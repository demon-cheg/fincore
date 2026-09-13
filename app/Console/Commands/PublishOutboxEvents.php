<?php

namespace App\Console\Commands;

use App\Contracts\Messaging\EventPublisher;
use App\Models\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PublishOutboxEvents extends Command
{
    protected $signature = 'outbox:publish {--limit=100}';

    protected $description =
        'Publish pending outbox events to RabbitMQ';

    public function handle(
        EventPublisher $publisher,
    ): int {
        $lock = Cache::lock(
            'outbox:publisher',
            30
        );

        if (! $lock->get()) {
            $this->warn(
                'Another outbox publisher is already running.'
            );

            return self::SUCCESS;
        }

        try {
            $events = OutboxEvent::query()
                ->whereNull('published_at')
                ->orderBy('created_at')
                ->limit((int) $this->option('limit'))
                ->get();

            if ($events->isEmpty()) {
                $this->info('No pending events.');

                return self::SUCCESS;
            }

            foreach ($events as $event) {
                try {
                    $publisher->publish(
                        eventId: $event->id,
                        eventType: $event->event_type,
                        schemaVersion: $event->schema_version,
                        occurredAt: $event->occurred_at->toIso8601String(),
                        payload: $event->payload,
                    );

                    $event->published_at = now();
                    $event->attempts++;
                    $event->last_error = null;
                    $event->save();

                    $this->info(
                        "Published {$event->event_type} [{$event->id}]"
                    );
                } catch (Throwable $exception) {
                    $event->attempts++;
                    $event->last_error = mb_substr(
                        $exception->getMessage(),
                        0,
                        2000
                    );

                    $event->save();

                    $this->error(
                        "Failed {$event->id}: {$exception->getMessage()}"
                    );
                }
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}