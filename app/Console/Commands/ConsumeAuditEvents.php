<?php

namespace App\Console\Commands;

use App\Actions\Audit\StoreAuditEvent;
use App\Infrastructure\Messaging\RabbitMqTopology;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Throwable;

class ConsumeAuditEvents extends Command
{
    protected $signature = 'rabbitmq:consume-audit';

    protected $description =
        'Consume FinCore transfer events into audit storage';

    public function handle(
        RabbitMqTopology $topology,
        StoreAuditEvent $storeAuditEvent,
    ): int {
        // Ensure required queues/exchanges exist.
        $topology->declare();

        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost'),
        );

        $consumeChannel = $connection->channel();
        $publishChannel = $connection->channel();

        $publishChannel->confirm_select();

        $publishChannel->set_nack_handler(
            static function (
                AMQPMessage $message,
            ): void {
                throw new RuntimeException(
                    'RabbitMQ negatively acknowledged republished message.'
                );
            }
        );

        $consumeChannel->basic_qos(
            0,
            10,
            false,
        );

        $queue = config('rabbitmq.audit.queue');

        $this->info(
            "Consuming {$queue}. Press Ctrl+C to stop."
        );

        $consumeChannel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            function (
                AMQPMessage $message,
            ) use (
                $storeAuditEvent,
                $publishChannel,
            ): void {
                $this->processMessage(
                    $message,
                    $storeAuditEvent,
                    $publishChannel,
                );
            }
        );

        try {
            while ($consumeChannel->is_consuming()) {
                $consumeChannel->wait();
            }
        } finally {
            $publishChannel->close();
            $consumeChannel->close();
            $connection->close();
        }

        return self::SUCCESS;
    }

    private function processMessage(
        AMQPMessage $message,
        StoreAuditEvent $storeAuditEvent,
        AMQPChannel $publishChannel,
    ): void {
        try {
            $data = json_decode(
                $message->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $validated = Validator::make(
                $data,
                [
                    'event_id' => [
                        'required',
                        'uuid',
                    ],

                    'event_type' => [
                        'required',
                        'string',
                        'max:100',
                    ],

                    'schema_version' => [
                        'required',
                        'integer',
                        'min:1',
                    ],

                    'occurred_at' => [
                        'required',
                        'date',
                    ],

                    'payload' => [
                        'required',
                        'array',
                    ],
                ]
            )->validate();

            $storeAuditEvent->execute(
                eventId: $validated['event_id'],
                eventType: $validated['event_type'],
                schemaVersion:
                    $validated['schema_version'],
                payload: $validated['payload'],
            );

            // ACK only after DB operation succeeds.
            $message->ack();

            $this->info(
                sprintf(
                    'Consumed %s [%s]',
                    $validated['event_type'],
                    $validated['event_id'],
                )
            );
        } catch (Throwable $exception) {
            $this->handleFailure(
                $message,
                $publishChannel,
                $exception,
            );
        }
    }

    private function handleFailure(
        AMQPMessage $message,
        AMQPChannel $publishChannel,
        Throwable $exception,
    ): void {
        $retryCount =
            $this->getRetryCount($message);

        $maxRetries =
            config('rabbitmq.audit.max_retries');

        $routingKey =
            $message->getRoutingKey()
            ?? 'unknown';

        try {
            if ($retryCount < $maxRetries) {
                $nextRetry = $retryCount + 1;

                $this->republish(
                    channel: $publishChannel,
                    message: $message,
                    exchange:
                        config(
                            'rabbitmq.audit.retry_exchange'
                        ),
                    routingKey: $routingKey,
                    retryCount: $nextRetry,
                );

                /*
                 * Retry copy has been confirmed by RabbitMQ,
                 * so original can now be acknowledged.
                 */
                $message->ack();

                $this->warn(
                    sprintf(
                        'Retry %d/%d: %s',
                        $nextRetry,
                        $maxRetries,
                        $exception->getMessage(),
                    )
                );

                return;
            }

            $this->republish(
                channel: $publishChannel,
                message: $message,
                exchange:
                    config(
                        'rabbitmq.audit.dead_letter_exchange'
                    ),
                routingKey: $routingKey,
                retryCount: $retryCount,
            );

            // DLQ copy confirmed.
            $message->ack();

            $this->error(
                sprintf(
                    'Moved message to DLQ after %d retries: %s',
                    $retryCount,
                    $exception->getMessage(),
                )
            );
        } catch (Throwable $publishException) {
            /*
             * We failed to safely copy the message to either
             * retry or DLQ.
             *
             * Do NOT ACK it.
             */
            $message->nack(
                false,
                true,
            );

            $this->error(
                'Failed to republish message: '
                .$publishException->getMessage()
            );
        }
    }

    private function republish(
        AMQPChannel $channel,
        AMQPMessage $message,
        string $exchange,
        string $routingKey,
        int $retryCount,
    ): void {
        $properties =
            $message->get_properties();

        $headers = [];

        $applicationHeaders =
            $properties['application_headers']
            ?? null;

        if (
            $applicationHeaders
            instanceof AMQPTable
        ) {
            $headers =
                $applicationHeaders
                    ->getNativeData();
        }

        $headers['x-fincore-retry-count'] =
            $retryCount;

        $properties['application_headers'] =
            new AMQPTable($headers);

        $properties['delivery_mode'] =
            AMQPMessage::DELIVERY_MODE_PERSISTENT;

        $retryMessage = new AMQPMessage(
            $message->getBody(),
            $properties,
        );

        $channel->basic_publish(
            $retryMessage,
            $exchange,
            $routingKey,
        );

        /*
         * Original message is ACKed only after
         * RabbitMQ confirms this publish.
         */
        $channel->wait_for_pending_acks(5);
    }

    private function getRetryCount(
        AMQPMessage $message,
    ): int {
        $properties =
            $message->get_properties();

        $headers =
            $properties['application_headers']
            ?? null;

        if (! $headers instanceof AMQPTable) {
            return 0;
        }

        $data = $headers->getNativeData();

        return (int) (
            $data['x-fincore-retry-count']
            ?? 0
        );
    }
}