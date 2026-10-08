<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use DateTimeZone;

/**
 * SETG-1: typed, defaulted access to the tenant's localization settings stored in the
 * tenant `settings` key/value table (App\Models\Setting).
 *
 * Tenant scope: must be called while tenancy is initialized (tenant DB). The service is
 * stateless — every getter reads through Setting's per-tenant cache, so it is safe to
 * resolve from the container in queued jobs and long-running workers that switch tenants.
 *
 * A stored value that is no longer valid (manual DB edit, list shrinking) never leaks
 * out: the getter falls back to the documented default.
 */
final class TenantSettings
{
    public const KEY_CURRENCY = 'currency';

    public const KEY_TIMEZONE = 'timezone';

    public const KEY_DEFAULT_LOCALE = 'default_locale';

    public const KEY_NUMBER_DIGITS = 'number_digits';

    public const DEFAULT_CURRENCY = 'EGP';

    public const DEFAULT_TIMEZONE = 'Africa/Cairo';

    public const DEFAULT_LOCALE = 'ar';

    public const DEFAULT_NUMBER_DIGITS = 'western';

    /**
     * ISO-4217 codes a tenant may choose. EGP is the product default; the rest cover the
     * Arab region and the common reference currencies. Amounts stay DECIMAL(12,3), which
     * also fits the 3-decimal currencies (KWD, BHD, OMR, JOD, IQD, LYD, TND).
     *
     * TODO(CTO): confirm the allowlist of selectable currencies for Phase 1.
     */
    public const SUPPORTED_CURRENCIES = [
        'EGP', 'SAR', 'AED', 'KWD', 'QAR', 'BHD', 'OMR', 'JOD', 'IQD', 'LBP',
        'SYP', 'YER', 'LYD', 'SDG', 'TND', 'DZD', 'MAD', 'USD', 'EUR', 'GBP',
    ];

    public const SUPPORTED_LOCALES = ['ar', 'en'];

    /** CTO decision (Q8): Western digits (123) everywhere — UI, invoices, prints. */
    public const SUPPORTED_NUMBER_DIGITS = ['western'];

    public function currency(): string
    {
        return $this->pick(self::KEY_CURRENCY, self::DEFAULT_CURRENCY, self::SUPPORTED_CURRENCIES);
    }

    public function timezone(): string
    {
        return $this->pick(self::KEY_TIMEZONE, self::DEFAULT_TIMEZONE, self::supportedTimezones());
    }

    public function defaultLocale(): string
    {
        return $this->pick(self::KEY_DEFAULT_LOCALE, self::DEFAULT_LOCALE, self::SUPPORTED_LOCALES);
    }

    public function numberDigits(): string
    {
        return $this->pick(self::KEY_NUMBER_DIGITS, self::DEFAULT_NUMBER_DIGITS, self::SUPPORTED_NUMBER_DIGITS);
    }

    /**
     * @return array{currency: string, timezone: string, default_locale: string, number_digits: string}
     */
    public function toArray(): array
    {
        return [
            self::KEY_CURRENCY => $this->currency(),
            self::KEY_TIMEZONE => $this->timezone(),
            self::KEY_DEFAULT_LOCALE => $this->defaultLocale(),
            self::KEY_NUMBER_DIGITS => $this->numberDigits(),
        ];
    }

    /**
     * Valid IANA identifiers (no abbreviations like "EET", no raw offsets like "+02:00").
     *
     * @return list<string>
     */
    public static function supportedTimezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    /**
     * Timezones a tenant may currently SELECT through the settings API (write side).
     *
     * The read side (TenantClock: reports, dashboard, daily journal) already honours any
     * IANA zone, but business DATE columns (invoice_date, payment_date, expense_date…) are
     * still stamped with the server date (now() in config('app.timezone')) when the client
     * omits them. Letting a tenant pick another zone would split its business day: a sale
     * at 00:30 Asia/Dubai is 23:30 Cairo and would land in yesterday's report.
     *
     * TODO(CTO): open the full supportedTimezones() list once every write path stamps its
     * business date with TenantClock::today() (POS/InvoiceService, blender, payments,
     * returns, purchases, transfers, treasury) — owned by other lanes.
     *
     * @return list<string>
     */
    public static function selectableTimezones(): array
    {
        return [(string) config('app.timezone')];
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function pick(string $key, string $default, array $allowed): string
    {
        $value = Setting::get($key);

        return $value !== null && in_array($value, $allowed, true) ? $value : $default;
    }
}
