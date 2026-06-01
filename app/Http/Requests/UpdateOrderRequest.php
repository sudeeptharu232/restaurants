<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'restaurant_table_id' => 'nullable|exists:restaurant_tables,id',
            'order_type' => 'nullable|string|in:dine_in,delivery,takeaway,pickup,reservation,qr_menu,regular',
            'status' => 'nullable|string|in:draft,pending,preparing,ready,served,completed,cancelled',
            'payment_status' => 'nullable|string|in:unpaid,partially_paid,paid,refunded',
            'discount_amount' => 'nullable|numeric|min:0',
            'service_charge_amount' => 'nullable|numeric|min:0',
            'delivery_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'nullable|array|min:1',
            'items.*.menu_item_id' => 'nullable|exists:menu_items,id',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.service_id' => 'nullable|exists:services,id',
            'items.*.name' => 'nullable|string',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ];
    }
}
