<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'report_date', 'total_sales', 'total_expenses', 'total_orders',
    'total_payments', 'total_due', 'net_revenue',
    'top_products', 'low_stock_items',
    'whatsapp_status', 'sent_at', 'error_message',
    // legacy columns kept for backward compat
    'new_customers', 'net_profit', 'summary_json',
])]
class DailyReport extends Model
{
    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'total_sales' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'total_payments' => 'decimal:2',
            'total_due' => 'decimal:2',
            'net_revenue' => 'decimal:2',
            'total_orders' => 'integer',
            'top_products' => 'array',
            'low_stock_items' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Scope: pending WhatsApp send.
     */
    public function scopePending($query)
    {
        return $query->where('whatsapp_status', 'pending');
    }

    /**
     * Scope: for a specific date.
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('report_date', $date);
    }
}
