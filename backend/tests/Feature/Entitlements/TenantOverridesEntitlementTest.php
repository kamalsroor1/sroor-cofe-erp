<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Actions\Billing\GetTenantSubscriptionAction;
use App\Enums\LimitResource;
use App\Http\Resources\TenantResource;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Models\TenantLimitOverride;
use App\Services\Entitlements\TenantEntitlementService;
use Database\Seeders\Catalog\FeatureCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * PLAN-12: per-shop overrides in the entitlement resolution (plan -> add-ons -> overrides last),
 * enforcement through the real endpoints, expiry, cache and the legacy-list migration.
 */
final class TenantOverridesEntitlementTest extends TenantTestCase
{
    private function plan(array $features = [], ?int $maxCustomers = 2): Plan
    {
        $slug = 'ovr-'.Str::lower(Str::random(8));

        return Plan::query()->create([
            'name' => $slug, 'slug' => $slug, 'price_monthly' => '449.000', 'price_yearly' => '4490.000',
            'features' => $features + array_fill_keys(FeatureCatalog::keys(), true),
            'max_users' => null, 'max_stores' => null, 'max_warehouses' => null, 'max_vans' => null, 'max_items' => null,
            'max_customers' => $maxCustomers, 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function shop(?Plan $plan = null): Tenant
    {
        return $this->createTenant(['plan_id' => ($plan ?? $this->plan())->id, 'status' => 'active']);
    }

    private function service(): TenantEntitlementService
    {
        return app(TenantEntitlementService::class);
    }

    private function limitOverride(Tenant $tenant, ?int $value, array $extra = []): TenantLimitOverride
    {
        return TenantLimitOverride::query()->create([
            'tenant_id' => $tenant->id, 'resource' => 'customers', 'value' => $value, 'reason' => 'سبب',
        ] + $extra);
    }

    private function seedCustomers(Tenant $tenant, int $count): void
    {
        $this->inTenant($tenant, function () use ($count): void {
            for ($i = 1; $i <= $count; $i++) {
                Customer::query()->create(['name' => 'عميل '.$i, 'phone' => sprintf('0101%07d', $i), 'current_balance' => '0.000', 'is_active' => true]);
            }
        });
    }

    public function test_a_raised_override_allows_creation_beyond_the_plan_limit(): void
    {
        $tenant = $this->shop();
        $headers = $this->tenantHeaders($tenant);
        $this->seedCustomers($tenant, 2);

        $this->postJson('/api/v1/customers', ['name' => 'فوق الحد'], $headers)
            ->assertStatus(403)->assertJsonPath('error_code', 'subscription.limit_reached');

        $this->limitOverride($tenant, 5);

        $this->postJson('/api/v1/customers', ['name' => 'فوق الحد'], $headers)->assertCreated();
    }

    public function test_a_lowered_override_blocks_below_the_plan_limit(): void
    {
        $tenant = $this->shop($this->plan(maxCustomers: 100));
        $headers = $this->tenantHeaders($tenant);
        $this->seedCustomers($tenant, 2);
        $this->postJson('/api/v1/customers', ['name' => 'مسموح'], $headers)->assertCreated();

        $this->limitOverride($tenant, 3);

        $this->postJson('/api/v1/customers', ['name' => 'ممنوع'], $headers)
            ->assertStatus(403)->assertJsonPath('details.max', 3);
    }

    public function test_a_null_override_is_unlimited_for_that_shop_only(): void
    {
        $unlimited = $this->shop();
        $other = $this->shop();
        $this->limitOverride($unlimited, null);
        $this->seedCustomers($unlimited, 3);
        $this->seedCustomers($other, 2);

        $this->postJson('/api/v1/customers', ['name' => 'بلا حد'], $this->tenantHeaders($unlimited))->assertCreated();
        $this->postJson('/api/v1/customers', ['name' => 'محدود'], $this->tenantHeaders($other))->assertStatus(403);
        $this->assertNull($this->service()->limit($unlimited, LimitResource::Customers));
        $this->assertSame(2, $this->service()->limit($other, LimitResource::Customers));
    }

    public function test_an_expired_override_is_ignored(): void
    {
        $tenant = $this->shop();
        $this->limitOverride($tenant, 50, ['expires_at' => now()->subMinute()]);
        TenantFeatureOverride::query()->create([
            'tenant_id' => $tenant->id, 'feature_key' => 'pos.access', 'mode' => 'revoke', 'reason' => 'سبب', 'expires_at' => now()->subMinute(),
        ]);

        $this->assertSame(2, $this->service()->limit($tenant, LimitResource::Customers));
        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'pos.access'));
    }

    public function test_a_cached_entry_never_outlives_the_nearest_expiry(): void
    {
        $tenant = $this->shop();
        $this->limitOverride($tenant, 50, ['expires_at' => now()->addMinutes(5)]);

        $this->assertSame(50, $this->service()->limit($tenant, LimitResource::Customers));

        $this->travel(6)->minutes();

        $this->assertSame(2, $this->service()->limit($tenant, LimitResource::Customers), 'The cached value expired with the override.');
    }

    public function test_a_just_expired_override_is_never_served_from_cache(): void
    {
        $this->travelTo(now()->startOfSecond());
        $tenant = $this->shop();
        $this->limitOverride($tenant, 50, ['expires_at' => now()->addSeconds(90)]);

        $this->assertSame(50, $this->service()->limit($tenant, LimitResource::Customers));

        $this->travelTo(now()->addSeconds(89));
        $this->assertSame(50, $this->service()->limit($tenant, LimitResource::Customers), 'Still active one second before expiry.');

        // Fresh entry written 1s before expiry must live at most 1s.
        $this->travelTo(now()->addSeconds(1));
        $this->assertSame(2, $this->service()->limit($tenant, LimitResource::Customers), 'At the expiry instant the override is gone.');
    }

    public function test_the_resource_lists_active_grants_and_legacy_entries_as_enabled_features(): void
    {
        $tenant = $this->shop($this->plan(['audit.logs' => false]));
        TenantFeatureOverride::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'audit.logs', 'mode' => 'grant', 'reason' => 'سبب']);
        TenantFeatureOverride::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'reports.advanced', 'mode' => 'grant', 'reason' => 'سبب', 'expires_at' => now()->subMinute()]);
        TenantFeatureOverride::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'pos.access', 'mode' => 'revoke', 'reason' => 'سبب']);
        DB::connection($this->centralConnectionName())->table('tenants')->where('id', $tenant->id)
            ->update(['enabled_features' => json_encode(['blender.access'])]);

        $data = (new TenantResource(Tenant::query()->findOrFail($tenant->id)))->resolve(request());

        $this->assertSame(['audit.logs', 'mixes.manage'], $data['enabled_features']);
    }

    public function test_every_override_change_clears_the_entitlement_cache(): void
    {
        $tenant = $this->shop();
        $this->assertSame(2, $this->service()->limit($tenant, LimitResource::Customers));
        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'pos.access'));

        $limit = $this->limitOverride($tenant, 7);
        $feature = TenantFeatureOverride::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'pos.access', 'mode' => 'revoke', 'reason' => 'سبب']);
        $this->assertSame(7, $this->service()->limit($tenant, LimitResource::Customers));
        $this->assertFalse($this->service()->isFeatureEnabled($tenant, 'pos.access'));

        $limit->update(['value' => 9]);
        $this->assertSame(9, $this->service()->limit($tenant, LimitResource::Customers));

        $limit->delete();
        $feature->delete();
        $this->assertSame(2, $this->service()->limit($tenant, LimitResource::Customers));
        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'pos.access'));
    }

    public function test_a_revoke_beats_the_plan_and_a_grant_goes_beyond_it(): void
    {
        $tenant = $this->shop($this->plan(['transfers.manage' => true, 'audit.logs' => false]));
        $headers = $this->tenantHeaders($tenant);

        $this->getJson('/api/v1/transfers', $headers)->assertOk();

        TenantFeatureOverride::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'transfers.manage', 'mode' => 'revoke', 'reason' => 'سبب']);
        TenantFeatureOverride::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'audit.logs', 'mode' => 'grant', 'reason' => 'سبب']);

        $this->getJson('/api/v1/transfers', $headers)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.feature_unavailable')
            ->assertJsonPath('details.feature', 'transfers.manage');
        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'audit.logs'));
    }

    public function test_a_revoke_beats_an_alias_in_the_legacy_list_too(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan()->id, 'enabled_features' => ['blender.access']]);
        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'mixes.manage'));

        TenantFeatureOverride::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'mixes.manage', 'mode' => 'revoke', 'reason' => 'سبب']);

        $this->assertFalse($this->service()->isFeatureEnabled($tenant, 'mixes.manage'));
    }

    public function test_usage_rows_use_the_effective_limit_and_flag_the_override(): void
    {
        $tenant = $this->shop();
        $expires = now()->addDays(3)->startOfSecond();
        $this->limitOverride($tenant, 10, ['expires_at' => $expires]);
        $this->seedCustomers($tenant, 4);

        $usage = collect($this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->assertOk()->json('data.usage'))->keyBy('resource');

        $this->assertSame(10, $usage['customers']['limit']);
        $this->assertSame(40, $usage['customers']['percent']);
        $this->assertTrue($usage['customers']['overridden']);
        $this->assertSame($expires->toIso8601String(), $usage['customers']['override_expires_at']);

        $direct = $this->inTenant($tenant, fn (): array => app(GetTenantSubscriptionAction::class)->execute($tenant)['usage']);
        $this->assertSame(10, collect($direct)->firstWhere('resource', 'customers')['limit']);
    }

    public function test_the_toggle_writes_a_grant_row_and_removes_it_on_the_second_call(): void
    {
        $tenant = $this->shop($this->plan(['audit.logs' => false]));

        $this->assertSame(['audit.logs'], $this->service()->toggleFeatureOverride($tenant, 'audit.logs'));
        $this->assertTrue($this->service()->isFeatureEnabled($tenant, 'audit.logs'));

        $this->assertSame([], $this->service()->toggleFeatureOverride($tenant, 'audit.logs'));
        $this->assertFalse($this->service()->isFeatureEnabled($tenant, 'audit.logs'));
    }

    public function test_the_migration_moves_legacy_grants_into_rows_without_losing_them(): void
    {
        $plan = $this->plan(['audit.logs' => false, 'mixes.manage' => false]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $empty = $this->createTenant(['plan_id' => $plan->id]);
        DB::connection($this->centralConnectionName())->table('tenants')->where('id', $tenant->id)
            ->update(['enabled_features' => json_encode(['audit.logs', 'blender.access'])]);
        $this->assertTrue($this->service()->resolve($tenant->id)->hasFeature('audit.logs'));

        $migration = require database_path('migrations/2026_11_19_100200_migrate_legacy_feature_overrides_to_grants.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $migration->up();
        $migration->up(); // idempotent
        $this->assertSame(['legacy_migration'], TenantFeatureOverride::query()->where('tenant_id', $tenant->id)->pluck('reason')->unique()->values()->all());

        $rows = TenantFeatureOverride::query()->where('tenant_id', $tenant->id)->orderBy('feature_key')->get();
        $this->assertSame(['audit.logs', 'mixes.manage'], $rows->pluck('feature_key')->all(), 'Aliases are stored canonical.');
        $this->assertSame(['grant', 'grant'], $rows->map(fn ($r) => $r->mode->value)->all());
        $this->assertSame([], Tenant::query()->findOrFail($tenant->id)->enabled_features);
        $this->assertSame(0, TenantFeatureOverride::query()->where('tenant_id', $empty->id)->count());

        $features = $this->service()->resolve($tenant->id)->features;
        $this->assertContains('audit.logs', $features);
        $this->assertContains('mixes.manage', $features);

        // down() finds the rows by the constant marker, never by translated text: a manual row survives.
        TenantFeatureOverride::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'pos.access', 'mode' => 'grant', 'reason' => 'يدوي']);
        $migration->down();
        $this->assertSame(['pos.access'], TenantFeatureOverride::query()->where('tenant_id', $tenant->id)->pluck('feature_key')->all());
        $this->assertSame(['audit.logs', 'mixes.manage'], Tenant::query()->findOrFail($tenant->id)->enabled_features);
    }
}
