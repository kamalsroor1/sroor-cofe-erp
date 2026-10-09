<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;

/**
 * SETG-1: typed, defaulted access to the tenant's settings stored in the tenant
 * `settings` key/value table (App\Models\Setting). This is the settings registry: every
 * typed tenant setting has its key constant, default and allowed values here, and
 * UpdateSettingsRequest validates against the same constants.
 *
 * Tenant scope: must be called while tenancy is initialized (tenant DB). The service is
 * stateless — every getter reads through Setting's per-tenant cache, so it is safe to
 * resolve from the container in queued jobs and long-running workers that switch tenants.
 *
 * A stored value that is no longer valid (manual DB edit, list shrinking) never leaks
 * out: the getter falls back to the documented default.
 *
 * Request field = stored key: the settings form posts flat keys (UpdateSettingsAction
 * stores them 1:1), so the catalog names `locale.business_day_cutoff` and
 * `inventory.low_stock.default_threshold` are stored as `business_day_cutoff` and
 * `low_stock_default_threshold`.
 */
final class TenantSettings
{
    public const KEY_CURRENCY = 'currency';

    public const KEY_TIMEZONE = 'timezone';

    public const KEY_DEFAULT_LOCALE = 'default_locale';

    public const KEY_NUMBER_DIGITS = 'number_digits';

    /** SETG-2 ext (catalog `locale.business_day_cutoff`). */
    public const KEY_BUSINESS_DAY_CUTOFF = 'business_day_cutoff';

    /** SETG-10 (catalog `inventory.units`): comma-separated tenant unit list. */
    public const KEY_INVENTORY_UNITS = 'inventory_units';

    /** SETG-10 (catalog `inventory.low_stock.default_threshold`). */
    public const KEY_LOW_STOCK_DEFAULT_THRESHOLD = 'low_stock_default_threshold';

    /** SETG-1 ext (W1 Q5): legal keys printed on the A4 invoice ('' = line hidden). */
    public const KEY_COMMERCIAL_REGISTER = 'commercial_register';

    public const KEY_TAX_REGISTRATION_NO = 'tax_registration_no';

    public const DEFAULT_CURRENCY = 'EGP';

    public const DEFAULT_TIMEZONE = 'Africa/Cairo';

    public const DEFAULT_LOCALE = 'ar';

    public const DEFAULT_NUMBER_DIGITS = 'western';

    public const DEFAULT_BUSINESS_DAY_CUTOFF = '00:00';

    public const DEFAULT_LOW_STOCK_THRESHOLD = '5.000';

    /** Used when the tenant never saved a unit list (same list the settings screen always showed). */
    public const DEFAULT_INVENTORY_UNITS = 'قطعة,علبة,كرتونة,كجم,جرام,شيكارة,طرد,دستة,لتر';

    /** HH:MM on a 24h clock, 00:00 … 23:59. */
    public const CUTOFF_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    /**
     * CTO W1 Q5: the ONLY currencies a tenant may newly select in Phase 1, with the number
     * of decimals used for display (SETG-13). Amounts are always stored DECIMAL(12,3).
     *
     * @var array<string, int>
     */
    public const SELECTABLE_CURRENCY_DECIMALS = [
        'EGP' => 2,
        'SAR' => 2,
        'AED' => 2,
        'KWD' => 3,
        'QAR' => 2,
        'USD' => 2,
    ];

    /**
     * Codes a tenant could select before the W1 Q5 decision. A stored value from this list
     * is still READ (no tenant silently changes currency) but cannot be newly saved.
     *
     * @var array<string, int>
     */
    public const LEGACY_CURRENCY_DECIMALS = [
        'BHD' => 3, 'OMR' => 3, 'JOD' => 3, 'IQD' => 3, 'LBP' => 2,
        'SYP' => 2, 'YER' => 2, 'LYD' => 3, 'SDG' => 2, 'TND' => 3,
        'DZD' => 2, 'MAD' => 2, 'EUR' => 2, 'GBP' => 2,
    ];

    public const SUPPORTED_LOCALES = ['ar', 'en'];

    /** CTO decision (Q8): Western digits (123) everywhere — UI, invoices, prints. */
    public const SUPPORTED_NUMBER_DIGITS = ['western'];

    public function currency(): string
    {
        return $this->pick(self::KEY_CURRENCY, self::DEFAULT_CURRENCY, self::readableCurrencies());
    }

