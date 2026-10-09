<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Contracts\TenantFeatureManagerInterface;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\LimitResource;
use App\Enums\TenantStatus;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Services\Entitlements\TenantEntitlementService;
use Database\Seeders\Catalog\FeatureCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * ENTI-2.2: TenantEntitlementService is the single source of truth for features and limits.
 *  features = plan ∪ active add-ons ∪ overrides (aliases resolved);
 *  limits   = plan + Σ add-on increments, NULL = unlimited;
 *  add-ons count during past_due (grace) and stop once the TENANT is read_only / suspended /
 *  cancelled / archived (CTO decision 2026-10-09).
 */
#[Group('entitlements')]
#[Group('mysql')]
final class TenantEntitlementServiceTest extends TenantTestCase
{
    public function test_the_feature_manager_contract_is_bound_to_the_entitlement_service(): void
    {
        $this->assertInstanceOf(TenantEntitlementService::class, app(TenantFeatureManagerInterface::class));
    }

    public function test_features_are_the_union_of_plan_add_ons_and_overrides(): void
    {
        $plan = $this->plan(['features' => ['pos.access' => true, 'reports.basic' => true, 'reports.advanced' => false]]);
        $tenant = $this->createTenant(['plan_id' => $plan->id, 'enabled_features' => ['audit.logs']]);
        $this->addonLine($this->subscription($tenant, $plan), $this->addon('reports.advanced', featureKey: 'reports.advanced'));

        $entitlements = $this->service()->entitlements($tenant);

        $this->assertSame(['audit.logs', 'pos.access', 'reports.advanced', 'reports.basic'], $entitlements->features);
        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'reports.advanced'), 'An active add-on grants its feature.');
        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'audit.logs'), 'An override grants its feature.');
        $this->assertFalse($this->service()->isFeatureEnabled($tenant, 'api.access'));
        $this->assertSame(['key' => 'reports.advanced', 'quantity' => 1], $entitlements->addons[0]);
    }

    public function test_the_blender_access_alias_resolves_to_mixes_manage_both_ways(): void
    {
        $plan = $this->plan(['features' => ['blender.access' => true]]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);

        $this->assertSame(['mixes.manage'], $this->service()->entitlements($tenant)->features, 'A legacy key stored in plans.features grants the canonical key.');
        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'blender.access'));
        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'mixes.manage'));

        $other = $this->createTenant(['plan_id' => $this->plan()->id, 'enabled_features' => ['blender.access']]);
        $this->assertTrue($this->service()->isFeatureEnabled($other, 'mixes.manage'), 'A legacy override grants the canonical key.');
    }

    public function test_limits_are_plan_plus_add_on_increments_times_quantity(): void
    {
        $plan = $this->plan(['max_users' => 3, 'max_stores' => 1, 'max_vans' => 0, 'max_items' => 200, 'max_storage_mb' => 500]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $subscription = $this->subscription($tenant, $plan);

        $this->addonLine($subscription, $this->addon('addon.store', limits: ['stores' => 1, 'users' => 1]), quantity: 2);
        $this->addonLine($subscription, $this->addon('addon.van', limits: ['vans' => 1, 'users' => 1]));
        $this->addonLine($subscription, $this->addon('addon.items_5k', limits: ['items' => 5000, 'unknown_resource' => 7]));

        $service = $this->service();

        $this->assertSame(6, $service->limit($tenant, LimitResource::Users), '3 + 2×1 + 1×1');
        $this->assertSame(3, $service->limit($tenant, LimitResource::Stores), '1 + 2×1');
        $this->assertSame(1, $service->limit($tenant, LimitResource::Vans));
        $this->assertSame(5200, $service->limit($tenant, LimitResource::Items));
        $this->assertSame(500, $service->limit($tenant, LimitResource::StorageMb));
        $this->assertSame(LimitResource::values(), array_keys($service->entitlements($tenant)->limits), 'Unknown add-on resource keys are ignored.');
    }

    public function test_a_null_plan_limit_means_unlimited_whatever_the_add_ons(): void
    {
        $plan = $this->plan(['max_users' => null, 'max_invoices_per_month' => null]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $this->addonLine($this->subscription($tenant, $plan), $this->addon('addon.user', limits: ['users' => 1]), quantity: 4);

        $this->assertNull($this->service()->limit($tenant, LimitResource::Users));
        $this->assertNull($this->service()->limit($tenant, LimitResource::InvoicesMonth));
    }

    public function test_add_ons_keep_working_while_the_subscription_is_past_due(): void
    {
        $plan = $this->plan(['max_users' => 3]);
        $tenant = $this->createTenant(['plan_id' => $plan->id, 'status' => TenantStatus::PastDue->value]);
        $subscription = $this->subscription($tenant, $plan, SubscriptionStatus::PastDue, endsAt: now()->subDays(2));
        $this->addonLine($subscription, $this->addon('addon.user', limits: ['users' => 1]), quantity: 2);
        $this->addonLine($subscription, $this->addon('mixes.manage', featureKey: 'mixes.manage'));

        $this->assertSame(5, $this->service()->limit($tenant, LimitResource::Users));
        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'mixes.manage'));
    }

    /**
     * @return iterable<string, array{0: TenantStatus}>
     */
    public static function statusesWithoutAddOns(): iterable
    {
        yield 'read_only' => [TenantStatus::ReadOnly];
        yield 'suspended' => [TenantStatus::Suspended];
        yield 'cancelled' => [TenantStatus::Cancelled];
        yield 'archived' => [TenantStatus::Archived];
    }

    #[DataProvider('statusesWithoutAddOns')]
    public function test_add_ons_stop_once_the_tenant_leaves_full_access(TenantStatus $status): void
    {
        $plan = $this->plan(['max_users' => 3, 'features' => ['pos.access' => true]]);
        $tenant = $this->createTenant(['plan_id' => $plan->id, 'enabled_features' => ['audit.logs']]);
        $subscription = $this->subscription($tenant, $plan, SubscriptionStatus::PastDue, endsAt: now()->subDays(10));
        $this->addonLine($subscription, $this->addon('addon.user', limits: ['users' => 1]), quantity: 2);
        $this->addonLine($subscription, $this->addon('mixes.manage', featureKey: 'mixes.manage'));

        $this->assertSame(5, $this->service()->limit($tenant, LimitResource::Users), 'Precondition: add-ons count while the tenant is active.');

        $tenant->update(['status' => $status->value]);

        $entitlements = $this->service()->entitlements($tenant->fresh() ?? $tenant);
        $this->assertSame(3, $entitlements->limit(LimitResource::Users), 'Add-on limits are gone.');
        $this->assertFalse($entitlements->hasFeature('mixes.manage'), 'Add-on features are gone.');
        $this->assertSame([], $entitlements->addons);
        $this->assertTrue($entitlements->hasFeature('pos.access'), 'Plan features stay.');
        $this->assertTrue($entitlements->hasFeature('audit.logs'), 'Overrides stay.');
    }

    public function test_an_unknown_status_grants_no_add_on(): void
    {
        $plan = $this->plan(['max_users' => 3]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $this->addonLine($this->subscription($tenant, $plan), $this->addon('addon.user', limits: ['users' => 1]));
        DB::connection($this->centralConnectionName())->table('tenants')->where('id', $tenant->id)->update(['status' => 'mystery']);

        $this->assertSame(3, $this->service()->resolve((string) $tenant->id)->limit(LimitResource::Users));
    }

    public function test_inactive_lines_and_other_tenants_lines_never_count(): void
    {
        $plan = $this->plan(['max_users' => 3]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $other = $this->createTenant(['plan_id' => $plan->id]);
        $userAddon = $this->addon('addon.user', limits: ['users' => 1]);

        $this->addonLine($this->subscription($tenant, $plan), $userAddon, status: SubscriptionAddonStatus::PendingPayment);
        $this->addonLine($this->subscription($tenant, $plan, SubscriptionStatus::Cancelled), $userAddon);
        $this->addonLine($this->subscription($other, $plan), $userAddon, quantity: 9);

        $this->assertSame(3, $this->service()->limit($tenant, LimitResource::Users));
        $this->assertSame(12, $this->service()->limit($other, LimitResource::Users));
    }

    public function test_a_tenant_without_plan_gets_nothing_but_its_overrides(): void
    {
        $tenant = $this->createTenant(['plan_id' => null, 'enabled_features' => ['pos.access']]);

        $entitlements = $this->service()->entitlements($tenant);

        $this->assertSame(['pos.access'], $entitlements->features);
        $this->assertSame(array_fill_keys(LimitResource::values(), 0), $entitlements->limits, 'No plan = default deny.');
        $this->assertNull($entitlements->planId);
    }

    public function test_an_unknown_tenant_gets_nothing(): void
    {
        $entitlements = $this->service()->entitlements('no-such-tenant');

        $this->assertSame([], $entitlements->features);
        $this->assertSame(0, $entitlements->limit(LimitResource::Users));
    }

    public function test_it_answers_the_same_inside_a_tenant_context(): void
    {
        $plan = $this->plan(['max_users' => 3, 'features' => ['reports.basic' => true]]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $this->addonLine($this->subscription($tenant, $plan), $this->addon('addon.user', limits: ['users' => 1]));

        $inside = $this->inTenant($tenant, fn (): array => [
            'default' => DB::getDefaultConnection(),
            'entitlements' => $this->service()->resolve((string) $tenant->id)->toArray(),
        ]);

        $this->assertNotSame($this->centralConnectionName(), $inside['default'], 'Tenancy must switch the default connection for this test to mean anything.');
        $this->assertSame($this->service()->resolve((string) $tenant->id)->toArray(), $inside['entitlements']);
        $this->assertSame(4, $inside['entitlements']['limits']['users']);
    }

    public function test_toggle_override_uses_the_canonical_key_and_returns_the_list(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan()->id, 'enabled_features' => ['blender.access', 'audit.logs']]);

        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'mixes.manage'));

        $afterOff = $this->service()->toggleFeatureOverride($tenant, 'mixes.manage');
        $this->assertSame(['audit.logs'], $afterOff, 'The legacy alias is removed together with the canonical key.');
        $this->assertFalse($this->service()->isFeatureEnabled($tenant->fresh() ?? $tenant, 'mixes.manage'));

        $afterOn = $this->service()->toggleFeatureOverride($tenant->fresh() ?? $tenant, 'blender.access');
        $this->assertSame(['audit.logs', 'mixes.manage'], $afterOn);
        $this->assertSame(['audit.logs', 'mixes.manage'], ($tenant->fresh() ?? $tenant)->enabled_features);
        $this->assertTrue($this->service()->isFeatureEnabled($tenant->fresh() ?? $tenant, 'mixes.manage'));
    }

    public function test_resolve_all_features_lists_plan_keys_that_are_off(): void
    {
        $plan = $this->plan(['features' => ['pos.access' => true, 'reports.advanced' => false, 'blender.access' => false]]);
        $tenant = $this->createTenant(['plan_id' => $plan->id, 'enabled_features' => ['audit.logs']]);

        $this->assertSame([
            'audit.logs' => true,
            'mixes.manage' => false,
            'pos.access' => true,
            'reports.advanced' => false,
        ], $this->service()->resolveAllFeatures($tenant));
    }

    public function test_pennant_is_an_api_layer_on_top_of_the_service(): void
    {
        $plan = $this->plan(['features' => ['reports.advanced' => true]]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);

        $this->assertSame('array', config('pennant.default'));
        $this->assertTrue(Feature::for($tenant)->active('reports.advanced'));
        $this->assertFalse(Feature::for($tenant)->active('api.access'));
        $this->assertFalse(Feature::active('reports.advanced'), 'Outside tenancy the scope is null: default deny.');

        $inside = $this->inTenant($tenant, fn (): array => [
            'reports.advanced' => Feature::active('reports.advanced'),
            'api.access' => Feature::active('api.access'),
        ]);
        $this->assertSame(['reports.advanced' => true, 'api.access' => false], $inside, 'Inside tenancy the scope is the current tenant.');

        $mixes = $this->createTenant(['plan_id' => $this->plan(['features' => ['mixes.manage' => true]])->id]);
        $this->assertTrue(Feature::for($mixes)->active('blender.access'), 'The legacy alias is defined in Pennant too.');
    }

    public function test_pennant_feature_list_mirrors_the_feature_catalog(): void
    {
        $this->assertSame(FeatureCatalog::keys(), config('entitlements.features'));

        foreach (FeatureCatalog::keys() as $key) {
            $this->assertContains($key, Feature::defined(), "Feature [{$key}] must be defined in Pennant.");
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function plan(array $attributes = []): Plan
    {
        $slug = 'ent-'.Str::lower(Str::random(10));

        return Plan::query()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'features' => [],
            'max_users' => 3,
            'max_stores' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    private function subscription(Tenant $tenant, Plan $plan, SubscriptionStatus $status = SubscriptionStatus::Active, mixed $endsAt = null): Subscription
    {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Monthly,
            'status' => $status,
            'amount' => '449.000',
            'starts_at' => now()->subMonth(),
            'ends_at' => $endsAt ?? now()->addMonth(),
        ]);
    }

    /**
     * @param  array<string, int>|null  $limits
     */
    private function addon(string $key, ?string $featureKey = null, ?array $limits = null): Addon
    {
        return Addon::query()->firstOrCreate(['key' => $key], [
            'name_key' => 'plans.addons.test.name',
            'feature_key' => $featureKey,
            'bundled_limits' => $limits,
            'unit_price' => '79.000',
        ]);
    }

    private function addonLine(
        Subscription $subscription,
        Addon $addon,
        int $quantity = 1,
        SubscriptionAddonStatus $status = SubscriptionAddonStatus::Active,
    ): SubscriptionAddon {
        return SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->id,
            'addon_id' => $addon->id,
            'quantity' => $quantity,
            'unit_price' => '79.000',
            'status' => $status,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);
    }

    private function service(): TenantEntitlementService
    {
        return app(TenantEntitlementService::class);
    }
}
