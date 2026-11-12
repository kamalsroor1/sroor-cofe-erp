<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Models\Store;
use App\Models\StorePosSetting;
use Illuminate\Database\Migrations\Migration;
use Tests\TenantTestCase;

/** ROLE-02: existing stores keep the old 10 % cap; new stores get the 5 % default. */
final class PinExistingStoresDiscountCapMigrationTest extends TenantTestCase
{
    public function test_unset_caps_are_pinned_to_ten_and_explicit_caps_untouched(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $unset = Store::create(['name' => 'B2', 'code' => 'DC-2', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);
            $explicit = Store::create(['name' => 'B3', 'code' => 'DC-3', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);
            StorePosSetting::create(['store_id' => $unset->id, 'max_discount_percent' => null]);
            StorePosSetting::create(['store_id' => $explicit->id, 'max_discount_percent' => '3.000']);

            $migration = require database_path('migrations/tenant/2026_11_17_100000_pin_existing_stores_discount_cap_to_legacy_default.php');
            $this->assertInstanceOf(Migration::class, $migration);
            $migration->up();

            $this->assertSame('10.000', StorePosSetting::forStore($unset->id)->effectiveMaxDiscountPercent());
            $this->assertSame('3.000', StorePosSetting::forStore($explicit->id)->effectiveMaxDiscountPercent());

            $fresh = Store::create(['name' => 'New', 'code' => 'DC-NEW', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);
            $this->assertSame('5.000', StorePosSetting::forStore($fresh->id)->effectiveMaxDiscountPercent());
        });
    }
}
