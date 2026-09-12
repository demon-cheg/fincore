<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'initiated_by_user_id'
        );
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'source_account_id'
        );
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'destination_account_id'
        );
    }
}