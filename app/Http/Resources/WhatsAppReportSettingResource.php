<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppReportSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'enabled'                  => (bool) ($this->enabled ?? $this->is_enabled ?? false),
            'owner_whatsapp_number'    => $this->owner_whatsapp_number ?? $this->phone_number,
            'send_time'                => $this->send_time,
            'timezone'                 => $this->timezone ?? 'Asia/Kathmandu',
            'include_sales_summary'    => (bool) ($this->include_sales_summary ?? true),
            'include_payment_summary'  => (bool) ($this->include_payment_summary ?? true),
            'include_due_summary'      => (bool) ($this->include_due_summary ?? true),
            'include_top_products'     => (bool) ($this->include_top_products ?? true),
            'include_inventory_alerts' => (bool) ($this->include_inventory_alerts ?? true),
            'created_at'               => $this->created_at?->toISOString(),
            'updated_at'               => $this->updated_at?->toISOString(),
        ];
    }
}
