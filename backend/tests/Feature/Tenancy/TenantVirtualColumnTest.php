<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TenantTestCase;

/**
 * Every REAL column of `tenants` is a stancl custom column (Tenant::getCustomColumns()):
 * nothing but true virtual attributes may land in the `data` JSON. Otherwise
 *  - created_at / updated_at are copied into `data` and the stale copy overrides the real
 *    column on hydration (breaks the provisioning "stale" check);
 *  - `settings` (array cast) is re-encoded into `data` on every save/load cycle and grows
 *    into nested escaped JSON, so `$tenant->settings` stops being an array.
 */
final class TenantVirtualColumnTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            TenantCreated::class,
            CreatingDatabase::class,
            DatabaseCreated::class,
            MigratingDatabase::class,
            DatabaseMigrated::class,
        ]);
    }

    public function test_real_columns_never_leak_into_data_across_saves(): void
    {
        Carbon::setTestNow('2026-10-01 10:00:00');
        Tenant::create([
            'id' => 'vc-shop',
            'name' => 'VC Shop',
            'slug' => 'vc-shop',
            'email' => 'vc@shop.test',
            'status' => 'active',
            'settings' => ['currency' => 'EGP', 'theme_preference' => 'dark'],
            'tenancy_db_name' => 'tenant_vc_shop',
        ]);

        foreach (['2026-10-02 10:00:00', '2026-10-03 10:00:00', '2026-10-04 10:00:00'] as $i => $at) {
            Carbon::setTestNow($at);
            $tenant = Tenant::query()->findOrFail('vc-shop');
            $tenant->update(['name' => 'VC Shop '.$i]);
        }
        Carbon::setTestNow();

        $row = DB::table('tenants')->where('id', 'vc-shop')->first();
        $data = json_decode((string) $row->data, true);

        $this->assertIsArray($data);
        foreach (Tenant::getCustomColumns() as $column) {
            $this->assertArrayNotHasKey($column, $data, "real column [{$column}] leaked into tenants.data");
        }
        $this->assertSame(['tenancy_db_name'], array_keys($data), 'only virtual attributes stay in data');

        $this->assertSame(['currency' => 'EGP', 'theme_preference' => 'dark'], json_decode((string) $row->settings, true));

        $fresh = Tenant::query()->findOrFail('vc-shop');
        $this->assertSame(['currency' => 'EGP', 'theme_preference' => 'dark'], $fresh->settings);
        $this->assertSame('tenant_vc_shop', $fresh->tenancy_db_name);
        $this->assertSame('2026-10-04 10:00:00', $fresh->updated_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-01 10:00:00', $fresh->created_at?->format('Y-m-d H:i:s'));
    }

    public function test_updated_at_on_the_model_reflects_the_real_column(): void
    {
        Tenant::create([
            'id' => 'vc-stale',
            'name' => 'VC Stale',
            'slug' => 'vc-stale',
            'email' => 'stale@shop.test',
            'status' => 'active',
        ]);
        Tenant::query()->findOrFail('vc-stale')->update(['name' => 'VC Stale 2']);

        DB::table('tenants')->where('id', 'vc-stale')->update(['updated_at' => '2020-01-01 00:00:00']);

        $this->assertSame('2020-01-01 00:00:00', Tenant::query()->findOrFail('vc-stale')->updated_at?->format('Y-m-d H:i:s'));
    }

    public function test_cleanup_migration_moves_leaked_copies_out_of_data_idempotently(): void
    {
        $settings = ['currency' => 'EGP', 'theme_preference' => 'dark'];
        $nested = (string) json_encode($settings);
        for ($i = 0; $i < 4; $i++) {
            $nested = (string) json_encode($nested); // one more encoding per historical save
        }

        DB::table('tenants')->insert([
            'id' => 'vc-legacy',
            'name' => 'Legacy',
            'slug' => 'vc-legacy',
            'email' => 'legacy@shop.test',
            'status' => 'active',
            'settings' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-05-05 05:05:05',
            'data' => json_encode([
                'logo' => null,
                'address' => '1 Nile St',
                'settings' => json_decode($nested),
                'created_at' => '2025-12-31 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
                'tenancy_db_name' => 'tenant_legacy',
            ]),
        ]);
        DB::table('tenants')->insert([
            'id' => 'vc-column-wins',
            'name' => 'Column wins',
            'slug' => 'vc-column-wins',
            'email' => 'wins@shop.test',
            'status' => 'active',
            'settings' => json_encode(['currency' => 'USD']),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-05-05 05:05:05',
            'data' => json_encode(['settings' => json_encode(['currency' => 'EGP']), 'updated_at' => '2026-01-01 00:00:00']),
        ]);

        $migration = require database_path('migrations/2026_10_10_500100_clean_tenant_data_virtual_column_duplicates.php');
        $migration->up();
        $migration->up(); // idempotent

        $legacy = DB::table('tenants')->where('id', 'vc-legacy')->first();
        $this->assertSame(['tenancy_db_name' => 'tenant_legacy'], json_decode((string) $legacy->data, true));
        $this->assertSame($settings, json_decode((string) $legacy->settings, true));
        $this->assertSame('1 Nile St', $legacy->address);
        $this->assertSame('2026-05-05 05:05:05', (string) $legacy->updated_at);
        $this->assertSame('2026-01-01 00:00:00', (string) $legacy->created_at);

        $model = Tenant::query()->findOrFail('vc-legacy');
        $this->assertSame($settings, $model->settings);
        $this->assertSame('2026-05-05 05:05:05', $model->updated_at?->format('Y-m-d H:i:s'));

        $wins = DB::table('tenants')->where('id', 'vc-column-wins')->first();
        $this->assertNull($wins->data);
        $this->assertSame(['currency' => 'USD'], json_decode((string) $wins->settings, true));
    }
}
