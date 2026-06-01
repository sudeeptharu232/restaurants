<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'billing_interval' => 'sometimes|required|string|in:monthly,yearly',
            'duration_days' => 'sometimes|required|integer|min:0',
            'max_staff' => 'nullable|integer|min:0',
            'max_products' => 'nullable|integer|min:0',
            'max_invoices_per_month' => 'nullable|integer|min:0',
            'whatsapp_reports_enabled' => 'sometimes|required|boolean',
            'analytics_enabled' => 'sometimes|required|boolean',
            'is_active' => 'sometimes|required|boolean',
        ];
    }
}
