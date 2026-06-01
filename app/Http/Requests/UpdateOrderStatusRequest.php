<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:draft,pending,preparing,ready,served,completed,cancelled',
            'payment_status' => 'nullable|string|in:unpaid,partially_paid,paid,refunded',
            'kitchen_status' => 'nullable|string|in:pending,preparing,ready,served',
        ];
    }
}
