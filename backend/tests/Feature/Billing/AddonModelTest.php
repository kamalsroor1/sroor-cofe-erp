<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\AddonType;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\PlanAddon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * ENTI-1.4: central `addons` catalog + `plan_addon` availability pivot.
 *
 * Money is DECIMAL(12,3) strings; tier prices are explicit stored values (no percentage
 * maths); Addon / PlanAddon are pinned to the central connection.
 */
#[Group('billing')]
#[Group('mysql')]
final class AddonModelTest extends TenantTestCase
{
    private const ADDONS_MIGRATION = 'migrations/2026_10_10_200300_create_addons_table.php';

    private const PLAN_ADDON_MIGRATION = 'migrations/2026_10_10_200310_create_plan_addon_table.php';

    public function test_addons_and_plan_addon_tables_have_the_designed_columns(): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        foreach ([
            'id', 'key', 'name_key', 'type', 'feature_key', 'bundled_limits',
            'unit_price', 'yearly_price', 'price_tiers', 'is_active', 'is_public',
            'sort_order', 'created_at', 'updated_at',
        ] as $column) {
            $this->assertTrue($schema->hasColumn('addons', $column), "addons.{$column} is missing.");
        }

        foreach ([
            'id', 'plan_id', 'addon_id', 'is_available', 'included_quantity',
            'unit_price_override', 'yearly_price_override', 'created_at', 'updated_at',
        ] as $column) {
            $this->assertTrue($schema->hasColumn('plan_addon', $column), "plan_addon.{$column} is missing.");
        }

        $addons = collect($schema->getColumns('addons'))->keyBy('name');
        $this->assertFalse((bool) $addons['unit_price']['nullable']);
        $this->assertTrue((bool) $addons['yearly_price']['nullable'], 'NULL yearly_price = not sold yearly.');
        $this->assertTrue((bool) $addons['price_tiers']['nullable']);

        if (in_array($schema->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->assertSame('decimal(12,3)', strtolower((string) $addons['unit_price']['type']));
            $this->assertSame('decimal(12,3)', strtolower((string) $addons['yearly_price']['type']));
            $pivot = collect($schema->getColumns('plan_addon'))->keyBy('name');
            $this->assertSame('decimal(12,3)', strtolower((string) $pivot['unit_price_override']['type']));
            $this->assertSame('decimal(12,3)', strtolower((string) $pivot['yearly_price_override']['type']));
        }
    }

    public function test_defaults_and_casts(): void
    {
        $addon = Addon::query()->create([
            'key' => 'addon.warehouse',
            'name_key' => 'plans.addons.warehouse',
            'unit_price' => '149',
        ]);
        $addon = Addon::query()->findOrFail($addon->id);

        $this->assertSame(AddonType::Recurring, $addon->type);
        $this->assertSame('149.000', $addon->unit_price);
        $this->assertNull($addon->yearly_price);
        $this->assertNull($addon->price_tiers);
        $this->assertNull($addon->bundled_limits);
        $this->assertNull($addon->feature_key);
        $this->assertTrue($addon->is_active);
        $this->assertTrue($addon->is_public);
        $this->assertSame(0, $addon->sort_order);
        $this->assertTrue($addon->isRecurring());
        $this->assertFalse($addon->isService());
    }

    public function test_tiers_and_bundled_limits_round_trip_as_stored(): void
    {
        $addon = Addon::query()->create([
            'key' => 'addon.store',
            'name_key' => 'plans.addons.store',
            'type' => AddonType::Recurring,
            'bundled_limits' => ['stores' => 1, 'users' => 1],
            'unit_price' => '249.000',
            'yearly_price' => '2490.000',
            'price_tiers' => [
                ['min_qty' => 3, 'unit_price' => '212.000', 'yearly_price' => '2120.000'],
                ['min_qty' => 6, 'unit_price' => '187.000', 'yearly_price' => '1870.000'],
            ],
        ]);
        $addon = Addon::query()->findOrFail($addon->id);

        $this->assertSame(['stores' => 1, 'users' => 1], $addon->bundled_limits);
        $this->assertSame('212.000', $addon->price_tiers[0]['unit_price'] ?? null);
        $this->assertSame('1870.000', $addon->price_tiers[1]['yearly_price'] ?? null);
        $this->assertSame('2490.000', $addon->yearly_price);
    }

    public function test_service_type_is_persisted(): void
    {
        $addon = Addon::query()->create([
            'key' => 'service.onboarding',
            'name_key' => 'plans.addons.onboarding',
            'type' => AddonType::Service,
            'unit_price' => '1500.000',
        ]);

        $this->assertSame('service', DB::table('addons')->where('id', $addon->id)->value('type'));
        $this->assertTrue(Addon::query()->findOrFail($addon->id)->isService());
    }

    public function test_key_is_unique(): void
    {
        Addon::query()->create(['key' => 'addon.user', 'name_key' => 'plans.addons.user', 'unit_price' => '79']);

        $this->expectException(QueryException::class);
        Addon::query()->create(['key' => 'addon.user', 'name_key' => 'plans.addons.user', 'unit_price' => '79']);
    }

    public function test_scopes_filter_inactive_and_hidden_addons(): void
    {
        Addon::query()->create(['key' => 'a.visible', 'name_key' => 'k', 'unit_price' => '1', 'sort_order' => 2]);
        Addon::query()->create(['key' => 'a.first', 'name_key' => 'k', 'unit_price' => '1', 'sort_order' => 1]);
        Addon::query()->create(['key' => 'custom.domain', 'name_key' => 'k', 'unit_price' => '149', 'is_active' => false, 'is_public' => false]);
        Addon::query()->create(['key' => 'a.hidden', 'name_key' => 'k', 'unit_price' => '1', 'is_public' => false, 'sort_order' => 3]);

        $this->assertSame(['a.first', 'a.visible', 'a.hidden'], Addon::query()->active()->pluck('key')->all());
        $this->assertSame(['a.first', 'a.visible'], Addon::query()->active()->visible()->pluck('key')->all());
    }

