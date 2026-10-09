<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\Subscription;
use Database\Seeders\Catalog\FeatureCatalog;
use Database\Seeders\Catalog\PlanCatalog;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * ENTI-1.8: the one-time data migration 2026_10_10_200610 moves an EXISTING installation
 * to the approved catalog and renames blender.access -> mixes.manage in plan_features,
 * plans.features and tenants.enabled_features. On an empty database it writes nothing.
 * down() reverses the rename only.
 */
#[Group('billing')]
#[Group('mysql')]
final class ApprovedPlanCatalogMigrationTest extends TenantTestCase
{
    private const MIGRATION = 'migrations/2026_10_10_200610_apply_approved_plan_catalog.php';

    /** Legacy seeder prices (before approval) and all-nines "unlimited" sentinels. */
    private const LEGACY_PLANS = [
        'free' => ['0.000', '0.000', 1, 1, 50, 100],
        'basic' => ['299.000', '2990.000', 3, 2, 500, 1000],
        'pro' => ['599.000', '5990.000', 10, 5, 5000, 10000],
        'enterprise' => ['999.000', '9990.000', null, null, null, null],
    ];

    public function test_an_empty_database_is_left_to_the_seeder(): void
    {
        $this->runMigration('up');

        $this->assertSame(0, $this->db()->table('plans')->count());
        $this->assertSame(0, $this->db()->table('plan_features')->count());
        $this->assertSame(0, $this->db()->table('addons')->count());
        $this->assertSame(0, $this->db()->table('plan_addon')->count());
    }

    public function test_it_applies_the_approved_catalog_to_an_existing_installation(): void
    {
        $this->legacyInstallation();
        $subscriptionId = $this->legacySubscription();

        $this->runMigration('up');

        $basic = Plan::query()->where('slug', 'basic')->firstOrFail();
        $this->assertSame('449.000', $basic->price_monthly);
        $this->assertSame('4490.000', $basic->price_yearly);
        $this->assertSame('299.000', $basic->founder_price_monthly);
        $this->assertSame('2990.000', $basic->founder_price_yearly);
        $this->assertSame(3000, $basic->max_items);
        $this->assertSame(1, $basic->max_warehouses);
        $this->assertSame(__('plans.plans.basic.name'), $basic->name);
        $this->assertSame('plans.plans.basic.name', $basic->name_key);

        $enterprise = Plan::query()->where('slug', 'enterprise')->firstOrFail();
        $this->assertSame('1799.000', $enterprise->price_monthly);
        $this->assertSame('9990.000', $enterprise->founder_price_yearly);
        $this->assertSame(25, $enterprise->max_users);
        $this->assertSame(10, $enterprise->max_stores);
        $this->assertNull($enterprise->max_items);

        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
        $this->assertSame('899.000', $pro->price_monthly);
        $expected = PlanCatalog::features('pro') + ['custom.legacy' => true];
        $actual = $pro->features ?? [];
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual, 'Approved values win, unknown keys survive.');
        $this->assertArrayNotHasKey('blender.access', $pro->features ?? []);

        // Feature registry: renamed, approved metadata, no emoji, all 26 keys.
        $features = $this->db()->table('plan_features')->get()->keyBy('key');
        $this->assertSame(FeatureCatalog::keys(), $features->sortBy('sort_order')->keys()->all());
        $this->assertNull($features['mixes.manage']->icon);
        $this->assertSame(__('plans.features.mixes_manage.name'), $features['mixes.manage']->name);
        $this->assertTrue((bool) $features['quotations.manage']->is_core);
        $this->assertFalse((bool) $features['pos.offline']->is_public);

        // Add-ons created with their availability.
        $this->assertSame(13, Addon::query()->count());
        $this->assertSame('2490.000', Addon::query()->where('key', 'addon.store')->value('yearly_price'));
        $this->assertGreaterThan(0, $this->db()->table('plan_addon')->count());

        // The tenant override was renamed in place.
        $this->assertSame(['pos.access', 'mixes.manage'], $this->overrides('legacy-tenant'));

        // A super-admin custom plan: key renamed, never repriced.
        $custom = Plan::query()->where('slug', 'custom-plan')->firstOrFail();
        $this->assertSame('123.000', $custom->price_monthly);
        $this->assertEquals(['pos.access' => true, 'mixes.manage' => false], $custom->features);

