<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Settings\TenantSettings;
use Illuminate\Support\Facades\App;
use Tests\TenantTestCase;

/**
 * FE-05: tenant-facing text (receipts, shift messages, prints) shows the tenant's currency
 * symbol, not a hardcoded Egyptian pound.
 */
final class TenantCurrencyLabelTest extends TenantTestCase
{
    public function test_label_follows_tenant_currency_and_locale(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $settings = app(TenantSettings::class);

            App::setLocale('ar');
            $this->assertSame('ج.م', $settings->currencyLabel());

            Setting::set('currency', 'SAR');
            $this->assertSame('ر.س', $settings->currencyLabel());

            App::setLocale('en');
            $this->assertSame('SAR', $settings->currencyLabel());
        });
    }

    public function test_every_selectable_currency_has_a_symbol_in_both_locales(): void
    {
        foreach (array_keys(TenantSettings::SELECTABLE_CURRENCY_DECIMALS) as $code) {
            foreach (['ar', 'en'] as $locale) {
                $this->assertNotSame(
                    'common.currencies.'.$code,
                    __('common.currencies.'.$code, [], $locale),
                    "{$code} missing in {$locale}",
                );
            }
        }
    }
}
