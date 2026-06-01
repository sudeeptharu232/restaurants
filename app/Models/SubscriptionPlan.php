<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'slug', 'description', 'price', 'billing_interval', 'features', 'is_active', 'duration_days', 'max_staff', 'max_products', 'max_invoices_per_month', 'whatsapp_reports_enabled', 'analytics_enabled'])]
class SubscriptionPlan extends Model
{
    use SoftDeletes;

    /**
     * Bind strictly to central database connection.
     */
    protected $connection = 'central';

    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'json',
            'is_active' => 'boolean',
            'duration_days' => 'integer',
            'max_staff' => 'integer',
            'max_products' => 'integer',
            'max_invoices_per_month' => 'integer',
            'whatsapp_reports_enabled' => 'boolean',
            'analytics_enabled' => 'boolean',
        ];
    }

    /**
     * Get the subscriptions for the plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
