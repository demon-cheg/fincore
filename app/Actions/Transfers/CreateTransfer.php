<?php

namespace App\Actions\Transfers;

use App\Enums\AccountStatus;
use App\Enums\TransferStatus;
use App\Models\Account;
use App\Models\Transfer;
use App\Models\User;
use App\Models\OutboxEvent;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateTransfer
{
    public function execute(
        User $user,
        int $sourceAccountId,
        int $destinationAccountId,
        int $amountMinor,
        string $idempotencyKey,
    ): Transfer {
        $requestHash = $this->makeRequestHash(
            userId: $user->id,
            sourceAccountId: $sourceAccountId,
            destinationAccountId: $destinationAccountId,
            amountMinor: $amountMinor,
        );

        $existingTransfer = Transfer::query()
            ->where('initiated_by_user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingTransfer) {
            $this->assertSameRequest($existingTransfer, $requestHash);

            return $existingTransfer;
        }

        $lockKey = sprintf(
            'transfer:%d:%s',
            $user->id,
            $idempotencyKey
        );

        return Cache::lock($lockKey, 10)->block(
        5,
        function () use (
            $user,
            $sourceAccountId,
            $destinationAccountId,
            $amountMinor,
            $idempotencyKey,
            $requestHash,
        ): Transfer {
            $existingTransfer = Transfer::query()
                ->where('initiated_by_user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingTransfer) {
                $this->assertSameRequest($existingTransfer, $requestHash);

                return $existingTransfer;
            }

            return DB::transaction(
            function () use (
                $user,
                $sourceAccountId,
                $destinationAccountId,
                $amountMinor,
                $idempotencyKey,
                $requestHash,
            ): Transfer {
                $accountIds = [
                    $sourceAccountId,
                    $destinationAccountId,
                ];

                sort($accountIds);

                $accounts = Account::query()
                    ->whereIn('id', $accountIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $sourceAccount = $accounts->get($sourceAccountId);
                $destinationAccount = $accounts->get($destinationAccountId);

                if (! $sourceAccount || ! $destinationAccount) {
                    throw ValidationException::withMessages([
                        'accounts' => ['One or more accounts do not exist.'],
                    ]);
                }

                if ($sourceAccount->user_id !== $user->id) {
                    throw new AuthorizationException(
                        'You are not allowed to transfer from this account.'
                    );
                }

                if ($sourceAccount->status !== AccountStatus::Active) {
                    throw ValidationException::withMessages([
                        'source_account_id' => [
                            'The source account is not active.',
                        ],
                    ]);
                }

                if ($destinationAccount->status !== AccountStatus::Active) {
                    throw ValidationException::withMessages([
                        'destination_account_id' => [
                            'The destination account is not active.',
                        ],
                    ]);
                }

                if ($sourceAccount->currency !== $destinationAccount->currency) {
                    throw ValidationException::withMessages([
                        'destination_account_id' => [
                            'Accounts must use the same currency.',
                        ],
                    ]);
                }

                if ($sourceAccount->balance_minor < $amountMinor) {
                    throw ValidationException::withMessages([
                        'amount_minor' => [
                            'Insufficient funds.',
                        ],
                    ]);
                }

                $sourceAccount->balance_minor -= $amountMinor;
                $destinationAccount->balance_minor += $amountMinor;

                $sourceAccount->save();
                $destinationAccount->save();

                $transfer = new Transfer();


                $transfer->request_hash = $requestHash;
                $transfer->initiated_by_user_id = $user->id;
                $transfer->source_account_id = $sourceAccount->id;
                $transfer->destination_account_id = $destinationAccount->id;
                $transfer->amount_minor = $amountMinor;
                $transfer->currency = $sourceAccount->currency;
                $transfer->status = TransferStatus::Completed;
                $transfer->idempotency_key = $idempotencyKey;

                $transfer->save();

                $outboxEvent = new OutboxEvent();

                $outboxEvent->id = (string) Str::uuid();
                $outboxEvent->event_type = 'transfer.completed';
                $outboxEvent->schema_version = 1;
                $outboxEvent->occurred_at = now();

                $outboxEvent->aggregate_type = 'transfer';
                $outboxEvent->aggregate_id = $transfer->id;

                $outboxEvent->payload = [
                    'transfer_id' => $transfer->id,
                    'source_account_id' => $sourceAccount->id,
                    'destination_account_id' => $destinationAccount->id,
                    'amount_minor' => $amountMinor,
                    'currency' => $sourceAccount->currency,
                    'initiated_by_user_id' => $user->id,
                ];

                $outboxEvent->save();

                return $transfer;
            });
        });
    }

    private function makeRequestHash(
        int $userId,
        int $sourceAccountId,
        int $destinationAccountId,
        int $amountMinor,
    ): string {
        return hash(
            'sha256',
            sprintf(
                '%d:%d:%d:%d',
                $userId,
                $sourceAccountId,
                $destinationAccountId,
                $amountMinor,
            )
        );
    }

    private function assertSameRequest(
        Transfer $transfer,
        string $requestHash,
    ): void {
        if ($transfer->request_hash !== $requestHash) {
            throw new HttpException(
                409,
                'This Idempotency-Key has already been used for a different request.'
            );
        }
    }
}