        // Existing subscriptions keep their frozen price (Q-E5).
        $this->assertSame('299.000', Subscription::query()->findOrFail($subscriptionId)->amount);
    }

    public function test_up_is_idempotent(): void
    {
        $this->legacyInstallation();

        $this->runMigration('up');
        $first = $this->snapshot();
        $this->runMigration('up');

        $this->assertSame($this->withoutTimestamps($first), $this->withoutTimestamps($this->snapshot()));
    }

    public function test_down_reverses_the_rename_and_keeps_the_approved_prices(): void
    {
        $this->legacyInstallation();
        $this->runMigration('up');

        $this->runMigration('down');

        $this->assertTrue($this->db()->table('plan_features')->where('key', 'blender.access')->exists());
        $this->assertFalse($this->db()->table('plan_features')->where('key', 'mixes.manage')->exists());
        foreach (Plan::query()->get() as $plan) {
            $this->assertArrayNotHasKey('mixes.manage', $plan->features ?? [], "{$plan->slug} still has mixes.manage.");
        }
        $this->assertArrayHasKey('blender.access', Plan::query()->where('slug', 'enterprise')->firstOrFail()->features ?? []);
        $this->assertSame(['pos.access', 'blender.access'], $this->overrides('legacy-tenant'));
        $this->assertSame('449.000', Plan::query()->where('slug', 'basic')->value('price_monthly'), 'A rollback never silently reprices plans.');

        // And up() again restores the approved state.
        $this->runMigration('up');
        $this->assertSame(['pos.access', 'mixes.manage'], $this->overrides('legacy-tenant'));
        $this->assertFalse($this->db()->table('plan_features')->where('key', 'blender.access')->exists());
    }

    public function test_when_both_keys_exist_the_new_key_wins(): void
    {
        $this->legacyInstallation();
        $this->db()->table('plans')->where('slug', 'pro')->update(['features' => json_encode(['blender.access' => false, 'mixes.manage' => true])]);
        $this->db()->table('tenants')->where('id', 'legacy-tenant')->update(['enabled_features' => json_encode(['blender.access', 'mixes.manage'])]);

        $this->runMigration('up');

        $this->assertSame(['mixes.manage'], $this->overrides('legacy-tenant'));
        $this->assertSame(1, $this->db()->table('plan_features')->where('key', 'mixes.manage')->count());
    }

    private function legacyInstallation(): void
    {
        $now = now();

        foreach (self::LEGACY_PLANS as $slug => [$monthly, $yearly, $users, $stores, $items, $invoices]) {
            $this->db()->table('plans')->insert([
                'name' => 'legacy '.$slug, 'slug' => $slug, 'description' => 'legacy',
                'price_monthly' => $monthly, 'price_yearly' => $yearly,
                'max_users' => $users, 'max_stores' => $stores, 'max_items' => $items, 'max_invoices_per_month' => $invoices,
                'features' => json_encode(['pos.access' => true, 'blender.access' => $slug !== 'basic', 'custom.legacy' => true]),
                'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->db()->table('plans')->insert([
            'name' => 'custom', 'slug' => 'custom-plan', 'price_monthly' => '123.000', 'price_yearly' => '1230.000',
            'features' => json_encode(['pos.access' => true, 'blender.access' => false]),
            'is_active' => true, 'sort_order' => 9, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->db()->table('plan_features')->insert([
            'key' => 'blender.access', 'name' => 'legacy blender', 'module' => 'inventory', 'type' => 'boolean',
            'default_value' => 'false', 'icon' => 'x', 'sort_order' => 8, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->db()->table('tenants')->insert([
            'id' => 'legacy-tenant', 'name' => 'Legacy tenant', 'slug' => 'legacy-tenant', 'email' => 'legacy@tenant.test',
            'status' => 'active', 'enabled_features' => json_encode(['pos.access', 'blender.access']),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function legacySubscription(): int
    {
        return (int) Subscription::query()->create([
            'tenant_id' => 'legacy-tenant',
            'plan_id' => Plan::query()->where('slug', 'basic')->value('id'),
            'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Active,
            'amount' => '299.000',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ])->getKey();
    }

    /**
     * @return list<string>
     */
    private function overrides(string $tenantId): array
    {
        $raw = $this->db()->table('tenants')->where('id', $tenantId)->value('enabled_features');

        return json_decode((string) $raw, true);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['plans' => 'slug', 'plan_features' => 'key', 'addons' => 'key', 'plan_addon' => 'id', 'tenants' => 'id'] as $table => $order) {
            $snapshot[$table] = $this->db()->table($table)->orderBy($order)->get()->map(fn (object $row): array => (array) $row)->all();
        }

        return $snapshot;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $snapshot
     * @return array<string, list<array<string, mixed>>>
     */
    private function withoutTimestamps(array $snapshot): array
    {
        return array_map(
            static fn (array $rows): array => array_map(static function (array $row): array {
                unset($row['updated_at']);

                return $row;
            }, $rows),
            $snapshot,
        );
    }

    private function db(): Connection
    {
        return DB::connection($this->centralConnectionName());
    }

    private function runMigration(string $direction): void
    {
        $migration = require database_path(self::MIGRATION);
        if (! is_object($migration) || ! method_exists($migration, $direction)) {
            $this->fail(self::MIGRATION." must return a migration with {$direction}().");
        }

        $migration->{$direction}();
    }
}
