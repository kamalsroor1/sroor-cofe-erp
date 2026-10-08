<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\SetRequestLocale;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * SETG-3: every API response is produced in the caller's language.
 *
 * Resolution order (SetRequestLocale / RequestLocaleResolver):
 *   users.locale (authenticated user) → X-Locale header (ar|en only)
 *   → tenant default_locale (SETG-1 TenantSettings) → 'ar'.
 *
 * Observed through real messages: the guest 401 of ApiTokenAuth (auth.unauthorized),
 * the authenticated logout message (auth.logout_success) and the Content-Language header.
 */
final class LocaleResolutionTest extends TenantTestCase
{
    public function test_users_table_has_a_nullable_locale_column(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $this->assertTrue(Schema::hasColumn('users', 'locale'));

            $user = User::query()->firstOrFail();
            $this->assertNull($user->locale);
        });
    }

    public function test_guest_without_header_gets_arabic_by_default(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/auth/me', $this->guestHeaders($tenant))
            ->assertStatus(401)
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', trans('auth.unauthorized', [], 'ar'));
    }

    public function test_guest_x_locale_header_selects_english(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/auth/me', $this->guestHeaders($tenant, 'en'))
            ->assertStatus(401)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', trans('auth.unauthorized', [], 'en'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsupportedHeaders(): array
    {
        return [
            'unsupported language' => ['fr'],
            'region suffix' => ['en-US'],
            'underscore region' => ['ar_EG'],
            'path traversal' => ['../../../etc/passwd'],
            'empty' => [''],
            'list' => ['en,ar'],
        ];
    }

    #[DataProvider('unsupportedHeaders')]
    public function test_unsupported_x_locale_values_are_ignored(string $header): void
    {
        $tenant = $this->tenantWithDefaultLocale('en');

        // Falls through to the tenant default; the raw header is never echoed back.
        $this->getJson('/api/v1/auth/me', $this->guestHeaders($tenant, $header))
            ->assertStatus(401)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', trans('auth.unauthorized', [], 'en'));
    }

    public function test_x_locale_header_is_case_insensitive(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/auth/me', $this->guestHeaders($tenant, ' EN '))
            ->assertStatus(401)
            ->assertHeader('Content-Language', 'en');
    }

    public function test_guest_falls_back_to_the_tenant_default_locale(): void
    {
        // Proves SetRequestLocale runs after ResolveApiTenancy (tenant settings are readable).
        $tenant = $this->tenantWithDefaultLocale('en');

        $this->getJson('/api/v1/auth/me', $this->guestHeaders($tenant))
            ->assertStatus(401)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', trans('auth.unauthorized', [], 'en'));
    }

    public function test_guest_header_wins_over_the_tenant_default(): void
    {
        $tenant = $this->tenantWithDefaultLocale('en');

        $this->getJson('/api/v1/auth/me', $this->guestHeaders($tenant, 'ar'))
            ->assertStatus(401)
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', trans('auth.unauthorized', [], 'ar'));
    }

    public function test_authenticated_user_locale_wins_over_header_and_tenant_default(): void
    {
        $tenant = $this->tenantWithDefaultLocale('ar');
        $user = $this->createTenantUser($tenant, 'admin', [], ['locale' => 'en']);

        $this->postJson('/api/v1/auth/logout', [], $this->userHeaders($tenant, $user, 'ar'))
            ->assertStatus(200)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', trans('auth.logout_success', [], 'en'));
    }

    public function test_arabic_user_locale_wins_over_an_english_header(): void
    {
        $tenant = $this->tenantWithDefaultLocale('en');
        $user = $this->createTenantUser($tenant, 'admin', [], ['locale' => 'ar']);

        $this->postJson('/api/v1/auth/logout', [], $this->userHeaders($tenant, $user, 'en'))
            ->assertStatus(200)
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', trans('auth.logout_success', [], 'ar'));
    }

    public function test_authenticated_user_without_locale_uses_the_header(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, 'admin');

        $this->postJson('/api/v1/auth/logout', [], $this->userHeaders($tenant, $user, 'en'))
            ->assertStatus(200)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', trans('auth.logout_success', [], 'en'));
    }

    public function test_authenticated_user_without_locale_or_header_uses_the_tenant_default(): void
    {
        $tenant = $this->tenantWithDefaultLocale('en');
        $user = $this->createTenantUser($tenant, 'admin');

        $this->postJson('/api/v1/auth/logout', [], $this->userHeaders($tenant, $user))
            ->assertStatus(200)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', trans('auth.logout_success', [], 'en'));
    }

    public function test_validation_messages_follow_the_user_locale(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, 'admin', [], ['locale' => 'en']);

        $response = $this->putJson('/api/v1/profile', [], $this->userHeaders($tenant, $user, 'ar'))
            ->assertStatus(422)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonValidationErrors(['name']);

        $message = (string) $response->json('errors.name.0');
        $this->assertStringContainsString('required', $message);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $message);
    }

    public function test_user_can_save_a_locale_preference_through_the_profile(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, 'admin');

        $this->putJson('/api/v1/profile', $this->profilePayload($user, ['locale' => 'en']), $this->userHeaders($tenant, $user))
            ->assertStatus(200)
            ->assertJsonPath('data.locale', 'en');

        $this->inTenant($tenant, function () use ($user): void {
            $this->assertSame('en', User::query()->findOrFail($user->getKey())->locale);
        });

        // The next request is answered in the saved language without any header.
        $this->postJson('/api/v1/auth/logout', [], $this->userHeaders($tenant, $user))
            ->assertStatus(200)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', trans('auth.logout_success', [], 'en'));
    }

    public function test_user_can_clear_the_locale_preference(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, 'admin', [], ['locale' => 'en']);

        $this->putJson('/api/v1/profile', $this->profilePayload($user, ['locale' => null]), $this->userHeaders($tenant, $user))
            ->assertStatus(200)
            ->assertJsonPath('data.locale', null);

        $this->inTenant($tenant, function () use ($user): void {
            $this->assertNull(User::query()->findOrFail($user->getKey())->locale);
        });
    }

    public function test_profile_update_without_locale_keeps_the_saved_preference(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, 'admin', [], ['locale' => 'en']);

        // Older clients do not send `locale`; they must not wipe it.
        $this->putJson('/api/v1/profile', $this->profilePayload($user), $this->userHeaders($tenant, $user))
            ->assertStatus(200);

        $this->inTenant($tenant, function () use ($user): void {
            $this->assertSame('en', User::query()->findOrFail($user->getKey())->locale);
        });
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidProfileLocales(): array
    {
        return [
            'unsupported' => ['fr'],
            'region suffix' => ['en-US'],
            'uppercase' => ['EN'],
            'array' => [['en']],
        ];
    }

    #[DataProvider('invalidProfileLocales')]
    public function test_profile_rejects_unsupported_locales_with_422(mixed $locale): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, 'admin');

        $this->putJson('/api/v1/profile', $this->profilePayload($user, ['locale' => $locale]), $this->userHeaders($tenant, $user))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);

        $this->inTenant($tenant, function () use ($user): void {
            $this->assertNull(User::query()->findOrFail($user->getKey())->locale);
        });
    }

    public function test_profile_locale_update_requires_authentication(): void
    {
        $tenant = $this->createTenant();

        $this->putJson('/api/v1/profile', ['locale' => 'en'], $this->guestHeaders($tenant))
            ->assertStatus(401);
    }

    public function test_tenant_default_locale_does_not_leak_between_tenants(): void
    {
        $english = $this->tenantWithDefaultLocale('en');
        $arabic = $this->createTenant();

        $this->getJson('/api/v1/auth/me', $this->guestHeaders($english))
            ->assertHeader('Content-Language', 'en');

        $this->getJson('/api/v1/auth/me', $this->guestHeaders($arabic))
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', trans('auth.unauthorized', [], 'ar'));
    }

    public function test_a_user_locale_does_not_leak_into_the_next_request(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, 'admin', [], ['locale' => 'en']);

        $this->postJson('/api/v1/auth/logout', [], $this->userHeaders($tenant, $user))
            ->assertHeader('Content-Language', 'en');

        $this->getJson('/api/v1/auth/me', $this->guestHeaders($tenant))
            ->assertStatus(401)
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', trans('auth.unauthorized', [], 'ar'));
    }

    public function test_central_api_routes_resolve_without_a_tenant(): void
    {
        // No tenancy on central routes: header → 'ar', and nothing touches a tenant DB.
        $this->getJson('/api/v1/ping', ['Accept' => 'application/json', 'X-Locale' => 'en'])
            ->assertStatus(200)
            ->assertHeader('Content-Language', 'en');

        $this->getJson('/api/v1/ping', ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertHeader('Content-Language', 'ar');
    }

    public function test_middleware_is_registered_on_the_api_group_after_tenancy(): void
    {
        $this->getJson('/api/v1/ping', ['Accept' => 'application/json'])->assertStatus(200);

        $router = $this->app->make('router');
        $this->assertContains(SetRequestLocale::class, $router->getMiddlewareGroups()['api'] ?? []);
    }

    private function tenantWithDefaultLocale(string $locale): Tenant
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($locale): void {
            Setting::set('default_locale', $locale);
        });

        return $tenant;
    }

    /**
     * @return array<string, string>
     */
    private function guestHeaders(Tenant $tenant, ?string $locale = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant' => (string) $tenant->getTenantKey(),
        ];

        if ($locale !== null) {
            $headers['X-Locale'] = $locale;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function userHeaders(Tenant $tenant, User $user, ?string $locale = null): array
    {
        $headers = $this->tenantHeaders($tenant, $user);

        if ($locale !== null) {
            $headers['X-Locale'] = $locale;
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profilePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'theme_preference' => 'dark',
        ], $overrides);
    }
}
