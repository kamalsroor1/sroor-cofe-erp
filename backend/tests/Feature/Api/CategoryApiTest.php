<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryApiTest extends TestCase
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
            'name'      => 'الفرع الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'email'            => 'admin@sroor.com',
            'password'         => Hash::make('password123'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->unauthorizedUser = User::factory()->create([
            'name'             => 'مستخدم بدون صلاحية',
            'email'            => 'no-perm@sroor.com',
            'password'         => Hash::make('password123'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth-token')->plainTextToken;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/categories');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_create_category(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->postJson('/api/v1/categories', [
                'name' => 'فئة ممنوعة',
                'icon' => '🚫',
            ]);

        $response->assertStatus(403);
    }

    public function test_can_list_and_create_categories(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/categories', [
                'name'       => 'مشروبات ساخنة',
                'icon'       => '☕',
                'sort_order' => 1,
                'is_active'  => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'مشروبات ساخنة')
            ->assertJsonPath('data.icon', '☕');

        $this->assertDatabaseHas('categories', [
            'name' => 'مشروبات ساخنة',
            'icon' => '☕',
        ]);

        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/categories');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_create_category_fails_validation_on_missing_name(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/categories', [
                'icon' => '☕',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_and_delete_category(): void
    {
        $cat = Category::create([
            'name'       => 'حلويات',
            'icon'       => '🍰',
            'sort_order' => 2,
            'is_active'  => true,
        ]);

        $item = Item::create([
            'name'          => 'تشيز كيك لوتس',
            'code'          => 'CAKE-001',
            'category_id'   => $cat->id,
            'unit'          => 'قطعة',
            'cost_price'    => '30.000',
            'selling_price' => '65.000',
            'is_active'     => true,
        ]);

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson("/api/v1/categories/{$cat->id}", [
                'name' => 'حلويات ومعجنات فاخرة',
                'icon' => '🥐',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'حلويات ومعجنات فاخرة')
            ->assertJsonPath('data.icon', '🥐');

        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/categories/{$cat->id}");

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('categories', ['id' => $cat->id]);

        // Item's category_id should be safely nullified
        $this->assertNull(Item::find($item->id)->category_id);
    }

    public function test_unauthorized_user_cannot_delete_category(): void
    {
        $cat = Category::create([
            'name'      => 'قسم سري',
            'icon'      => '🔒',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->deleteJson("/api/v1/categories/{$cat->id}");

        $response->assertStatus(403);
    }
}
