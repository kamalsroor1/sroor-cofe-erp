<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Enums\ShiftMode;
use App\Models\Store;
use App\Models\StorePosSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * SHIFT-01 migrations: cash_shifts.open_store_guard -> open_guard (+ shift_mode) with a working
 * down(), and store_pos_settings.shift_mode where a branch with an open shift keeps 'shared'.
 */
final class ShiftModeMigrationsTest extends TenantTestCase
{
    private const GUARD = 'migrations/tenant/2026_11_21_100100_replace_open_store_guard_with_open_guard_on_cash_shifts_table.php';

    private const MODE = 'migrations/tenant/2026_11_21_100000_add_shift_mode_to_store_pos_settings_table.php';

    private function migration(string $path): Migration
    {
        $migration = require database_path($path);
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    public function test_the_guard_migration_round_trips_and_backfills_open_shifts(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $guard = $this->migration(self::GUARD);
            $guard->down();
            $this->assertTrue(Schema::hasColumn('cash_shifts', 'open_store_guard'));
            $this->assertFalse(Schema::hasColumn('cash_shifts', 'open_guard'));
            $this->assertFalse(Schema::hasColumn('cash_shifts', 'shift_mode'));

            $storeId = (int) Store::query()->where('is_main', true)->value('id');
            $row = ['shift_number' => 'S', 'opening_cash_balance' => '0.000', 'store_id' => $storeId, 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()];
            $open = DB::table('cash_shifts')->insertGetId(array_merge($row, ['shift_number' => 'S-OPEN', 'status' => 'open', 'open_store_guard' => $storeId]));
            $closed = DB::table('cash_shifts')->insertGetId(array_merge($row, ['shift_number' => 'S-CLOSED', 'status' => 'closed']));

            $guard->up();
            $guard->up(); // idempotent

            $this->assertFalse(Schema::hasColumn('cash_shifts', 'open_store_guard'));
            $this->assertSame('store:'.$storeId, DB::table('cash_shifts')->where('id', $open)->value('open_guard'));
            $this->assertNull(DB::table('cash_shifts')->where('id', $closed)->value('open_guard'));
            $this->assertSame('shared', DB::table('cash_shifts')->where('id', $open)->value('shift_mode'));
        });
    }

    public function test_a_branch_with_an_open_shift_keeps_shared_and_the_others_get_the_default(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $mode = $this->migration(self::MODE);
            $mode->down();
            $this->assertFalse(Schema::hasColumn('store_pos_settings', 'shift_mode'));

            $busy = (int) Store::query()->where('is_main', true)->value('id');
            $withRow = (int) Store::create(['name' => 'B2', 'code' => 'SM-M2', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            $idle = (int) Store::create(['name' => 'B3', 'code' => 'SM-M3', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            DB::table('store_pos_settings')->insert(['store_id' => $withRow, 'require_shift_for_sales' => false, 'max_discount_percent' => '7.000', 'created_at' => now(), 'updated_at' => now()]);
            foreach ([$busy, $withRow] as $store) {
                DB::table('cash_shifts')->insert([
                    'shift_number' => 'S-'.$store, 'status' => 'open', 'store_id' => $store, 'opening_cash_balance' => '0.000',
                    'opened_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $mode->up();
            $mode->up(); // idempotent

            $this->assertSame(ShiftMode::Shared, StorePosSetting::forStore($busy)->shiftMode());
            $this->assertSame(ShiftMode::Shared, StorePosSetting::forStore($withRow)->shiftMode());
            $this->assertSame('7.000', (string) StorePosSetting::forStore($withRow)->max_discount_percent, 'Other columns are untouched.');
            $this->assertFalse((bool) StorePosSetting::forStore($withRow)->require_shift_for_sales);
            $this->assertSame(ShiftMode::PerCashier, StorePosSetting::forStore($idle)->shiftMode());
            $this->assertSame(0, DB::table('store_pos_settings')->where('store_id', $idle)->count(), 'No row is created for an idle branch.');
        });
    }
}
