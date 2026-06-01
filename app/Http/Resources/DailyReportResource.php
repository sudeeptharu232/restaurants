<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'report_date'     => $this->report_date instanceof \Carbon\Carbon
                ? $this->report_date->toDateString()
                : (string) $this->report_date,
            'total_sales'     => (float) $this->total_sales,
            'total_orders'    => (int) $this->total_orders,
            'total_payments'  => (float) $this->total_payments,
            'total_due'       => (float) $this->total_due,
            'total_expenses'  => (float) $this->total_expenses,
            'net_revenue'     => (float) $this->net_revenue,
            'top_products'    => $this->top_products ?? [],
            'low_stock_items' => $this->low_stock_items ?? [],
            'whatsapp_status' => $this->whatsapp_status ?? 'pending',
            'sent_at'         => $this->sent_at?->toISOString(),
            'error_message'   => $this->error_message,
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
