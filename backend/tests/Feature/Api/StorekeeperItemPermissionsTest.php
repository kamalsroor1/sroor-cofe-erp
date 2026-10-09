<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * CTO decision 2026-10-09: the seeded `storekeeper` role must not create or edit items (item
 * create, edit, toggle-active, price and cost changes are admin-only, as before the permission
 * rename). PermissionsSeeder no longer grants items.create / items.edit to it, and tenant
 * migration 2026_10_10_070010 revokes them from existing tenants. transfers.create stays.
 */
final class StorekeeperItemPermissionsTest extends TenantTestCase
{
    private const MIGRATION = 'database/migrations/tenant/2026_10_10_070010_revoke_item_edit_permissions_from_storekeeper.php';

    private function seedItem(Tenant $tenant): int
    {
        return $this->inTenant($tenant, fn (): int => (int) Item::query()->create([
            'code' => 'SK-1',
            'name' => 'صنف أمين المخزن',
            'unit' => 'كجم',
            'cost_price' => '10.000',
            'selling_price' => '15.000',
            'current_stock' => '0.000',
            'is_active' => true,
        ])->id);
    }

    public function test_seeded_storekeeper_role_has_no_item_create_or_edit_but_keeps_transfers(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $role = Role::findByName('storekeeper', 'web');

            $this->assertFalse($role->hasPermissionTo('items.create'));
            $this->assertFalse($role->hasPermissionTo('items.edit'));
            $this->assertTrue($role->hasPermissionTo('items.view'));
            $this->assertTrue($role->hasPermissionTo('transfers.create'));
        });
    }

    public function test_storekeeper_gets_403_on_item_create_update_and_toggle(): void
    {
        $tenant = $this->createTenant();
        $itemId = $this->seedItem($tenant);
        $storekeeper = $this->createTenantUser($tenant, 'storekeeper');
        $headers = $this->tenantHeaders($tenant, $storekeeper);

        $this->postJson('/api/v1/items', [
            'name' => 'صنف جديد',
            'unit' => 'كجم',
            'cost_price' => '5.000',
            'selling_price' => '9.000',
        ], $headers)->assertForbidden();

        $this->putJson('/api/v1/items/'.$itemId, [
            'name' => 'اسم معدل',
            'unit' => 'كجم',
            'cost_price' => '1.000',
            'selling_price' => '2.000',
        ], $headers)->assertForbidden();

        $this->patchJson('/api/v1/items/'.$itemId.'/toggle-active', [], $headers)->assertForbidden();

        $this->inTenant($tenant, function () use ($itemId): void {
            $item = Item::query()->findOrFail($itemId);
            $this->assertSame('صنف أمين المخزن', $item->name);
            $this->assertSame('10.000', (string) $item->cost_price);
            $this->assertSame('15.000', (string) $item->selling_price);
            $this->assertTrue((bool) $item->is_active);
            $this->assertSame(1, Item::query()->count());
        });

        // Reading stays allowed.
        $this->getJson('/api/v1/items/'.$itemId, $headers)->assertOk();
    }

    public function test_migration_revokes_from_storekeeper_only_is_idempotent_and_reversible(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $registrar = app(PermissionRegistrar::class);
            // A tenant provisioned before the decision: storekeeper still holds both.
            Role::findByName('storekeeper', 'web')->givePermissionTo(['items.create', 'items.edit']);
            $custom = Role::findOrCreate('stock-lead', 'web');
            $custom->givePermissionTo('items.edit');
            $registrar->forgetCachedPermissions();

            $this->runMigration('up');
            $this->runMigration('up');
            $registrar->forgetCachedPermissions();

            $storekeeper = Role::findByName('storekeeper', 'web');
            $this->assertFalse($storekeeper->hasPermissionTo('items.create'));
            $this->assertFalse($storekeeper->hasPermissionTo('items.edit'));
            $this->assertTrue($storekeeper->hasPermissionTo('transfers.create'));
            $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('items.edit'), 'admin keeps item editing');
            $this->assertTrue(Role::findByName('stock-lead', 'web')->hasPermissionTo('items.edit'), 'custom roles are untouched');

            $this->runMigration('down');
            $registrar->forgetCachedPermissions();

            $storekeeper = Role::findByName('storekeeper', 'web');
            $this->assertTrue($storekeeper->hasPermissionTo('items.create'));
            $this->assertTrue($storekeeper->hasPermissionTo('items.edit'));
        });
    }

    private function runMigration(string $direction): void
    {
        $migration = require base_path(self::MIGRATION);
        $this->assertInstanceOf(Migration::class, $migration);

        $direction === 'up' ? $migration->up() : $migration->down();
    }
}
