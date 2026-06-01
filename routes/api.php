<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessRegistrationController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\RestaurantSpaceController;
use App\Http\Controllers\Api\RestaurantTableController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\KitchenTicketController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\EsewaPaymentController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\WhatsAppReportSettingController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Middleware\ResolveTenantByPath;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminTenantController;
use App\Http\Controllers\Api\AdminSubscriptionPlanController;
use App\Http\Controllers\Api\AdminSubscriptionController;
use App\Http\Controllers\Api\AdminPlatformAnalyticsController;
use App\Http\Controllers\Api\AdminAuditLogController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here you can register central and tenant API routes for your application.
| Central routes are globally available. Tenant-scoped routes are bound to
| dynamic path-resolved tenant databases.
|
|
*/

// ==========================================
// Central Platform APIs (Central Registry Scope)
// ==========================================

Route::prefix('auth')->group(function () {
    // Business Owner Self-Registration (Creates new Tenant & Database)
    Route::post('/register-business', [BusinessRegistrationController::class, 'register']);
    
    // Unified Authentication (Authenticates central admins OR tenant workers based on payload)
    Route::post('/login', [AuthController::class, 'login']);
    
    // Mock Recovery Endpoint
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    
    // Accept Staff Invitation (Phase 10)
    Route::post('/accept-staff-invitation', [\App\Http\Controllers\Api\StaffInvitationController::class, 'accept']);
});

// Central Authenticated User profile
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

// Central Admin Dashboard & Control Panel Endpoints (Phase 11)
Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    
    // Tenant management
    Route::get('/tenants', [AdminTenantController::class, 'index']);
    Route::post('/tenants', [AdminTenantController::class, 'store']);
    Route::get('/tenants/{id}', [AdminTenantController::class, 'show']);
    Route::put('/tenants/{id}', [AdminTenantController::class, 'update']);
    Route::delete('/tenants/{id}', [AdminTenantController::class, 'destroy']);
    Route::put('/tenants/{id}/activate', [AdminTenantController::class, 'activate']);
    Route::put('/tenants/{id}/deactivate', [AdminTenantController::class, 'deactivate']);
    Route::put('/tenants/{id}/suspend', [AdminTenantController::class, 'suspend']);
    Route::put('/tenants/{id}/restore', [AdminTenantController::class, 'restore']);
    Route::get('/tenants/{id}/summary', [AdminTenantController::class, 'summary']);

    // Subscription plan management
    Route::get('/subscription-plans', [AdminSubscriptionPlanController::class, 'index']);
    Route::post('/subscription-plans', [AdminSubscriptionPlanController::class, 'store']);
    Route::get('/subscription-plans/{id}', [AdminSubscriptionPlanController::class, 'show']);
    Route::put('/subscription-plans/{id}', [AdminSubscriptionPlanController::class, 'update']);
    Route::delete('/subscription-plans/{id}', [AdminSubscriptionPlanController::class, 'destroy']);

    // Subscriptions management
    Route::get('/subscriptions', [AdminSubscriptionController::class, 'index']);
    Route::get('/subscriptions/{id}', [AdminSubscriptionController::class, 'show']);
    Route::post('/tenants/{id}/subscriptions', [AdminSubscriptionController::class, 'assign']);
    Route::put('/subscriptions/{id}', [AdminSubscriptionController::class, 'update']);
    Route::put('/subscriptions/{id}/cancel', [AdminSubscriptionController::class, 'cancel']);
    Route::put('/subscriptions/{id}/expire', [AdminSubscriptionController::class, 'expire']);
    Route::put('/subscriptions/{id}/renew', [AdminSubscriptionController::class, 'renew']);

    // Platform analytics
    Route::get('/platform-analytics', [AdminPlatformAnalyticsController::class, 'index']);
    Route::get('/platform-analytics/tenants', [AdminPlatformAnalyticsController::class, 'tenants']);
    Route::get('/platform-analytics/revenue', [AdminPlatformAnalyticsController::class, 'revenue']);
    Route::get('/platform-analytics/usage', [AdminPlatformAnalyticsController::class, 'usage']);

    // Audit logs querying
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
    Route::get('/audit-logs/{id}', [AdminAuditLogController::class, 'show']);
});

// ==========================================
// Tenant Scoped APIs (Isolated Workspace Scope)
// ==========================================

