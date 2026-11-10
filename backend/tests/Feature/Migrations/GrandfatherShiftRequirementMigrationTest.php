<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Models\Store;
use App\Models\StorePosSetting;
use App\Services\Pos\SaleShiftGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * POS-10 follow-up: existing branches keep selling without a shift; branches created afterwards
 * require one (the config default).
 */
final class GrandfatherShiftRequirementMigrationTest extends TenantTestCase
{
    private function runUp(): void
    {
        $migration = require database_path('migrations/tenant/2026_11_13_100000_grandfather_existing_stores_without_shift_requirement.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $migration->up();
    }

    public function test_existing_stores_are_switched_off_and_new_ones_require_a_shift(): void
    {
        config(['pos.require_shift_by_default' => true]);
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $main = Store::query()->where('is_main', true)->firstOrFail();
            $withRow = Store::create(['name' => 'B2', 'code' => 'GF-2', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);
            $other = Store::create(['name' => 'B3', 'code' => 'GF-3', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);
            StorePosSetting::create(['store_id' => $withRow->id, 'max_discount_percent' => '5.000', 'require_shift_for_sales' => true]);
            StorePosSetting::create(['store_id' => $other->id, 'require_shift_for_sales' => false]);

            $this->runUp();
            $this->runUp();

            $guard = app(SaleShiftGuard::class);
            foreach ([$main, $withRow, $other] as $store) {
                $this->assertFalse($guard->isRequired($store->id), "{$store->code} must not require a shift");
                $this->assertSame(1, DB::table('store_pos_settings')->where('store_id', $store->id)->count());
            }
            $this->assertSame('5.000', (string) StorePosSetting::query()->where('store_id', $withRow->id)->value('max_discount_percent'), 'Other columns are untouched.');

            $fresh = Store::create(['name' => 'New', 'code' => 'GF-NEW', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);
            $this->assertTrue($guard->isRequired($fresh->id), 'A branch created after the migration requires a shift.');
        });
    }
}
