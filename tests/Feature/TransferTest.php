<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_transfer_money_from_own_account(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $source = Account::factory()->for($user)->create([
            'currency' => 'EUR',
            'balance_minor' => 10_000,
            'status' => AccountStatus::Active,
        ]);

        $destination = Account::factory()->for($recipient)->create([
            'currency' => 'EUR',
            'balance_minor' => 2_000,
            'status' => AccountStatus::Active,
        ]);

        Sanctum::actingAs($user, ['transfers:create']);

        $response = $this->postJson(
            '/api/transfers',
            [
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'amount_minor' => 2_500,
            ],
            [
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.amount_minor', 2500)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(
            7_500,
            $source->fresh()->balance_minor
        );

        $this->assertSame(
            4_500,
            $destination->fresh()->balance_minor
        );

        $this->assertDatabaseCount('transfers', 1);

        $this->assertDatabaseCount('outbox_events', 1);

        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'transfer.completed',
            'schema_version' => 1,
            'aggregate_type' => 'transfer',
        ]);
        $outboxEvent = \App\Models\OutboxEvent::query()->firstOrFail();

        $this->assertNotNull($outboxEvent->occurred_at);

        $this->assertSame(
            $source->id,
            $outboxEvent->payload['source_account_id']
        );
    }
    public function test_transfer_fails_when_balance_is_insufficient(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $source = Account::factory()->for($user)->create([
            'currency' => 'EUR',
            'balance_minor' => 1_000,
            'status' => AccountStatus::Active,
        ]);

        $destination = Account::factory()->for($recipient)->create([
            'currency' => 'EUR',
            'balance_minor' => 2_000,
            'status' => AccountStatus::Active,
        ]);

        Sanctum::actingAs($user, ['transfers:create']);

        $response = $this->postJson(
            '/api/transfers',
            [
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'amount_minor' => 2_500,
            ],
            [
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount_minor']);

        $this->assertSame(1_000, $source->fresh()->balance_minor);
        $this->assertSame(2_000, $destination->fresh()->balance_minor);

        $this->assertDatabaseCount('transfers', 0);
        $this->assertDatabaseCount('outbox_events', 0);
    }
    public function test_user_cannot_transfer_from_another_users_account(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $recipient = User::factory()->create();

        $foreignSource = Account::factory()->for($owner)->create([
            'currency' => 'EUR',
            'balance_minor' => 10_000,
            'status' => AccountStatus::Active,
        ]);

        $destination = Account::factory()->for($recipient)->create([
            'currency' => 'EUR',
            'balance_minor' => 2_000,
            'status' => AccountStatus::Active,
        ]);

        Sanctum::actingAs($user, ['transfers:create']);

        $response = $this->postJson(
            '/api/transfers',
            [
                'source_account_id' => $foreignSource->id,
                'destination_account_id' => $destination->id,
                'amount_minor' => 2_500,
            ],
            [
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertForbidden();

        $this->assertSame(
            10_000,
            $foreignSource->fresh()->balance_minor
        );

        $this->assertSame(
            2_000,
            $destination->fresh()->balance_minor
        );

        $this->assertDatabaseCount('transfers', 0);
        $this->assertDatabaseCount('outbox_events', 0);
    }
    public function test_duplicate_idempotent_request_does_not_transfer_money_twice(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $source = Account::factory()->for($user)->create([
            'currency' => 'EUR',
            'balance_minor' => 10_000,
            'status' => AccountStatus::Active,
        ]);

        $destination = Account::factory()->for($recipient)->create([
            'currency' => 'EUR',
            'balance_minor' => 2_000,
            'status' => AccountStatus::Active,
        ]);

        Sanctum::actingAs($user, ['transfers:create']);

        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'amount_minor' => 2_500,
        ];

        $headers = [
            'Idempotency-Key' => $idempotencyKey,
        ];

        $first = $this->postJson(
            '/api/transfers',
            $payload,
            $headers
        );

        $second = $this->postJson(
            '/api/transfers',
            $payload,
            $headers
        );

        $first->assertCreated();
        $second->assertCreated();

        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id')
        );

        $this->assertSame(
            7_500,
            $source->fresh()->balance_minor
        );

        $this->assertSame(
            4_500,
            $destination->fresh()->balance_minor
        );

        $this->assertDatabaseCount('transfers', 1);
    }
    public function test_idempotency_key_cannot_be_reused_for_different_request(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $source = Account::factory()->for($user)->create([
            'currency' => 'EUR',
            'balance_minor' => 20_000,
            'status' => AccountStatus::Active,
        ]);

        $destination = Account::factory()->for($recipient)->create([
            'currency' => 'EUR',
            'balance_minor' => 2_000,
            'status' => AccountStatus::Active,
        ]);

        Sanctum::actingAs($user, ['transfers:create']);

        $idempotencyKey = (string) Str::uuid();

        $first = $this->postJson(
            '/api/transfers',
            [
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'amount_minor' => 2_500,
            ],
            [
                'Idempotency-Key' => $idempotencyKey,
            ]
        );

        $first->assertCreated();

        $second = $this->postJson(
            '/api/transfers',
            [
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'amount_minor' => 5_000,
            ],
            [
                'Idempotency-Key' => $idempotencyKey,
            ]
        );

        $second->assertStatus(409);

        $this->assertSame(
            17_500,
            $source->fresh()->balance_minor
        );

        $this->assertSame(
            4_500,
            $destination->fresh()->balance_minor
        );

        $this->assertDatabaseCount('transfers', 1);
    }
}