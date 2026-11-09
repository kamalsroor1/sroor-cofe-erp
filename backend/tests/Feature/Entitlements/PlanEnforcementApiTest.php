<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Enums\LimitResource;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Entitlements\PlanEnforcer;
use Database\Seeders\Catalog\FeatureCatalog;
use Database\Seeders\Catalog\PlanCatalog;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * SEC-06 / BE-12 / PRD-01: plan features (`feature:<key>` middleware) and plan limits
 * (PlanEnforcer inside the Create* Actions) are enforced on the server.
 */
#[Group('entitlements')]
final class PlanEnforcementApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    // ---------------------------------------------------------------- features

    public function test_a_gated_route_is_403_without_the_feature_and_200_with_it(): void
    {
        $without = $this->tenantOn($this->plan(['features' => ['transfers.manage' => false, 'purchases.manage' => true]]));
        $with = $this->tenantOn($this->plan(['features' => ['transfers.manage' => true]]));

        $this->getJson('/api/v1/transfers', $this->tenantHeaders($without))
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'subscription.feature_unavailable')
            ->assertJsonPath('details.feature', 'transfers.manage')
            ->assertJsonPath('message', __('subscription.errors.feature_unavailable'));

        $this->getJson('/api/v1/transfers', $this->tenantHeaders($with))->assertOk();

        // The plan's own features keep working, and ungated reads are untouched.
        $this->getJson('/api/v1/purchases', $this->tenantHeaders($without))->assertOk();
        $this->getJson('/api/v1/items', $this->tenantHeaders($without))->assertOk();
    }

    public function test_a_super_admin_override_and_the_legacy_alias_grant_the_feature(): void
    {
        $plan = $this->plan(['features' => ['mixes.manage' => false]]);
        $override = $this->tenantOn($plan, ['enabled_features' => ['blender.access']]);
        $none = $this->tenantOn($plan);

        $this->postJson('/api/v1/coffee-blender/calculate', [], $this->tenantHeaders($none))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.feature_unavailable');

        $this->assertNotSame(403, $this->postJson('/api/v1/coffee-blender/calculate', [], $this->tenantHeaders($override))->status());
    }

    public function test_the_authorization_check_still_runs_for_a_user_without_permission(): void
    {
        $tenant = $this->tenantOn($this->plan(['features' => ['purchases.manage' => true]]));
        $user = $this->createTenantUser($tenant);

        $this->getJson('/api/v1/purchases', $this->tenantHeaders($tenant, $user))->assertStatus(403);
        $this->getJson('/api/v1/purchases', $this->tenantGuestHeaders($tenant))->assertStatus(401);
    }

    public function test_a_legacy_tenant_without_a_plan_is_not_enforced(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/transfers', $this->tenantHeaders($tenant))->assertOk();

        config(['entitlements.enforce_planless' => true]);
        $this->getJson('/api/v1/transfers', $this->tenantHeaders($tenant))->assertStatus(403);
    }

    public function test_every_seeded_plan_keeps_every_gated_feature_it_has(): void
    {
        $catalogKeys = FeatureCatalog::keys();
        $gated = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($this->featureKeysOf($route) as $key) {
                $this->assertContains($key, $catalogKeys, "feature:{$key} on [{$route->uri()}] is not a catalog key.");
                $gated[$key][] = $route->uri();
            }
        }

        $this->assertNotEmpty($gated);

        // A CORE feature is on in every seeded plan, so gating it can never lock a plan out.
        foreach (FeatureCatalog::coreKeys() as $core) {
            foreach (PlanCatalog::SLUGS as $slug) {
                $this->assertTrue(PlanCatalog::features($slug)[$core], "{$slug} must include core feature {$core}.");
            }
        }

        // Every non-core gated feature is on in at least one seeded plan (it is sellable).
        $coreKeys = FeatureCatalog::coreKeys();
        foreach (array_diff(array_keys($gated), $coreKeys) as $key) {
            $plansWith = array_filter(PlanCatalog::SLUGS, fn (string $slug): bool => PlanCatalog::features($slug)[$key]);
            $this->assertNotEmpty($plansWith, "Gated feature {$key} is in no seeded plan.");
        }
    }

    public function test_central_routes_are_never_feature_gated(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (Str::startsWith($route->uri(), 'api/v1/super-admin') || Str::startsWith($route->uri(), 'api/v1/central')) {
                $this->assertSame([], $this->featureKeysOf($route), "Central route [{$route->uri()}] must not carry feature:*.");
            }
        }
    }

    public function test_the_super_admin_plans_endpoint_is_unaffected(): void
    {
        $operator = $this->centralOperator();

        $this->getJson('/api/v1/super-admin/plans', $this->centralHeaders($operator))->assertOk();
    }

    // ------------------------------------------------------------------ limits

    public function test_users_are_blocked_at_the_limit_and_allowed_below_it(): void
    {
        // The admin made by the harness is user #1.
        $tenant = $this->tenantOn($this->plan(['max_users' => 2]));
        $headers = $this->tenantHeaders($tenant);

        $this->postJson('/api/v1/users', $this->userPayload('01000000611'), $headers)->assertStatus(201);

        $this->postJson('/api/v1/users', $this->userPayload('01000000612'), $headers)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'users')
            ->assertJsonPath('details.max', 2);

        $this->assertSame(2, $this->inTenant($tenant, fn (): int => User::query()->count()));
    }

    public function test_an_unlimited_plan_never_blocks_user_creation(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_users' => null]));

        $this->postJson('/api/v1/users', $this->userPayload('01000000621'), $this->tenantHeaders($tenant))->assertStatus(201);
    }

    public function test_stores_are_blocked_at_the_limit_and_allowed_below_it(): void
    {
        // The harness main store is store #1.
        $tenant = $this->tenantOn($this->plan(['max_stores' => 2, 'max_warehouses' => 0, 'max_vans' => 1]));
        $headers = $this->tenantHeaders($tenant);

        $this->postJson('/api/v1/stores', ['name' => 'Branch 2', 'type' => 'retail_shop'], $headers)->assertStatus(201);

        $this->postJson('/api/v1/stores', ['name' => 'Branch 3', 'type' => 'retail_shop'], $headers)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'stores')
            ->assertJsonPath('details.max', 2);

        // Warehouses and vans have their own buckets.
        $this->postJson('/api/v1/stores', ['name' => 'WH', 'type' => 'main_warehouse'], $headers)
            ->assertStatus(403)
            ->assertJsonPath('details.limit', 'warehouses')
            ->assertJsonPath('details.max', 0);
        $this->postJson('/api/v1/stores', ['name' => 'Van', 'type' => 'wholesale_van'], $headers)->assertStatus(201);

        $this->assertSame(3, $this->inTenant($tenant, fn (): int => Store::query()->count()));
    }

    public function test_items_are_blocked_at_the_limit(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_items' => 1]));
        $headers = $this->tenantHeaders($tenant);

        $this->postJson('/api/v1/items', $this->itemPayload('A'), $headers)->assertStatus(201);
        $this->postJson('/api/v1/items', $this->itemPayload('B'), $headers)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'items');
    }

    public function test_a_tenant_above_its_limit_after_a_downgrade_can_read_and_update_but_not_create(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_users' => 1, 'max_stores' => 1]));
        $headers = $this->tenantHeaders($tenant);
        $extra = $this->createTenantUser($tenant);
        $this->inTenant($tenant, fn () => Store::query()->create(['name' => 'Second', 'code' => 'SEC-02', 'type' => 'retail_shop', 'is_active' => true]));

        // 2 users / 2 stores against a limit of 1: nothing is deleted or locked.
        $this->getJson('/api/v1/users', $headers)->assertOk();
        $this->getJson('/api/v1/stores', $headers)->assertOk();
        $this->putJson('/api/v1/users/'.$extra->id, ['name' => 'Renamed', 'phone' => $extra->phone, 'role' => 'cashier'], $headers)->assertOk();

        $this->postJson('/api/v1/users', $this->userPayload('01000000631'), $headers)->assertStatus(403)->assertJsonPath('error_code', 'subscription.limit_reached');
        $this->postJson('/api/v1/stores', ['name' => 'Third', 'type' => 'retail_shop'], $headers)->assertStatus(403)->assertJsonPath('error_code', 'subscription.limit_reached');

        $this->assertSame(2, $this->inTenant($tenant, fn (): int => User::query()->count()));
    }

    public function test_a_trial_tenant_gets_the_trial_plans_limits(): void
    {
        $free = collect(PlanCatalog::all())->firstWhere('slug', 'free');
        $plan = $this->plan(array_intersect_key($free, array_flip([
            'max_users', 'max_stores', 'max_warehouses', 'max_vans', 'max_items', 'max_invoices_per_month', 'max_storage_mb', 'features',
        ])));
        $tenant = $this->tenantOn($plan, ['status' => 'trial']);
        $headers = $this->tenantHeaders($tenant);

        // free: 3 users, admin is #1.
        $this->postJson('/api/v1/users', $this->userPayload('01000000641'), $headers)->assertStatus(201);
        $this->postJson('/api/v1/users', $this->userPayload('01000000642'), $headers)->assertStatus(201);
        $this->postJson('/api/v1/users', $this->userPayload('01000000643'), $headers)
            ->assertStatus(403)
            ->assertJsonPath('details.max', 3);
        // free: 1 store, the main store already uses it.
        $this->postJson('/api/v1/stores', ['name' => 'Branch', 'type' => 'retail_shop'], $headers)->assertStatus(403);
    }

    public function test_restoring_a_trashed_store_cannot_bypass_the_stores_limit(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_stores' => 2]));
        $headers = $this->tenantHeaders($tenant);

        $storeId = $this->inTenant($tenant, function (): int {
            $store = Store::query()->create(['name' => 'Trashed', 'code' => 'TRS-01', 'type' => 'retail_shop', 'is_active' => true]);
            $store->delete();

            return (int) $store->id;
        });
        $this->postJson('/api/v1/stores', ['name' => 'Replacement', 'type' => 'retail_shop'], $headers)->assertStatus(201);

        $this->postJson("/api/v1/trash/stores/{$storeId}/restore", [], $headers)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached');
    }

    public function test_changing_a_stores_type_into_a_full_bucket_is_blocked(): void
    {
        // shops: 2 (main + Shop B), vans: 1 (Van A, full).
        $tenant = $this->tenantOn($this->plan(['max_stores' => 2, 'max_vans' => 1, 'max_warehouses' => 0]));
        $headers = $this->tenantHeaders($tenant);
        $this->postJson('/api/v1/stores', ['name' => 'Shop B', 'type' => 'retail_shop'], $headers)->assertStatus(201);
        $this->postJson('/api/v1/stores', ['name' => 'Van A', 'type' => 'wholesale_van'], $headers)->assertStatus(201);
        $shopId = $this->inTenant($tenant, fn (): int => (int) Store::query()->where('name', 'Shop B')->value('id'));

        // Shop -> van (van bucket full) and shop -> warehouse (limit 0): refused.
        $this->putJson("/api/v1/stores/{$shopId}", ['name' => 'Shop B', 'type' => 'wholesale_van'], $headers)
            ->assertStatus(403)->assertJsonPath('details.limit', 'vans');
        $this->putJson("/api/v1/stores/{$shopId}", ['name' => 'Shop B', 'type' => 'main_warehouse'], $headers)
            ->assertStatus(403)->assertJsonPath('details.limit', 'warehouses');
        // Same bucket (a rename / shop -> shop) is always fine.
        $this->putJson("/api/v1/stores/{$shopId}", ['name' => 'Shop B2', 'type' => 'retail_shop'], $headers)->assertOk();
        $this->assertSame('retail_shop', $this->inTenant($tenant, fn (): string => (string) Store::query()->findOrFail($shopId)->type));
    }

    public function test_the_limit_check_has_a_fallback_lock_when_no_store_row_exists(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_items' => 5]));
        $this->inTenant($tenant, function (): void {
            DB::table('stores')->delete();
            app(PlanEnforcer::class)->assertWithinLimit(LimitResource::Items, fn (): int => 0);
            $this->addToAssertionCount(1);
        });
    }

    public function test_a_plan_with_tenants_cannot_be_deleted(): void
    {
        $plan = $this->plan();
        $this->tenantOn($plan);

        try {
            $plan->delete();
            $this->fail('A plan in use must not be deletable.');
        } catch (ValidationException) {
            $this->assertNotNull(Plan::query()->find($plan->id));
        }

        $this->assertTrue($this->plan()->delete());
    }

    public function test_the_planless_command_lists_and_assigns(): void
    {
        $legacy = $this->createTenant();
        $plan = $this->plan();

        $this->artisan('tenants:report-planless')->assertSuccessful();
        $this->assertNull($legacy->fresh()->plan_id, 'Dry run changes nothing.');

        $this->artisan('tenants:report-planless', ['--assign' => 'no-such-plan'])->assertFailed();
        $this->assertNull($legacy->fresh()->plan_id);

        $this->artisan('tenants:report-planless', ['--assign' => $plan->slug])->assertSuccessful();
        $this->assertSame($plan->id, $legacy->fresh()->plan_id);
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $tenantAttributes
     */
    private function tenantOn(Plan $plan, array $tenantAttributes = []): Tenant
    {
        return $this->createTenant(array_merge(['plan_id' => $plan->id], $tenantAttributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function plan(array $attributes = []): Plan
    {
        $slug = 'enf-'.Str::lower(Str::random(10));

        return Plan::query()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'features' => array_fill_keys(FeatureCatalog::keys(), true),
            'max_users' => null,
            'max_stores' => null,
            'max_warehouses' => null,
            'max_vans' => null,
            'max_items' => null,
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    /**
     * @return list<string>
     */
    private function featureKeysOf(LaravelRoute $route): array
    {
        $keys = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'feature:')) {
                array_push($keys, ...explode(',', substr($middleware, 8)));
            }
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(string $phone): array
    {
        return [
            'name' => 'Cashier '.$phone,
            'phone' => $phone,
            'password' => 'secret123',
            'role' => 'cashier',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(string $name): array
    {
        return [
            'name' => 'Item '.$name,
            'unit' => 'كجم',
            'cost_price' => '10.000',
            'selling_price' => '15.000',
        ];
    }
}
