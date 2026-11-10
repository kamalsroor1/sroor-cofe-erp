<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use Database\Seeders\Catalog\FeatureCatalog;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * FE-03: GET /system/context exposes the tenant's effective entitlements so the SPA can
 * hide modules the server's `feature:<key>` middleware would refuse.
 */
#[Group('entitlements')]
final class SystemContextEntitlementsApiTest extends TenantTestCase
{
    public function test_unauthenticated_request_is_rejected(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/system/context', $this->tenantGuestHeaders($tenant))->assertStatus(401);
    }

    public function test_enforced_tenant_only_gets_the_features_of_its_plan(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan([
            'pos.access' => true, 'reports.basic' => true, 'transfers.manage' => false,
        ], maxUsers: 5)->id]);

        $data = $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.entitlements.enforced', true)
            ->json('data.entitlements');

        $this->assertSame(['pos.access', 'reports.basic'], $data['features']);
        $this->assertNotContains('transfers.manage', $data['features']);
        $this->assertSame(5, $data['limits']['users']);
        $this->assertNull($data['limits']['stores']);
    }

    public function test_override_alias_and_active_addon_are_listed_with_canonical_keys(): void
    {
        $plan = $this->plan(['pos.access' => true]);
        $tenant = $this->createTenant(['plan_id' => $plan->id, 'enabled_features' => ['blender.access']]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Active,
            'amount' => '449.000',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);
        $addon = Addon::query()->create([
            'key' => 'reports.advanced', 'name_key' => 'plans.addons.test.name',
            'feature_key' => 'reports.advanced', 'bundled_limits' => null, 'unit_price' => '79.000',
        ]);
        SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->id, 'addon_id' => $addon->id, 'quantity' => 1,
            'unit_price' => '79.000', 'status' => SubscriptionAddonStatus::Active,
            'starts_at' => now()->subDay(), 'ends_at' => null,
        ]);

        $features = $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertOk()
            ->json('data.entitlements.features');

        $this->assertSame(['mixes.manage', 'pos.access', 'reports.advanced'], $features);
    }

    public function test_each_tenant_only_sees_its_own_plan_features(): void
    {
        $tenantA = $this->createTenant(['plan_id' => $this->plan(['pos.access' => true])->id]);
        $tenantB = $this->createTenant(['plan_id' => $this->plan(['transfers.manage' => true])->id]);

        $this->assertSame(['pos.access'], $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenantA))
            ->assertOk()->json('data.entitlements.features'));
        $this->assertSame(['transfers.manage'], $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenantB))
            ->assertOk()->json('data.entitlements.features'));
    }

    public function test_planless_legacy_tenant_is_not_enforced_and_gets_the_full_catalog(): void
    {
        $tenant = $this->createTenant();

        $data = $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.entitlements.enforced', false)
            ->json('data.entitlements');

        $this->assertContains('transfers.manage', $data['features']);
        $this->assertContains('mixes.manage', $data['features']);
        $this->assertNotContains('blender.access', $data['features']);
        $this->assertNull($data['limits']['users']);
    }

    /**
     * @param  array<string, bool>  $features
     */
    private function plan(array $features, ?int $maxUsers = null): Plan
    {
        $slug = 'ctx-'.Str::lower(Str::random(10));

        return Plan::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'features' => array_merge(array_fill_keys(FeatureCatalog::keys(), false), $features),
            'max_users' => $maxUsers,
            'max_stores' => null,
            'max_warehouses' => null,
            'max_vans' => null,
            'max_items' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
