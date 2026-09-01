<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingApiTest extends TestCase
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
            'name'      => 'المحمصة الرئيسية',
            'code'      => 'MAIN-01',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'email'            => 'kamal@sroor.com',
            'password'         => Hash::make('password123'),
            'theme_preference' => 'dark',
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
        $response = $this->getJson('/api/v1/settings');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_or_update_settings(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->getJson('/api/v1/settings');

        $response->assertStatus(403);
    }

    public function test_can_get_settings_dictionary(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/settings');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'settings' => [
                    'company_name',
                    'company_subtitle',
                    'company_phone',
                    'company_address',
                    'invoice_footer_note',
                    'show_print_company_name',
                    'show_print_subtitle',
                    'show_print_logo',
                    'thermal_show_customer_balance',
                    'print_show_qr',
                    'invoice_primary_color',
                    'system_theme_color',
                    'inventory_units',
                    'telegram_bot_token',
                    'telegram_chat_id',
                    'telegram_notifications_enabled',
                ],
                'stores',
                'users_count',
                'system_info',
            ]);
    }

    public function test_can_update_settings(): void
    {
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

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/settings', $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('محامص سرور العالمية', Setting::get('company_name'));
        $this->assertEquals('أجود أنواع البن الفاخر', Setting::get('company_subtitle'));
        $this->assertEquals('0', Setting::get('telegram_notifications_enabled'));
    }

    public function test_update_settings_fails_validation_on_empty_company_name(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/settings', [
                'company_name' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_name']);
    }

    public function test_can_send_test_telegram_notification(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/settings/telegram/test', [
                'bot_token' => 'test_bot_token_123',
                'chat_id'   => '123456789',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }
}
