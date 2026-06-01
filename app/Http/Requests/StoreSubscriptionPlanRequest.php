<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_interval' => 'required|string|in:monthly,yearly',
            'duration_days' => 'required|integer|min:0',
            'max_staff' => 'nullable|integer|min:0',
            'max_products' => 'nullable|integer|min:0',
            'max_invoices_per_month' => 'nullable|integer|min:0',
            'whatsapp_reports_enabled' => 'required|boolean',
            'analytics_enabled' => 'required|boolean',
            'is_active' => 'sometimes|required|boolean',
        ];
    }
}
