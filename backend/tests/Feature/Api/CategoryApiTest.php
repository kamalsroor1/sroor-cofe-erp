<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);

        $role = Role::create(['name' => 'admin']);

        $this->store = Store::create([
            'name'      => 'الفرع الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'email'            => 'admin@sroor.com',
            'password'         => Hash::make('password123'),
            'is_active'        => true,
        ]);
        $this->adminUser->assignRole($role);

        $this->adminToken = $this->adminUser->createToken('admin-test-token')->plainTextToken;
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

    public function test_can_update_and_delete_category(): void
    {
        $cat = Category::create([
            'name'       => 'حلويات',
            'icon'       => '🍰',
            'sort_order' => 2,
            'is_active'  => true,
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
    }
}