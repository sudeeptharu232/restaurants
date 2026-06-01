<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'type' => $this->type,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'kitchen_status' => $this->kitchen_status,
            'subtotal' => (float)$this->subtotal,
            'discount_amount' => (float)$this->discount_amount,
            'tax_amount' => (float)$this->tax_amount,
            'vat_amount' => (float)$this->vat_amount,
            'service_charge_amount' => (float)$this->service_charge_amount,
            'total' => (float)$this->total,
            'paid_amount' => (float)$this->paid_amount,
            'due_amount' => (float)$this->due_amount,
            'notes' => $this->notes,
            'delivery_address' => $this->delivery_address,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ] : null,
            'table' => $this->table ? [
                'id' => $this->table->id,
                'table_number' => $this->table->table_number,
                'capacity' => $this->table->capacity,
                'status' => $this->table->status,
            ] : null,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'kitchen_tickets' => KitchenTicketResource::collection($this->whenLoaded('kitchenTickets')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
