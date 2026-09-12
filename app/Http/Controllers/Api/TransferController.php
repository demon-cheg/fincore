<?php

namespace App\Http\Controllers\Api;

use App\Actions\Transfers\CreateTransfer;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTransferRequest;
use App\Http\Resources\TransferResource;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function store(
        CreateTransferRequest $request,
        CreateTransfer $createTransfer,
    ): JsonResponse {
        abort_unless(
            $request->user()->tokenCan('transfers:create'),
            403
        );

        $data = $request->validated();

        $transfer = $createTransfer->execute(
            user: $request->user(),
            sourceAccountId: $data['source_account_id'],
            destinationAccountId: $data['destination_account_id'],
            amountMinor: $data['amount_minor'],
            idempotencyKey: $data['idempotency_key'],
        );

        return (new TransferResource($transfer))
            ->response()
            ->setStatusCode(201);
    }
}