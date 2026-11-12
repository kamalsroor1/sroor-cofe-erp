<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\PaymentMethod;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StorePosSetting;

/**
 * SET-01: typed, defaulted access to the POS sale settings stored in the tenant `settings` table
 * (payment methods, quick cash denominations, quick discount percents, auto print, copies).
 *
 * Lists are stored as CSV. A stored value that is no longer valid never leaks out: the getter
 * falls back to the documented default (the pre-SET-01 behaviour).
 */
final class PosSaleSettings
{
    /**
     * Payment methods the SERVER accepts on invoices and receipts. null = the shop never saved the
     * setting: the legacy checkout list stays accepted (existing shops are unaffected).
     * Cash is always part of a saved list.
     *
     * @return list<string>|null
     */
    public function storedPaymentMethods(): ?array
    {
        $raw = Setting::get(TenantSettings::KEY_POS_ENABLED_PAYMENT_METHODS);
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $valid = array_map(static fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases());
        $list = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $v): bool => in_array($v, $valid, true),
        )));

        if ($list === []) {
            return null;
        }

        if (! in_array(PaymentMethod::Cash->value, $list, true)) {
            array_unshift($list, PaymentMethod::Cash->value);
        }

        return $list;
    }

    /**
     * Methods the POS offers (saved list, else what the panel always showed).
     *
     * @return list<string>
     */
    public function posPaymentMethods(): array
    {
        return $this->storedPaymentMethods() ?? TenantSettings::DEFAULT_POS_PAYMENT_METHODS;
    }

    /**
     * Methods a new invoice/payment may use: saved list, else every PaymentMethod value.
     *
     * @return list<string>
     */
    public function acceptedPaymentMethods(): array
    {
        return $this->storedPaymentMethods()
            ?? array_map(static fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases());
    }

    public function isMethodAccepted(string $method): bool
    {
        return in_array($method, $this->acceptedPaymentMethods(), true);
    }

    /** @return list<string> */
    public function cashDenominations(): array
    {
        $list = $this->parseDecimals(TenantSettings::KEY_POS_CASH_DENOMINATIONS);
        $list = array_values(array_filter($list, static fn (string $v): bool => bccomp($v, '0', 3) > 0));

        return $list !== [] && count($list) <= TenantSettings::POS_CASH_DENOMINATIONS_MAX
            ? $list
            : TenantSettings::DEFAULT_POS_CASH_DENOMINATIONS;
    }

    /** @return list<string> */
    public function discountPresets(): array
    {
        $list = $this->parseDecimals(TenantSettings::KEY_POS_DISCOUNT_PRESETS);
        $list = array_values(array_filter(
            $list,
            static fn (string $v): bool => bccomp($v, '0', 3) >= 0 && bccomp($v, '100', 3) <= 0,
        ));

        return $list !== [] && count($list) <= TenantSettings::POS_DISCOUNT_PRESETS_MAX
            ? $list
            : TenantSettings::DEFAULT_POS_DISCOUNT_PRESETS;
    }

    public function autoPrint(): string
    {
        $value = Setting::get(TenantSettings::KEY_POS_AUTO_PRINT);

        return $value !== null && in_array($value, TenantSettings::POS_AUTO_PRINT_MODES, true)
            ? $value
            : TenantSettings::DEFAULT_POS_AUTO_PRINT;
    }

    public function receiptCopies(): int
    {
        $value = trim((string) Setting::get(TenantSettings::KEY_POS_RECEIPT_COPIES, ''));

        if (preg_match('/^\d$/', $value) !== 1) {
            return TenantSettings::DEFAULT_POS_RECEIPT_COPIES;
        }

        $copies = (int) $value;

        return $copies >= TenantSettings::POS_RECEIPT_COPIES_MIN && $copies <= TenantSettings::POS_RECEIPT_COPIES_MAX
            ? $copies
            : TenantSettings::DEFAULT_POS_RECEIPT_COPIES;
    }

    /**
     * The highest discount cap any store enforces (stores without their own cap use the default).
     * A preset above it could never be applied anywhere, so settings reject it.
     *
     * @return numeric-string
     */
    public function highestDiscountCap(): string
    {
        $stored = StorePosSetting::query()->whereNotNull('max_discount_percent');
        $highest = (string) ($stored->max('max_discount_percent') ?? '0');
        $storesWithCap = (clone $stored)->count();

        $cap = bcadd($highest, '0', 3);
        if (Store::query()->count() > $storesWithCap && bccomp(StorePosSetting::DEFAULT_MAX_DISCOUNT_PERCENT, $cap, 3) > 0) {
            $cap = StorePosSetting::DEFAULT_MAX_DISCOUNT_PERCENT;
        }

        return $cap;
    }

    /**
     * Payload of /pos/bootstrap (`sale_settings`).
     *
     * @return array{payment_methods: list<string>, cash_denominations: list<string>, discount_presets: list<string>, auto_print: string, receipt_copies: int}
     */
    public function toArray(): array
    {
        return [
            'payment_methods' => $this->posPaymentMethods(),
            'cash_denominations' => $this->cashDenominations(),
            'discount_presets' => $this->discountPresets(),
            'auto_print' => $this->autoPrint(),
            'receipt_copies' => $this->receiptCopies(),
        ];
    }

    /**
     * CSV form of a validated list setting. Methods keep the enum order with cash first; numeric
     * lists are normalised (no trailing zeros), de-duplicated and sorted ascending.
     *
     * @param  array<int|string, mixed>  $values
     */
    public static function serialize(string $key, array $values): string
    {
        $items = array_values(array_filter(array_map(
            static fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : '',
            $values,
        ), static fn (string $v): bool => $v !== ''));

        if ($key === TenantSettings::KEY_POS_ENABLED_PAYMENT_METHODS) {
            $items[] = PaymentMethod::Cash->value;
            $ordered = array_map(static fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases());

            return implode(',', array_values(array_filter($ordered, static fn (string $m): bool => in_array($m, $items, true))));
        }

        if ($key === TenantSettings::KEY_POS_CASH_DENOMINATIONS || $key === TenantSettings::KEY_POS_DISCOUNT_PRESETS) {
            $numbers = array_values(array_unique(array_map(static fn (string $v): string => self::trimDecimal($v), $items)));
            usort($numbers, static fn (string $a, string $b): int => bccomp($a, $b, 3));

            return implode(',', $numbers);
        }

        return implode(',', $items);
    }

    /** '50.500' => '50.5', '100.000' => '100', '0.000' => '0'. */
    private static function trimDecimal(string $value): string
    {
        $fixed = bcadd($value, '0', 3);

        return str_contains($fixed, '.') ? rtrim(rtrim($fixed, '0'), '.') : $fixed;
    }

    /** @return list<string> */
    private function parseDecimals(string $key): array
    {
        $raw = Setting::get($key);
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $out = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if (preg_match('/^\d{1,9}(\.\d{1,3})?$/', $part) !== 1) {
                return [];
            }
            $out[] = self::trimDecimal($part);
        }

        return $out;
    }
}
