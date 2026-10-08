<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemContextApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name' => 'المخزن الرئيسي',
            'code' => 'MAIN-001',
            'type' => 'warehouse',
            'is_main' => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'email' => 'kamal@sroor.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/system/context');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_system_context_bootstrap_payload(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/system/context');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'auth' => ['user'],
                    'active_store',
                    'stores',
                    'system' => ['platform_name', 'company_name', 'system_theme_color'],
                    'branding',
                    'notifications',
                    'locale',
                    'translations',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_system_context_includes_active_store_and_stores_list(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->withHeader('X-Store-Id', (string) $this->store->id)
            ->getJson('/api/v1/system/context');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_store' => [
                        'id' => $this->store->id,
                        'name' => 'المخزن الرئيسي',
                    ],
                ],
            ]);
    }

    public function test_system_context_includes_low_stock_and_debt_alerts(): void
    {
        Item::create([
            'name' => 'بن كولومبي ناقص',
            'code' => 'BN-LOW',
            'category' => 'coffee_beans',
            'cost_price' => '400.000',
            'selling_price' => '550.000',
            'current_stock' => '5.000',
            'min_stock_level' => '20.000',
            'is_active' => true,
        ]);

        Customer::create([
            'name' => 'عميل مدين',
            'phone' => '01000007002',
            'current_balance' => '1500.000',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/system/context');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $notifications = $response->json('data.notifications');
        $this->assertNotEmpty($notifications);
    }

    public function test_can_fetch_translation_dictionary(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/system/translations?locale=ar');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'locale',
                'data',
            ])
            ->assertJson([
                'success' => true,
                'locale' => 'ar',
            ]);
    }

    // ------------------------------------------------------------------
    // P0-X3 regression: locale path traversal in the public translations
    // endpoint must not load arbitrary PHP files (config/*.php) as "groups".
    // ------------------------------------------------------------------

    private const SENTINEL_KEY = 'base64:TESTKEYSENTINELQAp0x3aaaaaaaaaaaaaaaaaaaaaaa=';

    /**
     * Secrets that must never appear in a translations payload: the env APP_KEY
     * (what config/app.php evaluates to when included) and the runtime config key.
     *
     * @return list<string>
     */
    private function secretsThatMustNotLeak(): array
    {
        $envKey = (string) env('APP_KEY');
        config(['app.key' => self::SENTINEL_KEY]);

        return array_values(array_filter([$envKey, self::SENTINEL_KEY]));
    }

    private function assertNoConfigLeak(string $body, array $secrets): void
    {
        $this->assertStringNotContainsString('cipher', $body);
        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString('AES-256-CBC', $body);
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $body);
            $this->assertStringNotContainsString(str_replace('/', '\\/', $secret), $body);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function traversalLocales(): array
    {
        return [
            'dot-dot config' => ['../config'],
            'double dot-dot config' => ['../../config'],
            'nested traversal' => ['en/../../config'],
            'absolute path' => ['/etc'],
            'backslash traversal' => ['..\\config'],
            'unknown locale' => ['fr'],
        ];
    }

    #[DataProvider('traversalLocales')]
    public function test_translations_rejects_traversal_locale_in_query(string $locale): void
    {
        $secrets = $this->secretsThatMustNotLeak();

        $response = $this->getJson('/api/v1/system/translations?locale='.rawurlencode($locale));

        $response->assertStatus(422);
        $this->assertNoConfigLeak((string) $response->getContent(), $secrets);
    }

    public function test_translations_rejects_raw_unencoded_traversal_locale(): void
    {
        $secrets = $this->secretsThatMustNotLeak();

        $response = $this->getJson('/api/v1/system/translations?locale=../config');

        $response->assertStatus(422);
        $this->assertNoConfigLeak((string) $response->getContent(), $secrets);
    }

    public function test_translations_rejects_percent_encoded_traversal_locale(): void
    {
        $secrets = $this->secretsThatMustNotLeak();

        $response = $this->getJson('/api/v1/system/translations?locale=..%2Fconfig');

        $response->assertStatus(422);
        $this->assertNoConfigLeak((string) $response->getContent(), $secrets);
    }

    public function test_translations_ignores_traversal_x_locale_header_and_falls_back_to_default(): void
    {
        $secrets = $this->secretsThatMustNotLeak();

        $response = $this->withHeader('X-Locale', '../config')
            ->getJson('/api/v1/system/translations');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('locale', config('app.locale', 'ar'));

        $this->assertIsArray($response->json('data.auth'));
        $this->assertNull($response->json('data.app.cipher'));
        $this->assertNull($response->json('data.auth.providers'));
        $this->assertNoConfigLeak((string) $response->getContent(), $secrets);
        $this->assertStringNotContainsString('"providers"', (string) $response->getContent());
    }

    public function test_translations_accepts_supported_english_locale(): void
    {
        $response = $this->getJson('/api/v1/system/translations?locale=en');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('locale', 'en');

        $this->assertIsArray($response->json('data.auth'));
    }

    public function test_translations_accepts_supported_arabic_locale_without_token(): void
    {
        $response = $this->getJson('/api/v1/system/translations?locale=ar');

        $response->assertStatus(200)
            ->assertJsonPath('locale', 'ar');
        $this->assertIsArray($response->json('data.auth'));
    }

    public function test_system_context_ignores_traversal_x_locale_header(): void
    {
        $secrets = $this->secretsThatMustNotLeak();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->withHeader('X-Locale', '../config')
            ->getJson('/api/v1/system/context');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $translationsJson = (string) json_encode($response->json('data.translations'));
        $this->assertNoConfigLeak($translationsJson, $secrets);
        $this->assertNull($response->json('data.translations.app.cipher'));
        $this->assertNotSame('../config', $response->json('data.locale'));
    }

    /**
     * AUTH-2: the public translations endpoint is rate limited (IDEN-4.6 public-api, 60/min per endpoint and IP).
     */
    public function test_public_translations_endpoint_is_throttled_after_sixty_requests_per_minute(): void
    {
        for ($i = 1; $i <= 60; $i++) {
            $status = $this->getJson('/api/v1/system/translations?locale=en')->status();
            $this->assertSame(200, $status, "request #{$i} must still be served");
        }

        $this->getJson('/api/v1/system/translations?locale=en')
            ->assertStatus(429)
            ->assertJsonPath('message', __('auth.too_many_requests'));

        $this->assertNotSame('auth.too_many_requests', __('auth.too_many_requests'), 'auth.too_many_requests lang key must exist');
    }
}
