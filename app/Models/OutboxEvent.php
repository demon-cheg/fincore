<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_type
 * @property int $schema_version
 * @property Carbon $occurred_at
 * @property string $aggregate_type
 * @property int $aggregate_id
 * @property array<string, mixed> $payload
 * @property int $attempts
 * @property Carbon|null $published_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'id',
    'event_type',
    'schema_version',
    'occurred_at',
    'aggregate_type',
    'aggregate_id',
    'payload',
    'attempts',
    'published_at',
    'last_error',
])]
class OutboxEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'payload' => 'array',
            'attempts' => 'integer',
            'occurred_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}