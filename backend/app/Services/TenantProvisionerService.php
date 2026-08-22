<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantProvisionerInterface;
use App\DTOs\CreateTenantDTO;
use App\Models\Tenant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TenantProvisionerService implements TenantProvisionerInterface
{
    /**
     * تنفيذ إنشاء وتجهيز المستأجر وقاعدة بياناته تلقائياً
     */
    public function provision(CreateTenantDTO $dto): Tenant
    {
        $plan = Plan::findOrFail($dto->planId);
        $tenantId = $dto->slug;

        $tenantData = [
            'id' => $tenantId,
            'name' => $dto->name,
            'slug' => $dto->slug,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'plan_id' => $plan->id,
            'status' => $dto->trialDays > 0 ? 'trial' : 'active',
            'trial_ends_at' => $dto->trialDays > 0 ? now()->addDays($dto->trialDays) : null,
            'subscription_ends_at' => $dto->trialDays > 0 ? now()->addDays($dto->trialDays) : now()->addMonth(),
            'settings' => [
                'theme_preference' => 'dark',
                'currency' => config('app.currency', 'EGP'),
            ],
            'enabled_features' => [],
        ];

        if (!empty($dto->tenancyDbName)) {
            $tenantData['tenancy_db_name'] = $dto->tenancyDbName;
        }

        if (!empty($dto->tenancyDbUsername)) {
            $tenantData['tenancy_db_username'] = $dto->tenancyDbUsername;
        }

        if (!empty($dto->tenancyDbPassword)) {
            $tenantData['tenancy_db_password'] = $dto->tenancyDbPassword;
        }

        $tenant = Tenant::create($tenantData);

        // 2. Provision Primary Subdomain
        $centralDomain = env('CENTRAL_DOMAIN', 'baraa-solutions.com');
        $primarySubdomain = $dto->slug . '.' . $centralDomain;
        $tenant->domains()->create([
            'domain' => $primarySubdomain,
        ]);

        // 3. Provision Custom Domain if requested
        if (!empty($dto->customDomain)) {
            $tenant->domains()->create([
                'domain' => $dto->customDomain,
            ]);
        }

        // 4. Record Initial Subscription
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => $dto->trialDays > 0 ? 'trialing' : 'active',
            'amount' => $plan->price_monthly,
            'starts_at' => now(),
            'ends_at' => $dto->trialDays > 0 ? now()->addDays($dto->trialDays) : now()->addMonth(),
            'payment_method' => 'manual',
            'notes' => __('super.auto_provision_note'),
        ]);

        // 5. Initialize tenant isolated database seed data
        $tenant->run(function () use ($dto) {
            // Seed permissions matrix in tenant DB
            (new \Database\Seeders\PermissionsSeeder)->run();

            $mainStore = Store::firstOrCreate(
                ['is_main' => true],
                [
                    'name' => __('common.main_store_default', [], 'ar') ?: 'الفرع والمخزن الرئيسي',
                    'code' => 'MAIN-01',
                    'type' => 'retail',
                    'is_active' => true,
                ]
            );

            $user = User::where('email', $dto->email)
                ->orWhere('phone', $dto->phone ?: '01000000000')
                ->first();

            if (!$user) {
                $user = User::create([
                    'name' => $dto->name,
                    'email' => $dto->email,
                    'phone' => $dto->phone ?: ($dto->slug . '_admin'),
                    'password' => Hash::make($dto->password),
                    'is_active' => true,
                    'default_store_id' => $mainStore->id,
                    'theme_preference' => 'dark',
                ]);
            } else {
                $user->update([
                    'name' => $dto->name,
                    'email' => $dto->email,
                    'password' => Hash::make($dto->password),
                    'is_active' => true,
                    'default_store_id' => $mainStore->id,
                ]);
            }

            $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
            $user->syncRoles([$adminRole]);

            // Seed default coffee inventory items
            (new \Database\Seeders\CoffeeItemsSeeder)->run();
        });

        return $tenant;
    }
}
