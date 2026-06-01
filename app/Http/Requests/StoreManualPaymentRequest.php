<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => 'nullable|exists:invoices,id',
            'order_id' => 'nullable|exists:orders,id',
            'customer_id' => 'nullable|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'gateway' => 'required|string|in:cash,bank,qr,credit',
            'transaction_id' => 'nullable|string|max:255',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
