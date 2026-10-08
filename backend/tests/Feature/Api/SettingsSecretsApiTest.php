<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * SETG-7 (urgent security fix, tenant-settings-catalog §5 #4):
 *  - the Telegram bot token is write-only: GET /settings and the POST /settings response
 *    never echo it, they only expose `has_telegram_bot_token`;
 *  - saving the settings form with an empty token keeps the stored one (the client never
 *    receives it, so it can only send it back empty);
 *  - a failed save never returns the raw exception message to the client;
 *  - POST /settings/telegram/test validates its input and never leaks the token through a
 *    transport error message.
 */
final class SettingsSecretsApiTest extends TenantTestCase
{
    // Fake Telegram bot tokens in the real <bot id>:<secret> shape, assembled from parts so
    // no token literal is committed (gitleaks). The secret part uses the `fixture` prefix,
    // which .gitleaks.toml treats as fake by convention.
    private const FAKE_BOT_ID = '123456789';

    private const OTHER_FAKE_BOT_ID = '987654321';

    private const FAKE_SECRET_PART = 'fixtureTelegramBotSecretA_abcdefghijk';

    private const OTHER_FAKE_SECRET_PART = 'fixtureTelegramBotSecretB_zyxwvutsrq';

    private const TOKEN = self::FAKE_BOT_ID.':'.self::FAKE_SECRET_PART;

    private const OTHER_TOKEN = self::OTHER_FAKE_BOT_ID.':'.self::OTHER_FAKE_SECRET_PART;

    public function test_get_settings_never_returns_the_stored_bot_token(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('telegram_bot_token', self::TOKEN));

        $response = $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('settings.telegram_bot_token', '')
            ->assertJsonPath('settings.has_telegram_bot_token', true);

        $this->assertStringNotContainsString(self::TOKEN, (string) $response->getContent());
    }

    public function test_has_flag_is_false_when_no_token_is_stored(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('settings.telegram_bot_token', '')
            ->assertJsonPath('settings.has_telegram_bot_token', false);
    }

    public function test_update_response_never_returns_the_bot_token(): void
    {
        $tenant = $this->createTenant();

        $response = $this->postJson('/api/v1/settings', $this->payload([
            'telegram_bot_token' => self::TOKEN,
        ]), $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('settings.has_telegram_bot_token', true);

        $this->assertStringNotContainsString(self::TOKEN, (string) $response->getContent());
        $this->assertStoredToken($tenant, self::TOKEN);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function blankTokenPayloads(): array
    {
        return [
            'token omitted' => [[]],
            'token empty string' => [['telegram_bot_token' => '']],
            'token null' => [['telegram_bot_token' => null]],
        ];
    }

    /**
     * @param  array<string, mixed>  $tokenField
     */
    #[DataProvider('blankTokenPayloads')]
    public function test_saving_with_a_blank_token_keeps_the_stored_token(array $tokenField): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('telegram_bot_token', self::TOKEN));

        $this->postJson('/api/v1/settings', $this->payload($tokenField), $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('settings.has_telegram_bot_token', true);

        $this->assertStoredToken($tenant, self::TOKEN);
    }

    public function test_a_new_token_replaces_the_stored_one(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('telegram_bot_token', self::TOKEN));

        $this->postJson('/api/v1/settings', $this->payload(['telegram_bot_token' => self::OTHER_TOKEN]), $this->tenantHeaders($tenant))
            ->assertStatus(200);

        $this->assertStoredToken($tenant, self::OTHER_TOKEN);
    }

    public function test_the_token_can_be_cleared_explicitly(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('telegram_bot_token', self::TOKEN));

        $this->postJson('/api/v1/settings', $this->payload(['clear_telegram_bot_token' => true]), $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('settings.has_telegram_bot_token', false);

        $this->assertStoredToken($tenant, '');
        $this->inTenant($tenant, function (): void {
            $this->assertFalse(Setting::query()->where('key', 'clear_telegram_bot_token')->exists());
        });
    }

    public function test_failed_save_does_not_leak_the_exception_message(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, function (): void {
            Schema::drop('settings');
        });

        $response = $this->postJson('/api/v1/settings', $this->payload(), $this->tenantHeaders($tenant))
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('settings.settings_save_failed'));

        $body = (string) $response->getContent();
        $this->assertStringNotContainsStringIgnoringCase('SQLSTATE', $body);
        $this->assertStringNotContainsStringIgnoringCase('no such table', $body);
    }

    public function test_settings_of_one_tenant_never_expose_another_tenants_token_flag(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();
        $this->inTenant($a, fn () => Setting::set('telegram_bot_token', self::TOKEN));

        $this->getJson('/api/v1/settings', $this->tenantHeaders($b))
            ->assertStatus(200)
            ->assertJsonPath('settings.has_telegram_bot_token', false);
    }

    // ---------------------------------------------------------------------
    // POST /settings/telegram/test
    // ---------------------------------------------------------------------

    public function test_telegram_test_requires_authentication(): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings/telegram/test', ['chat_id' => '12345'], [
            'Accept' => 'application/json',
            'X-Tenant' => (string) $tenant->getTenantKey(),
        ])->assertStatus(401);
    }

    public function test_telegram_test_is_forbidden_without_settings_permission(): void
    {
        Http::fake();
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->postJson('/api/v1/settings/telegram/test', [
            'bot_token' => self::TOKEN,
            'chat_id' => '12345',
        ], $this->tenantHeaders($tenant, $cashier))->assertStatus(403);

        $this->assertStoredToken($tenant, null);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidTelegramInput(): array
    {
        return [
            'token with a path separator' => ['bot_token', '123:abc/../getUpdates'],
            'token with a query string' => ['bot_token', '123:abc?x=1'],
            'token with whitespace' => ['bot_token', '123:abc def'],
            'token too long' => ['bot_token', str_repeat('a', 256)],
            'token not a string' => ['bot_token', ['123:abc']],
            'chat id with letters' => ['chat_id', 'abc;rm'],
            'chat id with a path' => ['chat_id', '123/456'],
            'chat id too long' => ['chat_id', str_repeat('1', 256)],
        ];
    }

    #[DataProvider('invalidTelegramInput')]
    public function test_telegram_test_rejects_invalid_input_without_saving(string $field, mixed $value): void
    {
        Http::fake();
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings/telegram/test', [$field => $value], $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);

        $this->inTenant($tenant, function (): void {
            $this->assertNull(Setting::get('telegram_bot_token'));
            $this->assertNull(Setting::get('telegram_chat_id'));
        });
        Http::assertNothingSent();
    }

    public function test_telegram_test_sends_with_the_given_credentials_and_stores_them(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $tenant = $this->createTenant();

        $response = $this->postJson('/api/v1/settings/telegram/test', [
            'bot_token' => ' '.self::TOKEN.' ',
            'chat_id' => '-1001234567890',
        ], $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertStringNotContainsString(self::TOKEN, (string) $response->getContent());
        $this->assertStoredToken($tenant, self::TOKEN);
        $this->inTenant($tenant, fn () => $this->assertSame('-1001234567890', Setting::get('telegram_chat_id')));

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/bot'.self::TOKEN.'/sendMessage')
            && $request['chat_id'] === '-1001234567890');
    }

    public function test_telegram_test_without_a_token_uses_the_stored_one(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('telegram_bot_token', self::TOKEN));

        // The settings screen sends the (always empty) masked field back.
        $this->postJson('/api/v1/settings/telegram/test', [
            'bot_token' => '',
            'chat_id' => '12345',
        ], $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertStoredToken($tenant, self::TOKEN);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/bot'.self::TOKEN.'/'));
    }

    public function test_telegram_transport_errors_never_leak_the_token(): void
    {
        $token = self::TOKEN;
        Http::fake(function (HttpRequest $request) use ($token): never {
            throw new ConnectionException('cURL error 6: Could not resolve host for https://api.telegram.org/bot'.$token.'/sendMessage');
        });
        $tenant = $this->createTenant();

        $response = $this->postJson('/api/v1/settings/telegram/test', [
            'bot_token' => self::TOKEN,
            'chat_id' => '12345',
        ], $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('success', false);

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString(self::TOKEN, $body);
        $this->assertStringNotContainsString(self::FAKE_SECRET_PART, $body);
        $this->assertStringNotContainsString('cURL', $body);
    }

    private function assertStoredToken(Tenant $tenant, ?string $expected): void
    {
        $this->inTenant($tenant, function () use ($expected): void {
            $this->assertSame($expected, Setting::get('telegram_bot_token'));
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['company_name' => 'محل اختبار الأسرار'], $overrides);
    }
}
