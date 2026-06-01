<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Enforced at controller gate
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|integer|exists:customers,id',
            'order_id' => 'nullable|integer|exists:orders,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'service_charge_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'items' => 'required_without:order_id|array',
            'items.*.menu_item_id' => 'nullable|integer|exists:menu_items,id',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.service_id' => 'nullable|integer|exists:services,id',
            'items.*.name' => 'nullable|string|max:255',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ];
    }
}
