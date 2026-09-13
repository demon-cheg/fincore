<?php

namespace App\Infrastructure\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMqTopology
{
    public function declare(): void
    {
        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost'),
        );

        $channel = $connection->channel();

        try {
            $eventsExchange = config('rabbitmq.exchange');

            $queue = config('rabbitmq.audit.queue');
            $binding = config('rabbitmq.audit.binding');

            $retryExchange =
                config('rabbitmq.audit.retry_exchange');

            $retryQueue =
                config('rabbitmq.audit.retry_queue');

            $deadLetterExchange =
                config('rabbitmq.audit.dead_letter_exchange');

            $deadLetterQueue =
                config('rabbitmq.audit.dead_letter_queue');

            $retryDelay =
                config('rabbitmq.audit.retry_delay_ms');

            // Main domain exchange
            $channel->exchange_declare(
                $eventsExchange,
                'topic',
                false,
                true,
                false,
            );

            // Retry exchange
            $channel->exchange_declare(
                $retryExchange,
                'topic',
                false,
                true,
                false,
            );

            // Dead-letter exchange
            $channel->exchange_declare(
                $deadLetterExchange,
                'topic',
                false,
                true,
                false,
            );

            // Main audit queue
            $channel->queue_declare(
                $queue,
                false,
                true,
                false,
                false,
            );

            $channel->queue_bind(
                $queue,
                $eventsExchange,
                $binding,
            );

            // Retry queue:
            // message waits here for N ms,
            // then RabbitMQ sends it back to fincore.events.
            $channel->queue_declare(
                $retryQueue,
                false,
                true,
                false,
                false,
                false,
                new AMQPTable([
                    'x-message-ttl' => $retryDelay,

                    'x-dead-letter-exchange' =>
                        $eventsExchange,
                ]),
            );

            $channel->queue_bind(
                $retryQueue,
                $retryExchange,
                '#',
            );

            // Final DLQ
            $channel->queue_declare(
                $deadLetterQueue,
                false,
                true,
                false,
                false,
            );

            $channel->queue_bind(
                $deadLetterQueue,
                $deadLetterExchange,
                '#',
            );
        } finally {
            $channel->close();
            $connection->close();
        }
    }
}