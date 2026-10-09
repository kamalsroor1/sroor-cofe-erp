<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Settings\TenantSettings;
use DateTimeZone;
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
            ->assertJsonPath('settings.currency_decimals', 2)
            ->assertJsonPath('settings.timezone', 'Africa/Cairo')
            ->assertJsonPath('settings.default_locale', 'ar')
            ->assertJsonPath('settings.number_digits', 'western')
            ->assertJsonPath('settings.business_day_cutoff', '00:00')
            ->assertJsonPath('settings.low_stock_default_threshold', '5.000');

        $this->inTenant($tenant, function (): void {
            $settings = app(TenantSettings::class);

            $this->assertSame('EGP', $settings->currency());
            $this->assertSame(2, $settings->currencyDecimals());
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
            // CTO W1 Q5: only EGP, SAR, AED, KWD, QAR, USD can be newly saved.
            'legacy euro cannot be newly saved' => ['currency', 'EUR'],
            'legacy bahraini dinar cannot be newly saved' => ['currency', 'BHD'],
            'legacy pound sterling cannot be newly saved' => ['currency', 'GBP'],
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

    /**
     * SETG-2 ext lifted the W1 pin: write-side business dates come from TenantClock, so any
     * IANA zone is selectable.
     */
    public function test_any_iana_timezone_is_selectable(): void
    {
        $this->assertSame(DateTimeZone::listIdentifiers(), TenantSettings::selectableTimezones());

        $tenant = $this->createTenant();

        foreach (['Asia/Dubai', 'Asia/Riyadh', (string) config('app.timezone')] as $zone) {
            $this->postJson('/api/v1/settings', $this->validPayload(['timezone' => $zone]), $this->tenantHeaders($tenant))
                ->assertStatus(200);

            $this->inTenant($tenant, function () use ($zone): void {
                $this->assertSame($zone, app(TenantSettings::class)->timezone());
            });
        }
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function phaseOneCurrencies(): array
    {
        return [
            'EGP' => ['EGP', 2],
            'SAR' => ['SAR', 2],
            'AED' => ['AED', 2],
            'KWD' => ['KWD', 3],
            'QAR' => ['QAR', 2],
            'USD' => ['USD', 2],
        ];
    }

    #[DataProvider('phaseOneCurrencies')]
    public function test_phase_one_currencies_save_with_their_display_decimals(string $currency, int $decimals): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', $this->validPayload(['currency' => $currency]), $this->tenantHeaders($tenant))
            ->assertStatus(200);

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertJsonPath('settings.currency', $currency)
            ->assertJsonPath('settings.currency_decimals', $decimals);

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.system.currency', $currency)
            ->assertJsonPath('data.system.currency_decimals', $decimals);
    }

    public function test_the_selectable_currency_list_is_exactly_the_cto_list(): void
    {
        $this->assertSame(['EGP', 'SAR', 'AED', 'KWD', 'QAR', 'USD'], TenantSettings::selectableCurrencies());
    }

    public function test_a_stored_legacy_currency_is_still_read_but_cannot_be_newly_chosen(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('currency', 'BHD'));

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('settings.currency', 'BHD')
            ->assertJsonPath('settings.currency_decimals', 3);

        // Re-saving the form unchanged keeps working.
        $this->postJson('/api/v1/settings', $this->validPayload(['currency' => 'BHD']), $this->tenantHeaders($tenant))
            ->assertStatus(200);

        // Another legacy code is rejected; a Phase-1 code is accepted.
        $this->postJson('/api/v1/settings', $this->validPayload(['currency' => 'EUR']), $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
        $this->assertTenantCurrency($tenant, 'BHD');

        $this->postJson('/api/v1/settings', $this->validPayload(['currency' => 'KWD']), $this->tenantHeaders($tenant))
            ->assertStatus(200);
        $this->assertTenantCurrency($tenant, 'KWD');

        // Once switched away, the legacy code can no longer be chosen again.
        $this->postJson('/api/v1/settings', $this->validPayload(['currency' => 'BHD']), $this->tenantHeaders($tenant))
            ->assertStatus(422);
    }

    public function test_legal_keys_use_the_registry_names(): void
    {
        $this->assertSame('commercial_register', TenantSettings::KEY_COMMERCIAL_REGISTER);
        $this->assertSame('tax_registration_no', TenantSettings::KEY_TAX_REGISTRATION_NO);

        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', $this->validPayload([
            'commercial_register' => '12345',
            'tax_registration_no' => '987-654-321',
        ]), $this->tenantHeaders($tenant))->assertStatus(200);

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.system.commercial_register', '12345')
            ->assertJsonPath('data.system.tax_registration_no', '987-654-321');
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
