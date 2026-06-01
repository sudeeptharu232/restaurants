<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'amount' => (float)$this->amount,
            'gateway' => $this->gateway,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'payment_date' => $this->payment_date ? $this->payment_date->toIso8601String() : null,
            'notes' => $this->notes,
            
            // Summaries
            'invoice' => $this->whenLoaded('invoice', fn () => [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'total' => (float)$this->invoice->total,
                'due_amount' => (float)$this->invoice->due_amount,
            ], null),
            
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'total' => (float)$this->order->total,
                'due_amount' => (float)$this->order->due_amount,
            ], null),
            
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ], null),

            'gateway_response' => $this->gateway_response,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
