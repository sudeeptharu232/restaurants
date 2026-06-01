<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'owner_whatsapp_number', 'enabled', 'send_time', 'timezone',
    'include_sales_summary', 'include_payment_summary', 'include_due_summary',
    'include_top_products', 'include_inventory_alerts',
    'phone_number', 'is_enabled', 'report_types',
])]
class WhatsAppReportSetting extends Model
{
    protected $table = 'whatsapp_report_settings';

    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_enabled' => 'boolean',
            'include_sales_summary' => 'boolean',
            'include_payment_summary' => 'boolean',
            'include_due_summary' => 'boolean',
            'include_top_products' => 'boolean',
            'include_inventory_alerts' => 'boolean',
            'report_types' => 'array',
        ];
    }

    /**
     * Get the active phone number (owner_whatsapp_number or fallback to phone_number).
     */
    public function getActivePhoneAttribute(): ?string
    {
        return $this->owner_whatsapp_number ?? $this->phone_number ?? null;
    }

    /**
     * Get the effective enabled state.
     */
    public function getIsActiveAttribute(): bool
    {
        return (bool) ($this->enabled ?? $this->is_enabled ?? false);
    }
}
