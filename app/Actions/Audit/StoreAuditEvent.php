<?php

namespace App\Actions\Audit;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StoreAuditEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function execute(
        string $eventId,
        string $eventType,
        int $schemaVersion,
        array $payload,
    ): void {
        if ($schemaVersion !== 1) {
            throw new RuntimeException(
                "Unsupported event schema version: {$schemaVersion}"
            );
        }

        DB::transaction(function () use (
            $eventId,
            $eventType,
            $payload,
        ): void {
            AuditEvent::query()->firstOrCreate(
                [
                    'event_id' => $eventId,
                ],
                [
                    'event_type' => $eventType,
                    'payload' => $payload,
                    'received_at' => now(),
                ]
            );
        });
    }
}