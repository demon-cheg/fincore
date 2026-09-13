<?php

namespace App\Console\Commands;

use App\Actions\Transfers\CreateTransfer;
use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class CreateDemoTransfer extends Command
{
    protected $signature = 'demo:transfer
                            {--amount=2500 : Amount in minor currency units}';

    protected $description =
        'Create a demo transfer for local development and messaging tests';

    public function handle(CreateTransfer $createTransfer): int
    {
        if (! app()->environment('local')) {
            $this->error(
                'demo:transfer can only be executed in the local environment.'
            );

            return self::FAILURE;
        }

        $amountMinor = (int) $this->option('amount');

        if ($amountMinor <= 0) {
            $this->error('Amount must be greater than zero.');

            return self::FAILURE;
        }

        $user = User::query()
            ->where('email', 'demo@fincore.local')
            ->first();

        if (! $user) {
            $this->error(
                'Demo user not found. Run the database seeder first.'
            );

            return self::FAILURE;
        }

        $sourceAccount = Account::query()
            ->where('user_id', $user->id)
            ->where('status', AccountStatus::Active->value)
            ->where('balance_minor', '>=', $amountMinor)
            ->orderByDesc('balance_minor')
            ->first();

        if (! $sourceAccount) {
            $this->error(
                'Demo user has no active account with sufficient balance.'
            );

            return self::FAILURE;
        }

        $destinationAccount = Account::query()
            ->where('user_id', '!=', $user->id)
            ->where('currency', $sourceAccount->currency)
            ->where('status', AccountStatus::Active->value)
            ->orderBy('id')
            ->first();

        if (! $destinationAccount) {
            $this->error(
                "No active {$sourceAccount->currency} destination account found."
            );

            return self::FAILURE;
        }

        $idempotencyKey = (string) Str::uuid();

        try {
            $transfer = $createTransfer->execute(
                user: $user,
                sourceAccountId: $sourceAccount->id,
                destinationAccountId: $destinationAccount->id,
                amountMinor: $amountMinor,
                idempotencyKey: $idempotencyKey,
            );
        } catch (Throwable $exception) {
            $this->error(
                'Transfer failed: '.$exception->getMessage()
            );

            return self::FAILURE;
        }

        $this->newLine();

        $this->info('Demo transfer created.');

        $this->table(
            ['Field', 'Value'],
            [
                ['Transfer ID', $transfer->id],
                ['User', $user->email],
                ['Source account', $sourceAccount->id],
                ['Destination account', $destinationAccount->id],
                ['Currency', $sourceAccount->currency],
                ['Amount minor', $amountMinor],
                ['Idempotency key', $idempotencyKey],
            ]
        );

        $this->newLine();

        $this->info(
            'A pending transactional outbox event should now exist.'
        );

        return self::SUCCESS;
    }
}