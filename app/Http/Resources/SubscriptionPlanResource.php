<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (double) $this->price,
            'billing_interval' => $this->billing_interval,
            'duration_days' => $this->duration_days,
            'max_staff' => $this->max_staff,
            'max_products' => $this->max_products,
            'max_invoices_per_month' => $this->max_invoices_per_month,
            'whatsapp_reports_enabled' => (bool) $this->whatsapp_reports_enabled,
            'analytics_enabled' => (bool) $this->analytics_enabled,
            'features' => $this->features,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
