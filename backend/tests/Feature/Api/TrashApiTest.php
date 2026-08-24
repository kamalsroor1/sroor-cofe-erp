<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrashApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected User $unauthorizedUser;
    protected string $unauthorizedToken;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'warehouse',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->unauthorizedUser = User::factory()->create([
            'name'             => 'مستخدم بدون صلاحيات',
            'phone'            => '01000000000',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth-token')->plainTextToken;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/trash');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_trash(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->getJson('/api/v1/trash');

        $response->assertStatus(403);
    }

    public function test_can_list_trashed_records_and_counts(): void
    {
        $item = Item::create([
            'name'            => 'صنف محذوف',
            'code'            => 'DEL-01',
            'category'        => 'coffee_beans',
            'cost_price'      => '100.000',
            'selling_price'   => '150.000',
            'current_stock'   => '0.000',
            'min_stock_level' => '5.000',
            'is_active'       => true,
        ]);
        $item->delete();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/trash?tab=items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'tab',
                'data',
                'counts' => ['items', 'customers', 'suppliers'],
                'pagination',
            ])
            ->assertJson([
                'success' => true,
                'tab'     => 'items',
                'counts'  => [
                    'items' => 1,
                ],
            ]);
    }

    public function test_can_restore_trashed_item(): void
    {
        $item = Item::create([
            'name'            => 'صنف للاسترجاع',
            'code'            => 'REST-01',
            'category'        => 'coffee_beans',
            'cost_price'      => '100.000',
            'selling_price'   => '150.000',
            'current_stock'   => '0.000',
            'min_stock_level' => '5.000',
            'is_active'       => true,
        ]);
        $item->delete();
        $this->assertSoftDeleted('items', ['id' => $item->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/trash/items/{$item->id}/restore");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertNotSoftDeleted('items', ['id' => $item->id]);
    }

    public function test_can_force_delete_trashed_item(): void
    {
        $item = Item::create([
            'name'            => 'صنف للحذف النهائي',
            'code'            => 'FORCE-01',
            'category'        => 'coffee_beans',
            'cost_price'      => '100.000',
            'selling_price'   => '150.000',
            'current_stock'   => '0.000',
            'min_stock_level' => '5.000',
            'is_active'       => true,
        ]);
        $item->delete();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/trash/items/{$item->id}/force");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_invalid_trash_type_returns_error(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/trash/invalid_type/999/restore");

        $response->assertStatus(422);
    }
}
