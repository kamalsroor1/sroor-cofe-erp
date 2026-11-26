<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\DTOs\Branding\PlatformBrandingDTO;
use App\Enums\Billing\BillingPaymentMethod;

/**
 * #232 spec F + UI spec 2b / 6b: the manual payment methods a shop can pay with, and the details it
 * sees for each, built from the platform settings. One definition for both readers:
 *  - forShops(): ONLY fully configured methods (every required detail filled), in the order
 *    Vodafone Cash, InstaPay, bank transfer; each `{key, label, details:[{key,label,value,copyable}],
 *    note}` with only the filled details (the optional branch is listed when filled, not copyable);
 *  - statuses(): the super-admin's computed per-method status, `visible` | `missing` (some required
 *    detail empty, listed) | `empty` (nothing of that method filled). Never a toggle: unfilled = hidden.
 * Values are read through PlatformBranding (stored, trimmed, no config fallback); nothing else of
 * the platform settings is exposed.
 */
final class PaymentMethodDetails
{
    /**
     * method => [details: list<[detail key, setting key, required, copyable]>, note: setting key]
     *
     * @var array<string, array{details: list<array{0: string, 1: string, 2: bool, 3: bool}>, note: string}>
     */
    private const METHODS = [
        'vodafone_cash' => [
            'details' => [['number', 'billing_vodafone_cash_number', true, true]],
            'note' => 'billing_vodafone_cash_note',
        ],
        'instapay' => [
            'details' => [['address', 'billing_instapay_address', true, true]],
            'note' => 'billing_instapay_note',
        ],
        'bank_transfer' => [
            'details' => [
                ['bank_name', 'billing_bank_name', true, true],
                ['account_holder', 'billing_bank_account_holder', true, true],
                ['account_number', 'billing_bank_account_number', true, true],
                ['iban', 'billing_bank_iban', true, true],
                ['branch', 'billing_bank_branch', false, false],
            ],
            'note' => 'billing_bank_note',
        ],
    ];

    /** Setting key => billing.console.settings label key, for the "missing :fields" status. */
    private const SETTING_LABELS = [
        'billing_vodafone_cash_number' => 'vodafone_cash_number',
        'billing_instapay_address' => 'instapay_address',
        'billing_bank_name' => 'bank_name',
        'billing_bank_account_holder' => 'bank_account_holder',
        'billing_bank_account_number' => 'bank_account_number',
        'billing_bank_iban' => 'bank_iban',
    ];

    public const STATUS_VISIBLE = 'visible';

    public const STATUS_MISSING = 'missing';

    public const STATUS_EMPTY = 'empty';

    /**
     * @return list<array{key: string, label: string, details: list<array{key: string, label: string, value: string, copyable: bool}>, note: string|null}>
     */
    public function forShops(PlatformBrandingDTO $branding): array
    {
        $values = $branding->toArray();
        $methods = [];

        foreach (self::METHODS as $method => $definition) {
            if ($this->missing($definition, $values) !== []) {
                continue;
            }

            $details = [];
            foreach ($definition['details'] as [$key, $setting, , $copyable]) {
                $value = $this->value($values, $setting);
                if ($value === '') {
                    continue;
                }

                $details[] = [
                    'key' => $key,
                    'label' => (string) __('billing.payment_method_details.'.$method.'.'.$key),
                    'value' => $value,
                    'copyable' => $copyable,
                ];
            }

            $note = $this->value($values, $definition['note']);

            $methods[] = [
                'key' => $method,
                'label' => BillingPaymentMethod::from($method)->label(),
                'details' => $details,
                'note' => $note === '' ? null : $note,
            ];
        }

        return $methods;
    }

    /**
     * @return array<string, array{status: string, visible: bool, missing: list<string>, missing_labels: list<string>}>
     */
    public function statuses(PlatformBrandingDTO $branding): array
    {
        $values = $branding->toArray();
        $statuses = [];

        foreach (self::METHODS as $method => $definition) {
            $missing = $this->missing($definition, $values);
            $filled = $this->value($values, $definition['note']) !== '';
            foreach ($definition['details'] as [, $setting]) {
                $filled = $filled || $this->value($values, $setting) !== '';
            }

            $statuses[$method] = [
                'status' => $missing === [] ? self::STATUS_VISIBLE : ($filled ? self::STATUS_MISSING : self::STATUS_EMPTY),
                'visible' => $missing === [],
                'missing' => $missing,
                'missing_labels' => array_map(
                    static fn (string $setting): string => (string) __('billing.console.settings.'.self::SETTING_LABELS[$setting]),
                    $missing,
                ),
            ];
        }

        return $statuses;
    }

    /**
     * Setting keys of the method's REQUIRED details that are empty.
     *
     * @param  array{details: list<array{0: string, 1: string, 2: bool, 3: bool}>, note: string}  $definition
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function missing(array $definition, array $values): array
    {
        $missing = [];
        foreach ($definition['details'] as [, $setting, $required]) {
            if ($required && $this->value($values, $setting) === '') {
                $missing[] = $setting;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function value(array $values, string $setting): string
    {
        $value = $values[$setting] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
