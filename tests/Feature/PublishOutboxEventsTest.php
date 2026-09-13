<?php

namespace Tests\Feature;

use App\Contracts\Messaging\EventPublisher;
use App\Models\OutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PublishOutboxEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_event_is_published_and_marked_as_published(): void
    {
        config([
            'cache.default' => 'array',
        ]);

        $publisher = new class implements EventPublisher
        {
            public array $events = [];

            public function publish(
                string $eventId,
                string $eventType,
                int $schemaVersion,
                string $occurredAt,
                array $payload,
            ): void {
                $this->events[] = [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'schema_version' => $schemaVersion,
                    'occurred_at' => $occurredAt,
                    'payload' => $payload,
                ];
            }
        };

        $this->app->instance(
            EventPublisher::class,
            $publisher
        );

        $event = OutboxEvent::query()->create([
            'id' => (string) Str::uuid(),
            'event_type' => 'transfer.completed',
            'schema_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'transfer',
            'aggregate_id' => 123,
            'payload' => [
                'transfer_id' => 123,
                'amount_minor' => 2500,
                'currency' => 'EUR',
            ],
        ]);

        $this->artisan('outbox:publish')
            ->assertSuccessful();

        $event->refresh();

        $this->assertCount(
            1,
            $publisher->events
        );

        $this->assertSame(
            $event->id,
            $publisher->events[0]['event_id']
        );

        $this->assertNotNull(
            $event->published_at
        );

        $this->assertSame(
            1,
            $event->attempts
        );

        $this->assertNull(
            $event->last_error
        );
    }

    public function test_failed_publish_remains_pending(): void
    {
        config([
            'cache.default' => 'array',
        ]);

        $publisher = new class implements EventPublisher
        {
            public function publish(
                string $eventId,
                string $eventType,
                int $schemaVersion,
                string $occurredAt,
                array $payload,
            ): void {
                throw new RuntimeException(
                    'RabbitMQ unavailable'
                );
            }
        };

        $this->app->instance(
            EventPublisher::class,
            $publisher
        );

        $event = OutboxEvent::query()->create([
            'id' => (string) Str::uuid(),
            'event_type' => 'transfer.completed',
            'schema_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'transfer',
            'aggregate_id' => 123,
            'payload' => [
                'transfer_id' => 123,
            ],
        ]);

        $this->artisan('outbox:publish')
            ->assertSuccessful();

        $event->refresh();

        $this->assertNull(
            $event->published_at
        );

        $this->assertSame(
            1,
            $event->attempts
        );

        $this->assertStringContainsString(
            'RabbitMQ unavailable',
            $event->last_error
        );
    }
}