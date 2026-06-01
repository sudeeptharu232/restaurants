<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'ticket_number' => $this->ticket_number,
            'type' => $this->type,
            'status' => $this->status,
            'printed_at' => $this->printed_at ? $this->printed_at->toIso8601String() : null,
            'order_number' => $this->order ? $this->order->order_number : null,
            'table_number' => ($this->order && $this->order->table) ? $this->order->table->table_number : null,
            'items' => KitchenTicketItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
