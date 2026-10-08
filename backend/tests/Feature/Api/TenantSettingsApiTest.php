<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Settings\TenantSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * SETG-1: per-tenant localization settings (currency, timezone, default locale,
 * number digits) read through the typed TenantSettings service, validated by
 * UpdateSettingsRequest and exposed by GET /api/v1/settings.
 */
final class TenantSettingsApiTest extends TenantTestCase
{
    public function test_defaults_are_returned_when_nothing_is_stored(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('settings.currency', 'EGP')
            ->assertJsonPath('settings.timezone', 'Africa/Cairo')
            ->assertJsonPath('settings.default_locale', 'ar')
            ->assertJsonPath('settings.number_digits', 'western');

        $this->inTenant($tenant, function (): void {
            $settings = app(TenantSettings::class);

            $this->assertSame('EGP', $settings->currency());
            $this->assertSame('Africa/Cairo', $settings->timezone());
            $this->assertSame('ar', $settings->defaultLocale());
            $this->assertSame('western', $settings->numberDigits());
        });
    }

    public function test_admin_can_update_localization_settings(): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', $this->validPayload([
            'currency' => 'SAR',
            'timezone' => 'Africa/Cairo',
            'default_locale' => 'en',
            'number_digits' => 'western',
        ]), $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('settings.currency', 'SAR')
            ->assertJsonPath('settings.timezone', 'Africa/Cairo')
            ->assertJsonPath('settings.default_locale', 'en')
            ->assertJsonPath('settings.number_digits', 'western');

        $this->inTenant($tenant, function (): void {
            $settings = app(TenantSettings::class);

            $this->assertSame('SAR', $settings->currency());
            $this->assertSame('Africa/Cairo', $settings->timezone());
            $this->assertSame('en', $settings->defaultLocale());
        });
    }

    public function test_updating_other_settings_does_not_reset_localization(): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', $this->validPayload(['currency' => 'USD']), $this->tenantHeaders($tenant))
            ->assertStatus(200);

        // Older clients that do not send the new keys must not wipe them.
        $this->postJson('/api/v1/settings', $this->validPayload(), $this->tenantHeaders($tenant))
            ->assertStatus(200);

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertJsonPath('settings.currency', 'USD')
            ->assertJsonPath('settings.timezone', 'Africa/Cairo');
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'unknown currency code' => ['currency', 'XYZ'],
            'currency outside allowlist' => ['currency', 'BTC'],
            'lowercase currency' => ['currency', 'egp'],
            'empty currency' => ['currency', ''],
            'currency not a string' => ['currency', ['EGP']],
            'unknown timezone' => ['timezone', 'Mars/Olympus'],
            'utc offset is not an identifier' => ['timezone', '+02:00'],
            'timezone abbreviation' => ['timezone', 'EET'],
            'empty timezone' => ['timezone', ''],
            // Write-side business dates are still stamped in the application timezone, so a
            // tenant may not pick a zone that would split its business day (see TenantSettings).
            'zone other than the application timezone' => ['timezone', 'Asia/Dubai'],
            'gulf zone is not selectable yet' => ['timezone', 'Asia/Riyadh'],
            'unsupported locale' => ['default_locale', 'fr'],
            'locale with region' => ['default_locale', 'ar_EG'],
            'eastern digits are not allowed' => ['number_digits', 'eastern'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected_with_422(string $field, mixed $value): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', $this->validPayload([$field => $value]), $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);

        // Nothing was persisted for the rejected request.
        $this->inTenant($tenant, function () use ($field): void {
            $this->assertFalse(Setting::query()->where('key', $field)->exists());
        });
    }

    public function test_selectable_timezones_are_pinned_to_the_application_timezone(): void
    {
        $this->assertSame([(string) config('app.timezone')], TenantSettings::selectableTimezones());

        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', $this->validPayload([
            'timezone' => (string) config('app.timezone'),
        ]), $this->tenantHeaders($tenant))->assertStatus(200);

        $this->inTenant($tenant, function (): void {
            $this->assertSame((string) config('app.timezone'), app(TenantSettings::class)->timezone());
        });
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', $this->validPayload(['currency' => 'USD']), [
            'Accept' => 'application/json',
            'X-Tenant' => (string) $tenant->getTenantKey(),
        ])->assertStatus(401);
    }

    public function test_user_without_settings_permission_gets_403(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant);

        $this->postJson('/api/v1/settings', $this->validPayload(['currency' => 'USD']), $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(403);

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(403);

        $this->assertTenantCurrency($tenant, 'EGP');
    }

    public function test_settings_of_one_tenant_do_not_leak_into_another(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $this->postJson('/api/v1/settings', $this->validPayload([
            'currency' => 'KWD',
            'default_locale' => 'en',
        ]), $this->tenantHeaders($a))->assertStatus(200);

        $this->getJson('/api/v1/settings', $this->tenantHeaders($b))
            ->assertStatus(200)
            ->assertJsonPath('settings.currency', 'EGP')
            ->assertJsonPath('settings.default_locale', 'ar');

        $this->assertTenantCurrency($a, 'KWD');
        $this->assertTenantCurrency($b, 'EGP');
    }

    public function test_corrupted_stored_values_fall_back_to_defaults(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            Setting::set('currency', 'BTC');
            Setting::set('timezone', 'Nowhere/Land');
            Setting::set('default_locale', 'fr');
            Setting::set('number_digits', 'eastern');

            $settings = app(TenantSettings::class);

            $this->assertSame('EGP', $settings->currency());
            $this->assertSame('Africa/Cairo', $settings->timezone());
            $this->assertSame('ar', $settings->defaultLocale());
            $this->assertSame('western', $settings->numberDigits());
        });
    }

    private function assertTenantCurrency(Tenant $tenant, string $expected): void
    {
        $this->inTenant($tenant, function () use ($expected): void {
            $this->assertSame($expected, app(TenantSettings::class)->currency());
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'محل اختبار الإعدادات',
        ], $overrides);
    }
}
