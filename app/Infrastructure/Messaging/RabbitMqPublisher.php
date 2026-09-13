<?php

namespace App\Infrastructure\Messaging;

use App\Contracts\Messaging\EventPublisher;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

class RabbitMqPublisher implements EventPublisher
{
    /**
     * @param array<string, mixed> $payload
     */
    public function publish(
        string $eventId,
        string $eventType,
        int $schemaVersion,
        string $occurredAt,
        array $payload,
    ): void {
        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost'),
        );

        $channel = $connection->channel();

        try {
            $exchange = config('rabbitmq.exchange');

            $channel->exchange_declare(
                $exchange,
                'topic',
                false,
                true,
                false,
            );

            $nacked = false;

            $channel->set_nack_handler(
                function () use (&$nacked): void {
                    $nacked = true;
                }
            );

            $channel->confirm_select();

            $message = new AMQPMessage(
                json_encode([
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'schema_version' => $schemaVersion,
                    'occurred_at' => $occurredAt,
                    'payload' => $payload,
                ], JSON_THROW_ON_ERROR),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'message_id' => $eventId,
                    'type' => $eventType,
                    'timestamp' => time(),
                    'app_id' => 'fincore',
                ]
            );

            $channel->basic_publish(
                $message,
                $exchange,
                $eventType,
            );

            $channel->wait_for_pending_acks(5);

            if ($nacked) {
                throw new RuntimeException(
                    "RabbitMQ negatively acknowledged event {$eventId}."
                );
            }
        } finally {
            $channel->close();
            $connection->close();
        }
    }
}