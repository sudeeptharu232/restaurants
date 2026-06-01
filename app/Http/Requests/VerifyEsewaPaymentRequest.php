<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEsewaPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_uuid' => 'required|string|exists:payments,transaction_id',
            'total_amount' => 'required|numeric|min:0.01',
            'ref_id' => 'required|string',
        ];
    }
}
