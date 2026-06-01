<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminSubscriptionService
{
    protected AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog)
    {
        $this->auditLog = $auditLog;
    }

    // ==========================================
    // SUBSCRIPTION PLAN CRUD
    // ==========================================

    public function listPlans()
    {
        return SubscriptionPlan::all();
    }

    public function createPlan(array $data): SubscriptionPlan
    {
        $data['slug'] = Str::slug($data['name']);
        
        // Prevent duplicate slug
        $counter = 1;
        while (SubscriptionPlan::where('slug', $data['slug'])->exists()) {
            $data['slug'] = Str::slug($data['name']) . '-' . $counter;
            $counter++;
        }

        $plan = SubscriptionPlan::create($data);

        $this->auditLog->log("plan.created", null, null, SubscriptionPlan::class, $plan->id, null, $plan->toArray());

        return $plan;
    }

    public function updatePlan(int $id, array $data): SubscriptionPlan
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $oldValues = $plan->toArray();

        $plan->update($data);

        $this->auditLog->log("plan.updated", null, null, SubscriptionPlan::class, $plan->id, $oldValues, $plan->toArray());

        return $plan;
    }

    public function deletePlan(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);

        // Check if there are active subscriptions
        $hasActive = Subscription::where('subscription_plan_id', $plan->id)
            ->whereIn('status', ['active', 'trialing'])
            ->exists();

        if ($hasActive) {
            throw new ConflictHttpException("Cannot delete plan with active or trialing subscriptions");
        }

        $plan->delete();

        $this->auditLog->log("plan.deleted", null, null, SubscriptionPlan::class, $id, $plan->toArray());
    }

    // ==========================================
    // TENANT SUBSCRIPTIONS MANAGEMENT
    // ==========================================

    public function listSubscriptions(array $filters = [])
    {
        $query = Subscription::query()->with('plan', 'tenant');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function assignSubscription(string $tenantId, int $planId, ?array $dates = []): Subscription
    {
        $tenant = Tenant::findOrFail($tenantId);
        $plan = SubscriptionPlan::findOrFail($planId);

        // Cancel previous active/trialing subscriptions
        Subscription::where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'trialing'])
            ->update(['status' => 'canceled']);

        $duration = $plan->duration_days ?? 30;

        $startsAt = !empty($dates['starts_at']) ? Carbon::parse($dates['starts_at']) : Carbon::now();
        $endsAt = !empty($dates['ends_at']) ? Carbon::parse($dates['ends_at']) : $startsAt->copy()->addDays($duration);
        
        $status = 'active';
        if ($plan->slug === 'free-trial' || $plan->price == 0) {
            $status = 'trialing';
        }

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $status === 'trialing' ? $endsAt : null,
        ]);

        $this->auditLog->log(
            "subscription.assigned",
            $tenant->id,
            null,
            Subscription::class,
            $subscription->id,
            null,
            $subscription->toArray()
        );

        return $subscription;
    }

    public function cancelSubscription(int $id): Subscription
    {
        $sub = Subscription::findOrFail($id);
        $oldValues = $sub->toArray();

        $sub->status = 'canceled';
        $sub->save();

        $this->auditLog->log(
            "subscription.canceled",
            $sub->tenant_id,
            null,
            Subscription::class,
            $sub->id,
            $oldValues,
            $sub->toArray()
        );

        return $sub;
    }

    public function expireSubscription(int $id): Subscription
    {
        $sub = Subscription::findOrFail($id);
        $oldValues = $sub->toArray();

        $sub->status = 'expired';
        $sub->ends_at = Carbon::now();
        $sub->save();

        $this->auditLog->log(
            "subscription.expired",
            $sub->tenant_id,
            null,
            Subscription::class,
            $sub->id,
            $oldValues,
            $sub->toArray()
        );

        return $sub;
    }

    public function renewSubscription(int $id, ?int $planId = null): Subscription
    {
        $sub = Subscription::findOrFail($id);
        $plan = $planId ? SubscriptionPlan::findOrFail($planId) : $sub->plan;
        
        // Deactivate old subscription
        $sub->status = 'expired';
        $sub->save();

        $duration = $plan->duration_days ?? 30;

        $newSub = Subscription::create([
            'tenant_id' => $sub->tenant_id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => Carbon::now(),
            'ends_at' => Carbon::now()->addDays($duration),
        ]);

        $this->auditLog->log(
            "subscription.renewed",
            $sub->tenant_id,
            null,
            Subscription::class,
            $newSub->id,
            $sub->toArray(),
            $newSub->toArray()
        );

        return $newSub;
    }

    public function updateSubscription(int $id, array $data): Subscription
    {
        $sub = Subscription::findOrFail($id);
        $oldValues = $sub->toArray();

        $sub->update($data);

        $this->auditLog->log(
            "subscription.updated",
            $sub->tenant_id,
            null,
            Subscription::class,
            $sub->id,
            $oldValues,
            $sub->toArray()
        );

        return $sub;
    }
}
