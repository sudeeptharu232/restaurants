<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Category;
use App\Models\RestaurantSpace;
use App\Models\RestaurantTable;
use App\Models\BusinessSetting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;

class BusinessRegistrationService
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Register a new business, creating a dedicated tenant database and seeding default templates.
     */
    public function register(array $data): array
    {
        $businessName = $data['business_name'];
        
        // 1. Generate unique tenant ID (handle) from business name
        $slug = Str::slug($businessName);
        $tenantId = $slug;
        $counter = 1;
        while (Tenant::where('id', $tenantId)->exists()) {
            $tenantId = $slug . '-' . $counter;
            $counter++;
        }

        // 2. Create the tenant profile in the central system
        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => $businessName,
        ]);

        // 3. Create the localhost domain for development
        $domainName = $tenantId . '.localhost';
        $tenant->domains()->create([
            'domain' => $domainName,
        ]);

        // 4. Run the dynamic database provisioning and isolated migrations
        // stancl/tenancy will automatically trigger these during tenant creation events.
        // However, to ensure they run cleanly in local testing environments, we can manually check.

        $ownerArray = null;
        $token = null;

        // 5. Swap connection context to the isolated database to provision default resources
        $tenant->run(function () use ($data, $tenant, &$ownerArray, &$token) {
            // Seed the isolated tenant owner account
            $owner = User::create([
                'name' => $data['owner_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'owner',
                'is_active' => true,
            ]);

            // Seed central BusinessSettings
            $settings = [
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'address' => $data['address'],
                'phone' => $data['phone'],
                'pan_or_vat_number' => $data['pan_or_vat_number'] ?? null,
                'is_vat_registered' => (!empty($data['is_vat_registered']) && $data['is_vat_registered']) ? '1' : '0',
            ];

            foreach ($settings as $key => $val) {
                if ($val !== null) {
                    BusinessSetting::create([
                        'tenant_id' => $tenant->id,
                        'key' => $key,
                        'value' => (string) $val,
                    ]);
                }
            }

            // Seed trial subscription in the central database
            $this->subscriptionService->createTrial($tenant);

            // Seed default categories based on business type
            $categories = [];
            $businessType = $data['business_type'];

            if (in_array($businessType, ['restaurant', 'cafe'])) {
                $categories = [
                    ['name' => 'Beverages', 'slug' => 'beverages', 'type' => 'menu'],
                    ['name' => 'Appetizers', 'slug' => 'appetizers', 'type' => 'menu'],
                    ['name' => 'Main Course', 'slug' => 'main-course', 'type' => 'menu'],
                    ['name' => 'Desserts', 'slug' => 'desserts', 'type' => 'menu'],
                ];
            } elseif ($businessType === 'retail') {
                $categories = [
                    ['name' => 'Groceries', 'slug' => 'groceries', 'type' => 'product'],
                    ['name' => 'Beverages', 'slug' => 'beverages', 'type' => 'product'],
                    ['name' => 'Snacks & Packaged Foods', 'slug' => 'snacks-packaged', 'type' => 'product'],
                    ['name' => 'Household Care', 'slug' => 'household-care', 'type' => 'product'],
                ];
            } else {
                $categories = [
                    ['name' => 'General Products', 'slug' => 'general-products', 'type' => 'product'],
                    ['name' => 'General Services', 'slug' => 'general-services', 'type' => 'service'],
                ];
            }

            foreach ($categories as $cat) {
                Category::create([
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'type' => $cat['type'],
                    'is_active' => true,
                ]);
            }

            // Seed default dining layout if Restaurant or Cafe
            if (in_array($businessType, ['restaurant', 'cafe'])) {
                $space = RestaurantSpace::create([
                    'name' => 'Main Dining Area',
                    'is_active' => true,
                ]);

                for ($i = 1; $i <= 4; $i++) {
                    RestaurantTable::create([
                        'restaurant_space_id' => $space->id,
                        'table_number' => 'Table ' . $i,
                        'capacity' => 4,
                        'status' => 'vacant',
                    ]);
                }
            }

            // Generate the access token inside the tenant database scope
            $token = $owner->createToken('auth_token')->plainTextToken;
            
            // Resolve the resource immediately to avoid serialization issues outside the tenant context
            $ownerArray = (new \App\Http\Resources\UserResource($owner))->resolve();
        });

        return [
            'user' => $ownerArray,
            'token' => $token,
            'tenant' => $tenant,
        ];
    }
}
