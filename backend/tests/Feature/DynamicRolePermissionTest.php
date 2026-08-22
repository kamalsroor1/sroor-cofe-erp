<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\PermissionsSeeder;

class DynamicRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected User $storekeeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $adminRole = Role::findByName('admin');
        $cashierRole = Role::findByName('cashier');
        $storeRole = Role::findByName('storekeeper');

        $this->admin = User::factory()->create(['phone' => '01012316954', 'is_active' => true]);
        $this->admin->assignRole($adminRole);

        $this->cashier = User::factory()->create(['phone' => '01111111111', 'is_active' => true]);
        $this->cashier->assignRole($cashierRole);

        $this->storekeeper = User::factory()->create(['phone' => '01222222222', 'is_active' => true]);
        $this->storekeeper->assignRole($storeRole);
    }

    public function test_permissions_seeder_populates_all_expected_permissions(): void
    {
        $this->assertGreaterThanOrEqual(25, Permission::count());
        $this->assertTrue(Permission::where('name', 'pos.access')->exists());
        $this->assertTrue(Permission::where('name', 'items.view_cost')->exists());
        $this->assertTrue(Permission::where('name', 'transfers.create')->exists());
        $this->assertTrue(Permission::where('name', 'roles.manage')->exists());
    }

    public function test_admin_can_access_role_permission_manager(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('roles.index'))
            ->assertStatus(200);
    }

    public function test_non_admin_is_forbidden_from_role_permission_manager(): void
    {
        $token = $this->cashier->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/roles')
            ->assertStatus(403);
    }

    public function test_role_permission_manager_can_update_permissions(): void
    {
        $this->actingAs($this->admin);

        $cashierRole = Role::findByName('cashier');
        $newPermissions = ['pos.access', 'invoices.view', 'invoices.create'];

        \Livewire\Livewire::test(\App\Livewire\Auth\RolePermissionManager::class)
            ->call('selectRole', $cashierRole->id)
            ->set('selectedPermissions', $newPermissions)
            ->call('savePermissions')
            ->assertDispatched('swal:toast');

        $cashierRole->refresh();
        $this->assertEqualsCanonicalizing($newPermissions, $cashierRole->permissions->pluck('name')->toArray());
    }

    public function test_admin_can_create_custom_role_via_manager(): void
    {
        $this->actingAs($this->admin);

        \Livewire\Livewire::test(\App\Livewire\Auth\RolePermissionManager::class)
            ->set('newRoleName', 'van_supervisor')
            ->call('createRole')
            ->assertDispatched('swal:toast');

        $this->assertTrue(Role::where('name', 'van_supervisor')->exists());
    }

    public function test_store_user_assignment_syncs_correctly(): void
    {
        $this->actingAs($this->admin);

        $store = Store::create([
            'name'      => 'فرع مدينة نصر',
            'code'      => 'SHOP-NASR',
            'type'      => 'retail_shop',
            'is_active' => true,
        ]);

        \Livewire\Livewire::test(\App\Livewire\StoreIndex::class)
            ->call('openUserAssignmentModal', $store->id)
            ->set('selectedUsers', [$this->cashier->id, $this->storekeeper->id])
            ->call('saveUserAssignment');

        $this->assertCount(2, $store->users()->get());
        $this->assertTrue($store->users->contains($this->cashier->id));
        $this->assertTrue($store->users->contains($this->storekeeper->id));
    }
}
