<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsProfileTrashApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);

        $this->store = Store::create([
            'name'      => 'المحمصة الرئيسية',
            'code'      => 'MAIN-01',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'email'            => 'kamal@sroor.com',
            'password'         => Hash::make('password123'),
            'theme_preference' => 'dark',
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($role);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;
    }

    public function test_can_get_and_update_settings(): void
    {
        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/settings');

        $getResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['settings', 'stores', 'users_count', 'system_info']);

        $payload = [
            'company_name'                   => 'محامص سرور العالمية',
            'company_subtitle'               => 'أجود أنواع البن الفاخر',
            'company_phone'                  => '01099998888',
            'company_address'                => 'القاهرة الجديدة',
            'invoice_footer_note'            => 'أهلاً بكم في سرور كوفي',
            'show_print_company_name'        => true,
            'show_print_subtitle'            => true,
            'show_print_logo'                => true,
            'thermal_show_customer_balance'  => true,
            'print_show_qr'                  => true,
            'telegram_notifications_enabled' => false,
        ];

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/settings', $payload);

        $updateResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('محامص سرور العالمية', Setting::get('company_name'));
        $this->assertEquals('أجود أنواع البن الفاخر', Setting::get('company_subtitle'));
    }

    public function test_can_get_and_update_profile(): void
    {
        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/profile');

        $getResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name'  => 'كمال سرور',
                    'phone' => '01012316954',
                ],
            ]);

        $updatePayload = [
            'name'                      => 'كمال سرور المطور',
            'phone'                     => '01012316954',
            'email'                     => 'developer@sroor.com',
            'theme_preference'          => 'light',
            'current_password'          => 'password123',
            'new_password'              => 'newsecret456',
            'new_password_confirmation' => 'newsecret456',
        ];

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson('/api/v1/profile', $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name'             => 'كمال سرور المطور',
                    'theme_preference' => 'light',
                ],
            ]);

        $this->assertTrue(Hash::check('newsecret456', $this->adminUser->fresh()->password));
    }

    public function test_can_get_trash_and_restore_and_force_delete(): void
    {
        $item = Item::create([
            'name'          => 'صنف للتجربة بسلة المهملات',
            'code'          => 'TRASH-ITEM-01',
            'category'      => 'coffee_beans',
            'cost_price'    => '200.000',
            'selling_price' => '300.000',
            'current_stock' => '10.000',
            'is_active'     => true,
        ]);

        $item->delete(); // Soft deleted

        $trashResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/trash?tab=items');

        $trashResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'tab'     => 'items',
            ])
            ->assertJsonStructure(['data', 'counts', 'pagination']);

        $this->assertEquals(1, $trashResponse->json('counts.items'));

        // Restore
        $restoreResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/trash/items/{$item->id}/restore");

        $restoreResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertNull($item->fresh()->deleted_at);

        // Delete again and Force delete
        $item->delete();
        $forceResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/trash/items/{$item->id}/force");

        $forceResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }
}