Route::middleware([ResolveTenantByPath::class])->prefix('{tenant}')->group(function () {
    
    // Public eSewa Callback Endpoints (No Sanctum Required)
    Route::get('/payments/esewa/success', [EsewaPaymentController::class, 'esewaSuccess']);
    Route::get('/payments/esewa/failure', [EsewaPaymentController::class, 'esewaFailure']);

    // Isolated tenant user authentications
    Route::middleware('auth:sanctum')->group(function () {
        
        // Tenant profile querying
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Core business catalogs (isolated CRUD resources)
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('services', ServiceController::class);
        Route::apiResource('menu-items', MenuItemController::class);
        Route::apiResource('spaces', RestaurantSpaceController::class);
        Route::apiResource('tables', RestaurantTableController::class);
        
        // Orders API
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::put('/orders/{id}', [OrderController::class, 'update']);
        Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
        Route::post('/orders/{id}/complete', [OrderController::class, 'complete']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('/orders/{id}/status', [OrderController::class, 'status']);
        Route::post('/orders/{id}/kitchen-ticket', [OrderController::class, 'kitchenTicket']);

        // Kitchen Tickets API
        Route::get('/kitchen-tickets', [KitchenTicketController::class, 'index']);
        Route::get('/kitchen-tickets/{id}', [KitchenTicketController::class, 'show']);
        Route::put('/kitchen-tickets/{id}/status', [KitchenTicketController::class, 'updateStatus']);
        Route::put('/kitchen-tickets/{id}/items/{itemId}/status', [KitchenTicketController::class, 'updateItemStatus']);
        Route::post('/kitchen-tickets/{id}/print', [KitchenTicketController::class, 'print']);

        // Invoices API
        Route::apiResource('invoices', InvoiceController::class);
        Route::post('/orders/{id}/invoice', [InvoiceController::class, 'createFromOrder']);
        Route::post('/invoices/{id}/issue', [InvoiceController::class, 'issue']);
        Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel']);
        Route::get('/invoices/{id}/download-pdf', [InvoiceController::class, 'downloadPdf']);
        Route::post('/invoices/{id}/regenerate-pdf', [InvoiceController::class, 'regeneratePdf']);

        // Payments API
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{id}', [PaymentController::class, 'show']);
        Route::post('/payments/manual', [PaymentController::class, 'storeManual']);

        // eSewa Payment Integration API
        Route::post('/payments/esewa/initiate', [EsewaPaymentController::class, 'initiate']);
        Route::post('/payments/esewa/verify', [EsewaPaymentController::class, 'verify']);

        // Analytics API
        Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
        // Separate routes for filters and breakdowns
        Route::get('/analytics/sales', [AnalyticsController::class, 'sales']);
        Route::get('/analytics/payments', [AnalyticsController::class, 'payments']);
        Route::get('/analytics/customers', [AnalyticsController::class, 'customers']);
        Route::get('/analytics/products', [AnalyticsController::class, 'products']);
        Route::get('/analytics/expenses', [AnalyticsController::class, 'expenses']);
        Route::get('/analytics/due-summary', [AnalyticsController::class, 'dueSummary']);
        Route::get('/analytics/top-products', [AnalyticsController::class, 'topProducts']);
        Route::get('/analytics/daily-report', [AnalyticsController::class, 'dailyReport']);

        // WhatsApp Report Settings API (Phase 9)
        Route::get('/whatsapp-settings', [WhatsAppReportSettingController::class, 'show']);
        Route::put('/whatsapp-settings', [WhatsAppReportSettingController::class, 'update']);

        // Daily Reports API (Phase 9)
        Route::get('/daily-reports', [DailyReportController::class, 'index']);
        Route::post('/daily-reports/generate', [DailyReportController::class, 'generate']);
        Route::get('/daily-reports/{id}', [DailyReportController::class, 'show']);
        Route::post('/daily-reports/{id}/send-whatsapp', [DailyReportController::class, 'sendWhatsApp']);

        // Staff/User Management API (Phase 10)
        Route::get('/staff', [\App\Http\Controllers\Api\StaffController::class, 'index']);
        Route::post('/staff', [\App\Http\Controllers\Api\StaffController::class, 'store']);
        Route::get('/staff/{id}', [\App\Http\Controllers\Api\StaffController::class, 'show']);
        Route::put('/staff/{id}', [\App\Http\Controllers\Api\StaffController::class, 'update']);
        Route::delete('/staff/{id}', [\App\Http\Controllers\Api\StaffController::class, 'destroy']);
        Route::put('/staff/{id}/activate', [\App\Http\Controllers\Api\StaffController::class, 'activate']);
        Route::put('/staff/{id}/deactivate', [\App\Http\Controllers\Api\StaffController::class, 'deactivate']);
        Route::put('/staff/{id}/permissions', [\App\Http\Controllers\Api\StaffController::class, 'updatePermissions']);
        Route::put('/staff/{id}/role', [\App\Http\Controllers\Api\StaffController::class, 'updateRole']);

        // Staff Invitation API (Phase 10)
        Route::get('/staff-invitations', [\App\Http\Controllers\Api\StaffInvitationController::class, 'index']);
        Route::post('/staff-invitations', [\App\Http\Controllers\Api\StaffInvitationController::class, 'store']);
        Route::delete('/staff-invitations/{id}', [\App\Http\Controllers\Api\StaffInvitationController::class, 'destroy']);
        Route::post('/staff-invitations/{id}/resend', [\App\Http\Controllers\Api\StaffInvitationController::class, 'resend']);

        // Manager/Owner specific route mockup to verify role checks
        Route::middleware(['role:owner,manager'])->get('/settings', function () {
            return response()->json([
                'success' => true,
                'message' => 'Settings loaded successfully'
            ]);
        });
    });
});

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Growstro API is healthy'
    ]);
});
