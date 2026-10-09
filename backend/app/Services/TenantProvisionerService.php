<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantProvisionerInterface;
use App\DTOs\CreateTenantDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TenantProvisionerService implements TenantProvisionerInterface
{
    /**
     * تنفيذ إنشاء وتجهيز المستأجر وقاعدة بياناته تلقائياً
     */
    public function provision(CreateTenantDTO $dto): Tenant
    {
        $plan = Plan::findOrFail($dto->planId);
        $tenantId = $dto->slug;

        // Lifecycle at birth (IDEN-3.2, CTO W1 Q4): a trial has no paid period yet
        // (subscription_ends_at = null). trial_days = 0 means "pending payment": the tenant
        // starts read-only and its subscription pending_payment until a verified payment
        // makes it active (ENTI-3.4, actor Billing; a super-admin activation goes through it too).
        $startsAsTrial = $dto->trialDays > 0;
        $now = now();

        $tenantData = [
            'id' => $tenantId,
            'name' => $dto->name,
            'slug' => $dto->slug,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'plan_id' => $plan->id,
            'status' => $startsAsTrial ? TenantStatus::Trial->value : TenantStatus::ReadOnly->value,
            'status_changed_at' => $now,
            'read_only_since' => $startsAsTrial ? null : $now,
            'trial_ends_at' => $startsAsTrial ? $now->copy()->addDays($dto->trialDays) : null,
            'subscription_ends_at' => null,
            'settings' => [
                'theme_preference' => 'dark',
                'currency' => config('app.currency', 'EGP'),
            ],
            'enabled_features' => [],
        ];

        if (! empty($dto->tenancyDbName)) {
            $tenantData['tenancy_db_name'] = $dto->tenancyDbName;
        }

        if (! empty($dto->tenancyDbUsername)) {
            $tenantData['tenancy_db_username'] = $dto->tenancyDbUsername;
        }

        if (! empty($dto->tenancyDbPassword)) {
            $tenantData['tenancy_db_password'] = $dto->tenancyDbPassword;
        }

        $tenant = Tenant::create($tenantData);

        // 2. Provision Primary Subdomain
        $centralDomain = env('CENTRAL_DOMAIN', 'baraa-solutions.com');
        $primarySubdomain = $dto->slug.'.'.$centralDomain;
        $tenant->domains()->create([
            'domain' => $primarySubdomain,
        ]);

        // 3. Provision Custom Domain if requested
        if (! empty($dto->customDomain)) {
            $tenant->domains()->create([
                'domain' => $dto->customDomain,
            ]);
        }

        // 4. Record Initial Subscription (central billing log, ENTI-1.3).
        // Founder slots / price locks are granted at first payment (ENTI-1.7, ENTI-3.4), never here.
        // trial_days = 0 → pending_payment (CTO W1 Q4); its dates are reset by the activation.
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Monthly,
            'status' => $startsAsTrial ? SubscriptionStatus::Trialing : SubscriptionStatus::PendingPayment,
            'amount' => $plan->price_monthly,
            'currency' => Subscription::DEFAULT_CURRENCY,
            'price_locked' => false,
            'is_founder' => false,
            'founder_price_until' => null,
            'starts_at' => now(),
            'ends_at' => $dto->trialDays > 0 ? now()->addDays($dto->trialDays) : now()->addMonth(),
            'payment_method' => 'manual',
            'notes' => __('super.auto_provision_note'),
        ]);

        // 5. Initialize tenant isolated database seed data
        $tenant->run(function () use ($dto) {
            // Seed permissions matrix in tenant DB
            (new PermissionsSeeder)->run();

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

            if (! $user) {
                $user = User::create([
                    'name' => $dto->name,
                    'email' => $dto->email,
                    'phone' => $dto->phone ?: ($dto->slug.'_admin'),
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

            $adminRole = Role::firstOrCreate(['name' => 'admin']);
            $user->syncRoles([$adminRole]);

            // Automatically set tenant company branding from creation DTO
            Setting::set('company_name', $dto->name);
            Setting::set('company_subtitle', 'لإدارة المبيعات والمخزون والفروع');
            if ($dto->phone) {
                Setting::set('company_phone', $dto->phone);
            }
        });

        return $tenant;
    }
}
