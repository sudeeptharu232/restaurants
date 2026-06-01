<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'invoice_date' => $this->invoice_date ? $this->invoice_date->format('Y-m-d') : null,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'notes' => $this->notes,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'taxable_amount' => (float) $this->taxable_amount,
            'vat_rate' => 13.0,
            'vat_amount' => (float) $this->vat_amount,
            'service_charge' => (float) $this->service_charge,
            'total' => (float) $this->total,
            'paid_amount' => (float) $this->paid_amount,
            'due_amount' => (float) $this->due_amount,
            'pdf_path' => $this->pdf_path,
            'pdf_url' => $this->pdf_path ? "/api/" . tenant('id') . "/invoices/{$this->id}/download-pdf" : null,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'address' => $this->customer->address,
            ] : null,
            'order' => $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status,
            ] : null,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