    public function test_plan_addon_pivot_links_plans_and_addons(): void
    {
        $plan = $this->plan('basic');
        $enterprise = $this->plan('enterprise');
        $mixes = Addon::query()->create(['key' => 'mixes.manage', 'name_key' => 'plans.addons.mixes', 'feature_key' => 'mixes.manage', 'unit_price' => '199']);

        $plan->addons()->attach($mixes->id, ['is_available' => true]);
        $enterprise->addons()->attach($mixes->id, ['is_available' => false, 'included_quantity' => 1]);

        $pivot = $plan->addons()->firstOrFail()->pivot;
        $this->assertInstanceOf(PlanAddon::class, $pivot);
        $this->assertTrue($pivot->is_available);
        $this->assertSame(0, $pivot->included_quantity);
        $this->assertNull($pivot->unit_price_override);

        $this->assertSame(['basic', 'enterprise'], $mixes->plans()->orderBy('slug')->pluck('slug')->all());

        $row = PlanAddon::query()->where('plan_id', $enterprise->id)->where('addon_id', $mixes->id)->firstOrFail();
        $this->assertFalse($row->is_available);
        $this->assertSame(1, $row->included_quantity);
        $this->assertSame($mixes->id, $row->addon->id);
        $this->assertSame($enterprise->id, $row->plan->id);
        $this->assertCount(2, $mixes->planAddons);
    }

    public function test_plan_addon_pair_is_unique(): void
    {
        $plan = $this->plan('pro');
        $addon = Addon::query()->create(['key' => 'api.access', 'name_key' => 'k', 'unit_price' => '299']);

        PlanAddon::query()->create(['plan_id' => $plan->id, 'addon_id' => $addon->id]);

        $this->expectException(QueryException::class);
        PlanAddon::query()->create(['plan_id' => $plan->id, 'addon_id' => $addon->id]);
    }

    public function test_models_read_the_central_db_inside_a_tenant(): void
    {
        $plan = $this->plan('central-pinned');
        $addon = Addon::query()->create(['key' => 'addon.van', 'name_key' => 'k', 'unit_price' => '249']);
        PlanAddon::query()->create(['plan_id' => $plan->id, 'addon_id' => $addon->id, 'unit_price_override' => '199']);

        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $central = $this->centralConnectionName();

        $seen = $this->inTenant($tenant, fn (): array => [
            'default' => DB::getDefaultConnection(),
            'addon_connection' => (new Addon)->getConnectionName(),
            'pivot_connection' => (new PlanAddon)->getConnectionName(),
            'addon_key' => Addon::query()->find($addon->id)?->key,
            'override' => PlanAddon::query()->where('addon_id', $addon->id)->value('unit_price_override'),
            'via_plan' => Plan::query()->findOrFail($plan->id)->addons()->pluck('key')->all(),
        ]);

        $this->assertNotSame($central, $seen['default'], 'Tenancy must switch the default connection for this test to mean anything.');
        $this->assertSame($central, $seen['addon_connection']);
        $this->assertSame($central, $seen['pivot_connection']);
        $this->assertSame('addon.van', $seen['addon_key']);
        $this->assertSame(0, bccomp((string) $seen['override'], '199', 3));
        $this->assertSame(['addon.van'], $seen['via_plan']);
    }

    public function test_deleting_a_plan_removes_its_pivot_rows_only(): void
    {
        $plan = $this->plan('to-delete');
        $addon = Addon::query()->create(['key' => 'addon.storage_10gb', 'name_key' => 'k', 'unit_price' => '49']);
        $plan->addons()->attach($addon->id);

        $plan->delete();

        $this->assertSame(0, PlanAddon::query()->count());
        $this->assertTrue(Addon::query()->whereKey($addon->id)->exists());
    }

    public function test_migrations_roll_back_and_reapply(): void
    {
        try {
            $this->runMigration(self::PLAN_ADDON_MIGRATION, 'down');
            $this->runMigration(self::ADDONS_MIGRATION, 'down');

            $this->assertFalse(Schema::hasTable('plan_addon'));
            $this->assertFalse(Schema::hasTable('addons'));

            // Running down() twice is harmless.
            $this->runMigration(self::PLAN_ADDON_MIGRATION, 'down');
            $this->runMigration(self::ADDONS_MIGRATION, 'down');
        } finally {
            $this->runMigration(self::ADDONS_MIGRATION, 'up');
            $this->runMigration(self::PLAN_ADDON_MIGRATION, 'up');
        }

        $this->assertTrue(Schema::hasTable('addons'));
        $this->assertTrue(Schema::hasTable('plan_addon'));

        // up() is idempotent too.
        $this->runMigration(self::ADDONS_MIGRATION, 'up');
        $this->runMigration(self::PLAN_ADDON_MIGRATION, 'up');
        $this->assertTrue(Schema::hasTable('plan_addon'));
    }

    private function plan(string $slug): Plan
    {
        return Plan::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function runMigration(string $path, string $direction): void
    {
        $migration = require database_path($path);
        if (! is_object($migration) || ! method_exists($migration, $direction)) {
            $this->fail("{$path} must return a migration with {$direction}().");
        }

        $migration->{$direction}();
    }
}
