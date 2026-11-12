<?php

declare(strict_types=1);

namespace App\DTOs\Central;

use App\DTOs\Branding\UpdateLegacyPlatformSettingsDTO;
use App\Enums\PlatformSettingKey;

/**
 * BRND-2: a partial update of the platform settings (PUT /api/v1/super-admin/platform-settings).
 *
 * Absent vs null matters:
 *  - a key that was NOT sent is not in $changes (left unchanged);
 *  - a key sent as null is in $changes with null (reset to the config/branding.php default).
 *
 * Asset keys (logos, favicon, app icon) are never accepted here: they change only through
 * the asset upload/delete endpoints.
 */
final class PlatformSettingsDTO
{
    /** Keys PUT may change, in PlatformSettingKey values. */
    public const EDITABLE = [
        'name',
        'short_name',
        'subtitle',
        'legal_name',
        'primary_color',
        'support_email',
        'support_phone',
        'website_url',
        'powered_by_enabled',
        'billing_instapay_address',
        'billing_vodafone_cash_number',
        'billing_account_name',
        'billing_payment_note',
        'billing_tax_rate',
        'signup_trial_days',
    ];

    /**
     * @param  array<string, string|bool|null>  $changes  PlatformSettingKey value => new value (null = reset)
     */
    public function __construct(public readonly array $changes) {}

    /**
     * @param  array<string, mixed>  $data  validated UpdatePlatformBrandingRequest data
     */
    public static function fromArray(array $data): self
    {
        $changes = [];

        foreach (self::EDITABLE as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if ($key === PlatformSettingKey::PoweredByEnabled->value) {
                $changes[$key] = filter_var($value, FILTER_VALIDATE_BOOL);

                continue;
            }

            $value = $value === null ? null : trim((string) $value);

            if ($key === PlatformSettingKey::SignupTrialDays->value && $value !== null && $value !== '') {
                // Stored as a plain whole number ("07" -> "7").
                $value = (string) (int) $value;
            }

            if ($key === PlatformSettingKey::BillingTaxRate->value && $value !== null && $value !== '') {
                // Stored as a clean 3-decimal string (bcmath, no float).
                $value = bcadd($value, '0', 3);
            }

            $changes[$key] = $value === '' ? null : $value;
        }

        return new self($changes);
    }

    /** The legacy screen (POST /super-admin/settings): an empty optional field means "unchanged". */
    public static function fromLegacy(UpdateLegacyPlatformSettingsDTO $legacy): self
    {
        return new self($legacy->toArray());
    }

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function toArray(): array
    {
        return $this->changes;
    }
}
