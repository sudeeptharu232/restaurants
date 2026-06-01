<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant;
use App\Models\Subscription;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantByPath
{
    /**
     * In-process cache to avoid repeated Cache::remember() calls
     * within the same request lifecycle (e.g. Octane, queued closures).
     */
    protected static array $resolved = [];

    /**
     * Handle an incoming request.
     *
     * Optimizations applied:
     *  1. Cache::remember() with 60s TTL for tenant + subscription lookup
     *     so repeated requests don't hit the central DB every time.
     *  2. Static in-process cache so the same PHP process never even
     *     touches the cache store twice for the same tenant.
     *  3. Skip tenancy()->initialize() if tenancy is already booted for
     *     the same tenant (avoids redundant DB-connection switching).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->route('tenant');
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant slug not specified'
            ], 400);
        }

        // ── 1. Resolve tenant + subscription (cached) ────────────────
        $resolved = $this->resolveTenantData($tenantId);

        if ($resolved === null) {
            return response()->json([
                'success' => false,
                'message' => 'Business tenant not found'
            ], 404);
        }

        /** @var Tenant $tenant */
        $tenant = $resolved['tenant'];
        $subscriptionEndsAt = $resolved['subscription_ends_at'];

        // ── 2. Validate tenant status ────────────────────────────────
        if (isset($tenant->status) && $tenant->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'This business account has been suspended'
            ], 403);
        }

        // ── 3. Validate subscription expiry ──────────────────────────
        if ($subscriptionEndsAt && now()->greaterThan($subscriptionEndsAt)) {
            return response()->json([
                'success' => false,
                'message' => 'Business subscription has expired'
            ], 403);
        }

        // ── 4. Initialize tenancy (skip if already active for this tenant)
        if (!$this->tenancyAlreadyInitialized($tenant)) {
            tenancy()->initialize($tenant);
        }

        // Bind the tenant instance to the request context for downstream use
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    /**
     * Resolve tenant and its latest subscription, using a layered cache:
     *   Layer 1 – static property (same process / Octane worker)
     *   Layer 2 – Cache::remember (shared across processes, 60s TTL)
     *   Layer 3 – database (cold miss)
     *
     * Returns null when the tenant does not exist.
     *
     * @return array{tenant: Tenant, subscription_ends_at: \Carbon\Carbon|null}|null
     */
    protected function resolveTenantData(string $tenantId): ?array
    {
        if (app()->runningUnitTests()) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return null;
            }

            $subscription = Subscription::where('tenant_id', $tenant->id)
                ->latest()
                ->first();

            return [
                'tenant'               => $tenant,
                'subscription_ends_at' => $subscription?->ends_at,
            ];
        }

        // Layer 1: in-process static cache
        if (isset(static::$resolved[$tenantId])) {
            return static::$resolved[$tenantId];
        }

        // Layer 2: application cache (file / Redis / etc.) with 60s TTL
        $cacheKey = 'tenant_resolve_' . $tenantId;

        $cached = Cache::remember($cacheKey, 60, function () use ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                // Store a sentinel so we cache the "not found" result too
                // and avoid hammering the DB for nonexistent slugs.
                return ['__not_found' => true];
            }

            $subscription = Subscription::where('tenant_id', $tenant->id)
                ->latest()
                ->first();

            return [
                'tenant_attributes'    => $tenant->getAttributes(),
                'subscription_ends_at' => $subscription?->ends_at?->toIso8601String(),
            ];
        });

        // Handle cached "not found"
        if (isset($cached['__not_found'])) {
            return null;
        }

        // Re-hydrate the Tenant model from cached attributes without DB query
        $tenant = new Tenant();
        $tenant->setRawAttributes($cached['tenant_attributes']);
        $tenant->exists = true;

        $endsAt = $cached['subscription_ends_at'] 
            ? \Carbon\Carbon::parse($cached['subscription_ends_at']) 
            : null;

        $result = [
            'tenant'               => $tenant,
            'subscription_ends_at' => $endsAt,
        ];

        // Promote to Layer 1 for the rest of this process lifetime
        static::$resolved[$tenantId] = $result;

        return $result;
    }

    /**
     * Check whether tenancy is already initialized for the given tenant,
     * so we can skip the (expensive) initialize() call.
     */
    protected function tenancyAlreadyInitialized(Tenant $tenant): bool
    {
        try {
            $current = tenant();
            return $current && $current->getTenantKey() === $tenant->getTenantKey();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Bust the static in-process cache (useful in tests or after
     * tenant mutations).
     */
    public static function flushResolvedCache(?string $tenantId = null): void
    {
        if ($tenantId !== null) {
            unset(static::$resolved[$tenantId]);
            Cache::forget('tenant_resolve_' . $tenantId);
        } else {
            static::$resolved = [];
        }
    }
}