    /** Display decimals of the tenant currency (KWD = 3, the others 2). */
    public function currencyDecimals(): int
    {
        return self::decimalsFor($this->currency());
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
     * SETG-2 ext: the business day of date D is [D + cutoff, D+1 + cutoff) on the tenant
     * clock. '00:00' (default) = the calendar day.
     */
    public function businessDayCutoff(): string
    {
        $value = Setting::get(self::KEY_BUSINESS_DAY_CUTOFF);

        return $value !== null && preg_match(self::CUTOFF_PATTERN, $value) === 1
            ? $value
            : self::DEFAULT_BUSINESS_DAY_CUTOFF;
    }

    /**
     * SETG-10: the tenant's unit list (saved `inventory_units`, picked from the platform
     * `global_system_units` catalog). Order kept, blanks and duplicates dropped.
     *
     * @return list<string>
     */
    public function inventoryUnits(): array
    {
        $units = self::parseUnits((string) Setting::get(self::KEY_INVENTORY_UNITS, ''));

        return $units !== [] ? $units : self::parseUnits(self::DEFAULT_INVENTORY_UNITS);
    }

    /**
     * SETG-10: low-stock threshold for items without their own minimum (min_stock_level
     * NULL or <= 0). A decimal string at scale 3.
     */
    public function lowStockDefaultThreshold(): string
    {
        $value = Setting::get(self::KEY_LOW_STOCK_DEFAULT_THRESHOLD);

        if ($value === null || preg_match('/^\d{1,9}(\.\d{1,3})?$/', trim($value)) !== 1) {
            return self::DEFAULT_LOW_STOCK_THRESHOLD;
        }

        return bcadd(trim($value), '0', 3);
    }

    /**
     * SETG-10: restrict an items query to low-stock rows. An item's own minimum wins; an
     * item with no minimum (NULL or <= 0) uses lowStockDefaultThreshold().
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function whereLowStock(Builder $query, string $quantityColumn = 'current_stock', string $minColumn = 'min_stock_level'): Builder
    {
        $threshold = $this->lowStockDefaultThreshold();

        return $query->where(function (Builder $q) use ($quantityColumn, $minColumn, $threshold): void {
            $q->where(function (Builder $own) use ($quantityColumn, $minColumn): void {
                $own->where($minColumn, '>', 0)->whereColumn($quantityColumn, '<=', $minColumn);
            })->orWhere(function (Builder $fallback) use ($quantityColumn, $minColumn, $threshold): void {
                $fallback->where(function (Builder $none) use ($minColumn): void {
                    $none->whereNull($minColumn)->orWhere($minColumn, '<=', 0);
                })->where($quantityColumn, '<=', $threshold);
            });
        });
    }

    /**
     * @return array{currency: string, currency_decimals: int, timezone: string, default_locale: string, number_digits: string, business_day_cutoff: string, low_stock_default_threshold: string}
     */
    public function toArray(): array
    {
        return [
            self::KEY_CURRENCY => $this->currency(),
            'currency_decimals' => $this->currencyDecimals(),
            self::KEY_TIMEZONE => $this->timezone(),
            self::KEY_DEFAULT_LOCALE => $this->defaultLocale(),
            self::KEY_NUMBER_DIGITS => $this->numberDigits(),
            self::KEY_BUSINESS_DAY_CUTOFF => $this->businessDayCutoff(),
            self::KEY_LOW_STOCK_DEFAULT_THRESHOLD => $this->lowStockDefaultThreshold(),
        ];
    }

    /**
     * Currencies a tenant may newly save (CTO W1 Q5).
     *
     * @return list<string>
     */
    public static function selectableCurrencies(): array
    {
        return array_keys(self::SELECTABLE_CURRENCY_DECIMALS);
    }

    /**
     * Currencies honoured when READ from storage: selectable + legacy.
     *
     * @return list<string>
     */
    public static function readableCurrencies(): array
    {
        return array_keys(self::SELECTABLE_CURRENCY_DECIMALS + self::LEGACY_CURRENCY_DECIMALS);
    }

    public static function decimalsFor(string $currency): int
    {
        return self::SELECTABLE_CURRENCY_DECIMALS[$currency]
            ?? self::LEGACY_CURRENCY_DECIMALS[$currency]
            ?? 2;
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
     * Timezones a tenant may SELECT through the settings API.
     *
     * SETG-2 ext lifted the W1 pin: every document's business date is now stamped through
     * TenantClock::businessDate() (tenant clock + business_day_cutoff), so any IANA zone
     * keeps a tenant's business day whole.
     *
     * @return list<string>
     */
    public static function selectableTimezones(): array
    {
        return self::supportedTimezones();
    }

    /**
     * @return list<string>
     */
    public static function parseUnits(string $raw): array
    {
        $units = [];
        foreach (explode(',', $raw) as $unit) {
            $unit = trim($unit);
            if ($unit !== '' && ! in_array($unit, $units, true)) {
                $units[] = $unit;
            }
        }

        return $units;
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
