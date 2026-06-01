<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'menu_item_id' => $this->menu_item_id,
            'product_id' => $this->product_id,
            'service_id' => $this->service_id,
            'name' => $this->name,
            'quantity' => (float)$this->quantity,
            'unit_price' => (float)$this->unit_price,
            'discount_amount' => (float)$this->discount_amount,
            'vat_amount' => (float)$this->vat_amount,
            'total_amount' => (float)$this->total_amount,
            'kitchen_status' => $this->kitchen_status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
