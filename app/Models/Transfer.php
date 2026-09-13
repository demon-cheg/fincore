<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $initiated_by_user_id
 * @property int $source_account_id
 * @property int $destination_account_id
 * @property int $amount_minor
 * @property string $currency
 * @property TransferStatus $status
 * @property string $idempotency_key
 * @property string|null $request_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Transfer extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => TransferStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'initiated_by_user_id'
        );
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'source_account_id'
        );
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'destination_account_id'
        );
    }
}