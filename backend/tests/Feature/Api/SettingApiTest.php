<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

class SettingApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->store = $this->tenantStore($this->tenant);

        $this->adminUser = $this->createTenantUser($this->tenant, 'admin', attributes: [
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'email' => 'kamal@sroor.com',
            'password' => Hash::make('password123'),
            'theme_preference' => 'dark',
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminHeaders = $this->tenantHeaders($this->tenant, $this->adminUser);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: [
            'name' => 'مستخدم بدون صلاحيات',
            'phone' => '01000000000',
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/settings', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_or_update_settings(): void
    {
        $response = $this->getJson('/api/v1/settings', $this->unauthorizedHeaders);

        $response->assertStatus(403);

        $this->postJson('/api/v1/settings', ['company_name' => 'اسم ممنوع'], $this->unauthorizedHeaders)
            ->assertStatus(403);
        $this->inTenant($this->tenant, fn () => $this->assertNotSame('اسم ممنوع', Setting::get('company_name')));
    }

    public function test_roles_manage_alone_cannot_read_the_settings_dictionary(): void
    {
        $this->inTenant($this->tenant, fn () => Setting::set('telegram_bot_token', 'fixture-bot-token-not-a-secret'));

        $rolesManager = $this->createTenantUser($this->tenant, null, ['roles.manage']);
        $this->getJson('/api/v1/settings', $this->tenantHeaders($this->tenant, $rolesManager))
            ->assertStatus(403)
            ->assertJsonMissing(['telegram_bot_token' => 'fixture-bot-token-not-a-secret']);

        $settingsManager = $this->createTenantUser($this->tenant, null, ['settings.manage']);
        $this->getJson('/api/v1/settings', $this->tenantHeaders($this->tenant, $settingsManager))
            ->assertOk();
    }

    public function test_can_get_settings_dictionary(): void
    {
        $response = $this->getJson('/api/v1/settings', $this->adminHeaders);

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
            ])
            ->assertJsonMissingPath('system_info')
            ->assertJsonMissingPath('php_version')
            ->assertJsonMissingPath('laravel_version');

        $this->assertStringNotContainsString('php_version', (string) $response->getContent());
        $this->assertStringNotContainsString('laravel_version', (string) $response->getContent());
    }

    public function test_can_update_settings(): void
    {
        $payload = [
            'company_name' => 'محامص سرور العالمية',
            'company_subtitle' => 'أجود أنواع البن الفاخر',
            'company_phone' => '01000007005',
            'company_address' => 'القاهرة الجديدة',
            'invoice_footer_note' => 'أهلاً بكم في سرور كوفي',
            'show_print_company_name' => true,
            'show_print_subtitle' => true,
            'show_print_logo' => true,
            'thermal_show_customer_balance' => true,
            'print_show_qr' => true,
            'telegram_notifications_enabled' => false,
        ];

        $response = $this->postJson('/api/v1/settings', $payload, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->inTenant($this->tenant, function (): void {
            $this->assertEquals('محامص سرور العالمية', Setting::get('company_name'));
            $this->assertEquals('أجود أنواع البن الفاخر', Setting::get('company_subtitle'));
            $this->assertEquals('0', Setting::get('telegram_notifications_enabled'));
        });
    }

    public function test_update_settings_fails_validation_on_empty_company_name(): void
    {
        $response = $this->postJson('/api/v1/settings', [
            'company_name' => '',
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_name']);
    }

    public function test_update_settings_rejects_html_tags_in_company_text_fields(): void
    {
        $this->inTenant($this->tenant, fn () => Setting::set('company_name', 'محامص سرور'));

        $this->postJson('/api/v1/settings', [
            'company_name' => '<script>alert(1)</script>',
            'company_subtitle' => '<b>فرع</b>',
            'company_phone' => '0100<img src=x>',
            'company_address' => 'شارع <a href="x">النصر</a>',
            'invoice_footer_note' => 'شكرا <script>evil()</script>',
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'company_name',
                'company_subtitle',
                'company_phone',
                'company_address',
                'invoice_footer_note',
            ]);

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame('محامص سرور', Setting::get('company_name'));
        });
    }

    public function test_update_settings_accepts_legitimate_arabic_and_english_business_names(): void
    {
        $name = 'Sroor & Sons Co. — محامص سرور (فرع 1)';

        $this->postJson('/api/v1/settings', [
            'company_name' => $name,
            'company_subtitle' => 'Coffee & Roastery / تحميص وطحن البن',
            'company_phone' => '+20 100-000-7005',
            'company_address' => '15 Tahrir St., Cairo / القاهرة',
            'invoice_footer_note' => 'Thank you for visiting — شكراً لزيارتكم!',
        ], $this->adminHeaders)
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->inTenant($this->tenant, function () use ($name): void {
            $this->assertSame($name, Setting::get('company_name'));
        });
    }

    public function test_can_send_test_telegram_notification(): void
    {
        // No real network in tests.
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $response = $this->postJson('/api/v1/settings/telegram/test', [
            'bot_token' => 'test_bot_token_123',
            'chat_id' => '123456789',
        ], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }

    public function test_settings_are_isolated_between_tenants(): void
    {
        $other = $this->createTenant();
        $this->inTenant($other, fn () => Setting::set('company_name', 'شركة المستأجر الآخر'));

        $this->postJson('/api/v1/settings', ['company_name' => 'محامص سرور العالمية'], $this->adminHeaders)
            ->assertStatus(200);

        $this->getJson('/api/v1/settings', $this->tenantHeaders($other))
            ->assertStatus(200)
            ->assertJsonPath('settings.company_name', 'شركة المستأجر الآخر');
        $this->getJson('/api/v1/settings', $this->adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('settings.company_name', 'محامص سرور العالمية');

        // This tenant's token cannot write the other tenant's settings.
        $this->postJson('/api/v1/settings', ['company_name' => 'اختراق'], array_merge($this->adminHeaders, ['X-Tenant' => (string) $other->getTenantKey()]))
            ->assertStatus(401);
        $this->inTenant($other, fn () => $this->assertSame('شركة المستأجر الآخر', Setting::get('company_name')));
    }
}
