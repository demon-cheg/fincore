<?php

namespace Tests\Feature;

use App\Actions\Audit\StoreAuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class StoreAuditEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_is_stored_only_once(): void
    {
        $action = app(StoreAuditEvent::class);

        $eventId = (string) Str::uuid();

        $payload = [
            'transfer_id' => 10,
            'amount_minor' => 2500,
            'currency' => 'EUR',
        ];

        $action->execute(
            eventId: $eventId,
            eventType: 'transfer.completed',
            schemaVersion: 1,
            payload: $payload,
        );

        $action->execute(
            eventId: $eventId,
            eventType: 'transfer.completed',
            schemaVersion: 1,
            payload: $payload,
        );

        $this->assertDatabaseCount(
            'audit_events',
            1
        );

        $this->assertDatabaseHas(
            'audit_events',
            [
                'event_id' => $eventId,
                'event_type' => 'transfer.completed',
            ]
        );
    }

    public function test_unsupported_schema_version_is_rejected(): void
    {
        $action = app(StoreAuditEvent::class);

        $this->expectException(
            RuntimeException::class
        );

        try {
            $action->execute(
                eventId: (string) Str::uuid(),
                eventType: 'transfer.completed',
                schemaVersion: 999,
                payload: [
                    'transfer_id' => 10,
                ],
            );
        } finally {
            $this->assertDatabaseCount(
                'audit_events',
                0
            );
        }
    }
}