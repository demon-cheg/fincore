<?php

namespace App\Http\Resources;

use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Transfer $transfer */
        $transfer = $this->resource;

        return [
            'id' => $transfer->id,
            'source_account_id' =>
                $transfer->source_account_id,

            'destination_account_id' =>
                $transfer->destination_account_id,

            'amount_minor' =>
                $transfer->amount_minor,

            'currency' =>
                $transfer->currency,

            'status' =>
                $transfer->status->value,

            'created_at' =>
                $transfer->created_at?->toIso8601String(),
        ];
    }
}