<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenTicketItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kitchen_ticket_id' => $this->kitchen_ticket_id,
            'order_item_id' => $this->order_item_id,
            'quantity' => (float)$this->quantity,
            'status' => $this->status,
            'name' => $this->orderItem ? $this->orderItem->name : 'Unknown Item',
            'notes' => $this->orderItem ? $this->orderItem->notes : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
