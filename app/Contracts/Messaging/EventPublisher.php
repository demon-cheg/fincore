<?php

namespace App\Contracts\Messaging;

interface EventPublisher
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
    ): void;
}