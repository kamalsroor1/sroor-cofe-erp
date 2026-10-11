<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StoreStock;
use App\Models\Tenant;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * CTO decision 2026-10-11: the storekeeper enters purchase costs, so the seeded `storekeeper`
 * role holds items.view_cost. PermissionsSeeder grants it to new tenants and tenant migration
 * 2026_10_11_110000 grants it to existing ones. Cashiers still never see the cost.
 */
final class StorekeeperItemCostVisibilityTest extends TenantTestCase
{
    private const MIGRATION = 'database/migrations/tenant/2026_10_11_110000_grant_view_cost_to_storekeeper.php';

    private Tenant $tenant;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($this->tenant)->id;

        $this->itemId = $this->inTenant($this->tenant, function () use ($storeId): int {
            $item = Item::query()->create([
                'code' => 'SKC-1',
                'name' => 'بن كولومبي',
                'unit' => 'كجم',
                'cost_price' => '250.500',
                'selling_price' => '400.000',
                'current_stock' => '5.000',
                'is_active' => true,
            ]);

            StoreStock::query()->create(['store_id' => $storeId, 'item_id' => $item->id, 'quantity' => '5.000']);

            StockMovement::query()->create([
                'item_id' => $item->id,
                'movement_type' => 'purchase_in',
                'quantity' => '5.000',
                'stock_before' => '0.000',
                'stock_after' => '5.000',
                'unit_cost' => '250.500',
                'source_type' => 'adjustment',
                'source_id' => 0,
                'document_number' => 'SKC-MOV-1',
                'store_id' => $storeId,
            ]);

            return (int) $item->id;
        });
    }

    public function test_seeded_storekeeper_role_holds_view_cost_and_cashier_does_not(): void
    {
        $this->inTenant($this->tenant, function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->assertTrue(Role::findByName('storekeeper', 'web')->hasPermissionTo('items.view_cost'));
            $this->assertFalse(Role::findByName('cashier', 'web')->hasPermissionTo('items.view_cost'));
            // The 2026-10-09 decision (no item editing) still holds.
            $this->assertFalse(Role::findByName('storekeeper', 'web')->hasPermissionTo('items.edit'));
        });
    }

    public function test_storekeeper_sees_the_cost_on_the_item_and_purchase_screen_endpoints(): void
    {
        $storekeeper = $this->createTenantUser($this->tenant, 'storekeeper');
        $headers = $this->tenantHeaders($this->tenant, $storekeeper);

        // The purchase form's item picker (GET /items?per_page=200).
        $list = $this->getJson('/api/v1/items?per_page=200', $headers)->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $this->itemId);
        $this->assertIsArray($row);
        $this->assertSame(250.5, (float) $row['cost_price']);
        $this->assertNotNull($list->json('summary.total_stock_value'));

        $item = $this->getJson("/api/v1/items/{$this->itemId}", $headers)->assertOk()->json('data');
        $this->assertSame(250.5, (float) $item['cost_price']);

        $movements = $this->getJson("/api/v1/items/{$this->itemId}/movements", $headers)->assertOk();
        $this->assertSame(250.5, (float) $movements->json('data.item.cost_price'));
        $this->assertArrayHasKey('unit_cost', $movements->json('data.data.0'));

        $stock = $this->getJson('/api/v1/stores/stocks', $headers)->assertOk()->json('data.0');
        $this->assertSame(250.5, (float) $stock['cost_price']);
    }

    public function test_cashier_still_gets_null_cost(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $headers = $this->tenantHeaders($this->tenant, $cashier);

        $list = $this->getJson('/api/v1/items', $headers)->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $this->itemId);
        $this->assertIsArray($row);
        $this->assertNull($row['cost_price']);
        $this->assertNull($list->json('summary.total_stock_value'));

        $this->assertNull($this->getJson("/api/v1/items/{$this->itemId}", $headers)->assertOk()->json('data.cost_price'));
    }

    public function test_migration_grants_to_storekeeper_only_is_idempotent_and_reversible(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $registrar = app(PermissionRegistrar::class);
            // A tenant provisioned before the decision.
            Role::findByName('storekeeper', 'web')->revokePermissionTo('items.view_cost');
            $registrar->forgetCachedPermissions();
            $this->assertFalse(Role::findByName('storekeeper', 'web')->hasPermissionTo('items.view_cost'));

            $this->runMigration('up');
            $this->runMigration('up');
            $registrar->forgetCachedPermissions();

            $storekeeper = Role::findByName('storekeeper', 'web');
            $this->assertTrue($storekeeper->hasPermissionTo('items.view_cost'));
            $this->assertSame(1, $storekeeper->permissions()->where('name', 'items.view_cost')->count());
            $this->assertFalse(Role::findByName('cashier', 'web')->hasPermissionTo('items.view_cost'), 'other roles untouched');

            $this->runMigration('down');
            $this->runMigration('down');
            $registrar->forgetCachedPermissions();

            $this->assertFalse(Role::findByName('storekeeper', 'web')->hasPermissionTo('items.view_cost'));
            $this->assertTrue(Permission::query()->where('name', 'items.view_cost')->where('guard_name', 'web')->exists(), 'down() keeps the permission');
            $this->assertTrue(Role::findByName('accountant', 'web')->hasPermissionTo('items.view_cost'), 'accountant keeps its grant');
            $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('items.view_cost'), 'admin keeps its grant');
        });
    }

    public function test_migration_creates_the_permission_when_missing_and_skips_a_missing_role(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $registrar = app(PermissionRegistrar::class);
            Permission::query()->where('name', 'items.view_cost')->delete();
            Role::findByName('storekeeper', 'web')->delete();
            $registrar->forgetCachedPermissions();

            $this->runMigration('up');
            $this->runMigration('down');

            $this->assertSame(1, Permission::query()->where('name', 'items.view_cost')->where('guard_name', 'web')->count());
            $this->assertFalse(Role::query()->where('name', 'storekeeper')->exists());

            // The seeder re-creates the role with the grant.
            (new PermissionsSeeder)->run();
            $registrar->forgetCachedPermissions();
            $this->assertTrue(Role::findByName('storekeeper', 'web')->hasPermissionTo('items.view_cost'));
        });
    }

    private function runMigration(string $direction): void
    {
        $migration = require base_path(self::MIGRATION);
        $this->assertInstanceOf(Migration::class, $migration);

        $this->assertContains($direction, ['up', 'down']);

        $migration->{$direction}();
    }
}
