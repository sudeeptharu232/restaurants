<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;

class AdminTenantService
{
    protected AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog)
    {
        $this->auditLog = $auditLog;
    }

    /**
     * List and filter tenants with PostgreSQL-compatible case-insensitive ILIKE searches.
     */
    public function listTenants(array $filters = [])
    {
        $query = Tenant::query();

        // 1. Filter by Status
        if (!empty($filters['status'])) {
            $status = $filters['status'];
            $query->where('data->status', $status);
        }

        // 2. Search by keyword
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('id', 'ILIKE', "%{$search}%")
                  ->orWhere('name', 'ILIKE', "%{$search}%")
                  ->orWhere('data->email', 'ILIKE', "%{$search}%")
                  ->orWhere('data->owner_email', 'ILIKE', "%{$search}%")
                  ->orWhere('data->phone', 'ILIKE', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get a single tenant's full profile details.
     */
    public function getTenantDetails(string $id): Tenant
    {
        $tenant = Tenant::findOrFail($id);
        
        // Load relationships
        $tenant->load('domains');
        
        // Load latest central subscription
        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->with('plan')
            ->latest()
            ->first();
            
        $tenant->subscription = $subscription;
        
        return $tenant;
    }

    /**
     * Change tenant status and log the action.
     */
    public function updateStatus(string $id, string $status): Tenant
    {
        $tenant = Tenant::findOrFail($id);
        $oldStatus = $tenant->status ?? 'active';

        $tenant->status = $status;
        $tenant->save();

        // Write central audit log
        $this->auditLog->log(
            "tenant.status_change",
            $tenant->id,
            null,
            Tenant::class,
            null,
            ['status' => $oldStatus],
            ['status' => $status]
        );

        return $tenant;
    }

    /**
     * Safely read tenant database summary using isolated DB context.
     * Reverts connection back to central context on completion or failure.
     */
    public function getTenantSummary(string $id): array
    {
        $tenant = Tenant::findOrFail($id);

        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->with('plan')
            ->latest()
            ->first();

        $summary = [
            'tenant_id' => $tenant->id,
            'name' => $tenant->name,
            'status' => $tenant->status ?? 'active',
            'email' => $tenant->email ?? null,
            'phone' => $tenant->phone ?? null,
            'subscription' => $subscription ? [
                'plan_name' => $subscription->plan->name,
                'status' => $subscription->status,
                'ends_at' => $subscription->ends_at ? $subscription->ends_at->toDateTimeString() : null,
            ] : null,
            'database_healthy' => false,
            'total_customers' => 0,
            'total_orders' => 0,
            'total_sales' => 0.00,
            'total_invoices' => 0,
            'total_due' => 0.00,
            'total_payments' => 0.00,
            'total_staff' => 0,
        ];

        try {
            // Swaps dynamic database context to tenant
            tenancy()->initialize($tenant);

            $summary['total_customers'] = Customer::count();
            $summary['total_orders'] = Order::count();
            $summary['total_sales'] = (double) Order::where('status', 'completed')->sum('total');
            $summary['total_invoices'] = Invoice::count();
            $summary['total_due'] = (double) Invoice::where('status', '!=', 'paid')->sum('due_amount');
            $summary['total_payments'] = (double) Payment::where('status', 'completed')->sum('amount');
            $summary['total_staff'] = User::count();
            $summary['database_healthy'] = true;
            
        } catch (\Exception $e) {
            $summary['database_healthy'] = false;
            $summary['database_error'] = $e->getMessage();
        } finally {
            // Restore central database context safely
            try {
                tenancy()->end();
            } catch (\Exception $e) {
                // Fail-safe
            }
        }

        return $summary;
    }

    /**
     * Safely delete a tenant in central registry.
     * Prevents dynamic DB deletion unless explicit force parameter is set.
     */
    public function deleteTenant(string $id, bool $force = false): void
    {
        $tenant = Tenant::findOrFail($id);

        if ($force) {
            // Destructive removal - drop tenant DB
            // Stancl Tenancy automatically handles DB drop when deleting the model
            $tenant->delete();
            
            $this->auditLog->log("tenant.force_deleted", $id, null, Tenant::class, null, ['id' => $id]);
        } else {
            // Soft deactivation
            $tenant->status = 'inactive';
            $tenant->save();
            
            $this->auditLog->log("tenant.soft_deleted", $id, null, Tenant::class, null, ['status' => 'active'], ['status' => 'inactive']);
        }
    }
}
