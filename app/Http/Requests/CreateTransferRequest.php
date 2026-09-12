<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransferRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_account_id' => [
                'required',
                'integer',
                'exists:accounts,id',
            ],

            'destination_account_id' => [
                'required',
                'integer',
                'different:source_account_id',
                'exists:accounts,id',
            ],

            'amount_minor' => [
                'required',
                'integer',
                'min:1',
                'max:100000000',
            ],

            'idempotency_key' => [
                'required',
                'uuid',
            ],
        ];
    }
}