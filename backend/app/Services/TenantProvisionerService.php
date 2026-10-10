<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantProvisionerInterface;
use App\DTOs\CreateTenantDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantProvisioningStatus;
use App\Enums\TenantStatus;
use App\Jobs\ProvisionTenantJob;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlatformHosts;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * The single provisioning path of a tenant (multi-tenancy rule 8), OPS-2:
 *
 *  1. provision(): ONE central transaction — tenant row (`provisioning_status = pending`,
 *     stancl's synchronous CreateDatabase pipeline switched off with
 *     `tenancy_create_database = false`), domains, initial subscription, audit
 *     `tenant_provisioning_started`; ProvisionTenantJob is dispatched AFTER COMMIT with the
 *     tenant id only: it reads the first admin's password HASH from the tenant row
 *     (storedPasswordHash(), encrypted at rest), never from its payload. No database is
 *     touched here.
 *  2. ProvisionTenantJob (queue): creates/verifies the database with the provisioner
 *     account, migrates it, then calls seedTenantDatabase() and marks the tenant ready.
 *
 * Keys kept in stancl's `data` column (internal prefix `tenancy_`) while provisioning:
 *  - SEED_KEY: the encrypted first-admin password hash the job seeds the admin with (also
 *    after a super-admin retry); removed once the tenant is ready;
 *  - EXPLICIT_DATABASE_KEY: the operator named the database (may adopt it if it is empty);
 *  - CREATED_DATABASE_KEY: the NAME of the database the job created;
 *  - CREATED_USER_KEY: ['username' => …, 'host' => …] of the per-tenant user the job created.
 *  They are ours (reused / dropped by failed()) ONLY while they equal the tenant's current
 *  database name / username: a renamed database or a username switched to a shared account
 *  is never migrated into nor dropped.
 *
 * `tenancy_db_password` is stored encrypted (Tenant::sealDatabasePassword()).
 */
class TenantProvisionerService implements TenantProvisionerInterface
{
    public const SEED_KEY = 'provisioning_seed';

    public const EXPLICIT_DATABASE_KEY = 'provisioning_explicit_db';

    public const CREATED_DATABASE_KEY = 'provisioner_created_db';

    public const CREATED_USER_KEY = 'provisioner_created_db_user';

    /**
     * Register the tenant and queue its provisioning. Returns the tenant as stored after
     * the commit: `pending` on a real queue (`ready` already on the sync driver).
     */
    public function provision(CreateTenantDTO $dto): Tenant
    {
        $plan = Plan::findOrFail($dto->planId);
        $passwordHash = Hash::make($dto->password);

        $tenant = DB::connection(self::centralConnection())->transaction(
            fn (): Tenant => $this->registerTenant($dto, $plan, $passwordHash),
        );

        return $tenant->refresh();
    }

