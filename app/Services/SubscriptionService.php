<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;

class SubscriptionService
{
    /**
     * Create a standard 14-day free trial subscription for a tenant.
     */
    public function createTrial(Tenant $tenant, string $planSlug = 'basic-plan'): Subscription
    {
        $plan = SubscriptionPlan::where('slug', $planSlug)->first();

        if (!$plan) {
            // Fallback plan values if not seeded
            $plan = SubscriptionPlan::create([
                'name' => 'Basic Plan',
                'slug' => 'basic-plan',
                'description' => 'Standard Growstro access tier',
                'price' => 1500.00,
                'billing_interval' => 'monthly',
                'features' => ['billing', 'inventory', 'reports'],
                'is_active' => true,
            ]);
        }

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'trialing',
            'starts_at' => now(),
            'ends_at' => now()->addDays(14),
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    /**
     * Check if a tenant's subscription is active and has not expired.
     */
    public function hasActiveSubscription(string $tenantId): bool
    {
        $subscription = Subscription::where('tenant_id', $tenantId)->latest()->first();

        if (!$subscription) {
            return false;
        }

        if ($subscription->ends_at && now()->greaterThan($subscription->ends_at)) {
            return false;
        }

        return in_array($subscription->status, ['trialing', 'active']);
    }
}