    /**
     * Seed a migrated tenant database in ONE tenant transaction: permission matrix, main
     * store, first admin (looked up by the tenant's email) and company settings.
     * Idempotent: a re-run updates the same rows. Called by ProvisionTenantJob only.
     */
    public function seedTenantDatabase(Tenant $tenant, #[SensitiveParameter] string $passwordHash): void
    {
        $name = (string) $tenant->name;
        $email = (string) $tenant->email;
        $phone = $tenant->phone !== null && $tenant->phone !== '' ? (string) $tenant->phone : null;
        $slug = (string) $tenant->slug;

        $tenant->run(function () use ($name, $email, $phone, $slug, $passwordHash): void {
            DB::transaction(function () use ($name, $email, $phone, $slug, $passwordHash): void {
                // Tenant guard pinned explicitly: provisioning may run from a request
                // authenticated on `central` (sync queue) or from a worker.
                (new PermissionsSeeder)->run();

                // The tenant's language defaults to Arabic until onboarding (SETG-12) sets it.
                $mainStore = Store::query()->firstOrCreate(
                    ['is_main' => true],
                    [
                        'name' => __('common.main_store_default', [], 'ar'),
                        'code' => 'MAIN-01',
                        'type' => 'retail',
                        'is_active' => true,
                    ]
                );

                $user = User::query()->where('email', $email)->first() ?? new User;
                $user->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $user->exists ? $user->phone : ($phone ?? $slug.'_admin'),
                    // Already a hash: the `hashed` cast keeps a verified hash as is.
                    'password' => $passwordHash,
                    'is_active' => true,
                    'default_store_id' => $mainStore->id,
                    'theme_preference' => $user->exists ? $user->theme_preference : 'dark',
                ])->save();

                $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => PermissionsSeeder::GUARD]);
                $user->syncRoles([$adminRole]);

                Setting::set('company_name', $name);
                Setting::set('company_subtitle', __('provisioning.company_subtitle_default', [], 'ar'));
                if ($phone !== null) {
                    Setting::set('company_phone', $phone);
                }
            });
        });
    }

    /**
     * The first-admin password hash stored for a retry, or null when it is gone/unreadable.
     */
    public static function storedPasswordHash(Tenant $tenant): ?string
    {
        $sealed = $tenant->getInternal(self::SEED_KEY);
        if (! is_string($sealed) || $sealed === '') {
            return null;
        }

        try {
            $seed = json_decode(Crypt::decryptString($sealed), true, 4, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $hash = is_array($seed) ? ($seed['password_hash'] ?? null) : null;

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    /**
     * Seconds the job's unique lock lives; a pending/running provisioning idle for longer is
     * stale (Tenant::provisioningIsStale()). config tenancy.provisioning.unique_for.
     */
    public static function provisioningWindowSeconds(): int
    {
        return max(2850, (int) config('tenancy.provisioning.unique_for', 3600));
    }

    public static function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }

    private function registerTenant(CreateTenantDTO $dto, Plan $plan, #[SensitiveParameter] string $passwordHash): Tenant
    {
        // Lifecycle at birth (IDEN-3.2, CTO W1 Q4): a trial has no paid period yet
        // (subscription_ends_at = null). trial_days = 0 means "pending payment": the tenant
        // starts read-only and its subscription pending_payment until a verified payment
        // makes it active (ENTI-3.4, actor Billing; a super-admin activation goes through it too).
        $startsAsTrial = $dto->trialDays > 0;
        $now = now();

        $tenantData = [
            'id' => $dto->slug,
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
            // OPS-2: the database is created by ProvisionTenantJob, not by stancl's
            // synchronous TenantCreated pipeline (its CreateDatabase job stops on this flag).
            'provisioning_status' => TenantProvisioningStatus::Pending,
            'provisioning_attempts' => 0,
            'tenancy_create_database' => false,
            'tenancy_'.self::SEED_KEY => Crypt::encryptString((string) json_encode(['password_hash' => $passwordHash])),
        ];

        if (! empty($dto->tenancyDbName)) {
            $tenantData['tenancy_db_name'] = $dto->tenancyDbName;
            $tenantData['tenancy_'.self::EXPLICIT_DATABASE_KEY] = true;
        }

        if (! empty($dto->tenancyDbUsername)) {
            $tenantData['tenancy_db_username'] = $dto->tenancyDbUsername;
        }

        if (! empty($dto->tenancyDbPassword)) {
            $tenantData['tenancy_db_password'] = Tenant::sealDatabasePassword($dto->tenancyDbPassword);
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->create($tenantData);

        $tenant->domains()->create(['domain' => PlatformHosts::tenantHost($dto->slug)]);

        if (! empty($dto->customDomain)) {
            $tenant->domains()->create(['domain' => $dto->customDomain]);
        }

        // Initial subscription (central billing log, ENTI-1.3). Founder slots / price locks
        // are granted at first payment (ENTI-1.7, ENTI-3.4), never here.
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
            'starts_at' => $now,
            'ends_at' => $startsAsTrial ? $now->copy()->addDays($dto->trialDays) : $now->copy()->addMonth(),
            'payment_method' => 'manual',
            'notes' => __('super.auto_provision_note'),
        ]);

        // Joins this central transaction: rolls back with the tenant row.
        app(CentralAuditLogger::class)->record(
            CentralAuditEvent::TenantProvisioningStarted,
            ['slug' => $dto->slug, 'plan_id' => $plan->id, 'explicit_database' => ! empty($dto->tenancyDbName), 'retry' => false],
            subject: $tenant,
        );

        ProvisionTenantJob::dispatch((string) $tenant->getTenantKey())->afterCommit();

        return $tenant;
    }
}
