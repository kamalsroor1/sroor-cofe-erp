<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TenantLogoVariant;
use App\Http\Requests\Settings\SendTestTelegramRequest;
use App\Services\Branding\TenantBranding;
use App\Services\Settings\TenantSettings;
use App\Support\Media\BrandAssetSpec;
use App\Support\Media\ImageSanitizer;
use App\Support\Media\SanitizedImage;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    /** Legacy logo field of this form without a variant: it means the light logo. */
    public const LEGACY_LOGO_FIELD = 'logo_file';

    /** @var array<string, SanitizedImage> variant value => sanitized upload */
    private array $sanitizedLogos = [];

    public function authorize(): bool
    {
        return (bool) ($this->user() && (
            $this->user()->hasRole('admin')
            || $this->user()->can('roles.manage')
            || $this->user()->can('settings.manage')
        ));
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_subtitle' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'invoice_footer_note' => ['nullable', 'string', 'max:500'],
            'show_print_company_name' => ['sometimes', 'boolean'],
            'show_print_subtitle' => ['sometimes', 'boolean'],
            'show_print_logo' => ['sometimes', 'boolean'],
            'thermal_show_customer_balance' => ['sometimes', 'boolean'],
            'print_show_qr' => ['sometimes', 'boolean'],
            'invoice_primary_color' => ['nullable', 'string', Rule::in(TenantBranding::INVOICE_COLORS)],
            // BRND-5: a palette preset id or a #RRGGBB code (applied as CSS variables by the SPA).
            TenantBranding::KEY_THEME_COLOR => ['nullable', 'string', 'max:50', $this->themeColorRule()],
            // BRND-5: printed receipt header, plain text, one line per "\n" (an array of lines is accepted too).
            TenantBranding::KEY_RECEIPT_HEADER_LINES => ['nullable', 'string', 'max:1000', $this->receiptHeaderRule()],
            // BRND-5: printed receipt footer (falls back to invoice_footer_note when empty).
            TenantBranding::KEY_RECEIPT_FOOTER_TEXT => ['nullable', 'string', 'max:'.TenantBranding::MAX_FOOTER_LENGTH, 'not_regex:'.TenantBranding::MARKUP_PATTERN],
            // SETG-10: the tenant unit list (comma-separated); item create/update validate against it.
            TenantSettings::KEY_INVENTORY_UNITS => ['nullable', 'string', 'max:1000'],
            // SETG-7: write-only secret — blank keeps the stored token, clear_telegram_bot_token removes it.
            'telegram_bot_token' => ['nullable', 'string', 'max:255', SendTestTelegramRequest::BOT_TOKEN_RULE],
            'clear_telegram_bot_token' => ['sometimes', 'boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255', SendTestTelegramRequest::CHAT_ID_RULE],
            // SETG-7 / SETG-1 ext (CTO W1 Q5): legal info printed on the A4 invoice (empty = line hidden).
            TenantSettings::KEY_COMMERCIAL_REGISTER => ['nullable', 'string', 'max:50'],
            TenantSettings::KEY_TAX_REGISTRATION_NO => ['nullable', 'string', 'max:50'],
            'telegram_notifications_enabled' => ['sometimes', 'boolean'],
            // BRND-5: legacy multipart logo fields, now stored per tenant (UploadTenantBrandAssetAction).
            // The real check is ImageSanitizer in after(); these rules only fail fast.
            self::LEGACY_LOGO_FIELD => $this->logoRules(),
            TenantLogoVariant::Light->legacySettingsField() => $this->logoRules(),
            TenantLogoVariant::Dark->legacySettingsField() => $this->logoRules(),

            // SETG-1 tenant localization. Optional so older clients that omit them keep the stored values.
            // SETG-1 ext (CTO W1 Q5): only the 6 Phase-1 currencies can be newly saved; a legacy
            // value already stored may be re-sent unchanged so older tenants can still save the form.
            TenantSettings::KEY_CURRENCY => ['sometimes', 'required', 'string', Rule::in($this->savableCurrencies())],
            // SETG-2 ext: any IANA zone — write-side business dates now come from TenantClock.
            TenantSettings::KEY_TIMEZONE => ['sometimes', 'required', 'string', Rule::in(TenantSettings::selectableTimezones())],
            // SETG-2 ext: business day = [D + cutoff, D+1 + cutoff) on the tenant clock.
            TenantSettings::KEY_BUSINESS_DAY_CUTOFF => ['sometimes', 'required', 'string', 'regex:'.TenantSettings::CUTOFF_PATTERN],
            // SETG-10: low-stock threshold for items without their own minimum.
            TenantSettings::KEY_LOW_STOCK_DEFAULT_THRESHOLD => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            TenantSettings::KEY_DEFAULT_LOCALE => ['sometimes', 'required', 'string', Rule::in(TenantSettings::SUPPORTED_LOCALES)],
            TenantSettings::KEY_NUMBER_DIGITS => ['sometimes', 'required', 'string', Rule::in(TenantSettings::SUPPORTED_NUMBER_DIGITS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            TenantSettings::KEY_CURRENCY => __('settings.currency'),
            TenantSettings::KEY_TIMEZONE => __('settings.timezone'),
            TenantSettings::KEY_DEFAULT_LOCALE => __('settings.default_locale'),
            TenantSettings::KEY_NUMBER_DIGITS => __('settings.number_digits'),
            TenantSettings::KEY_BUSINESS_DAY_CUTOFF => __('settings.business_day_cutoff'),
            TenantSettings::KEY_LOW_STOCK_DEFAULT_THRESHOLD => __('settings.low_stock_default_threshold'),
            TenantSettings::KEY_INVENTORY_UNITS => __('settings.inventory_units'),
            'telegram_bot_token' => __('settings.attr_telegram_bot_token'),
            'telegram_chat_id' => __('settings.attr_telegram_chat_id'),
            TenantSettings::KEY_COMMERCIAL_REGISTER => __('settings.commercial_register'),
            TenantSettings::KEY_TAX_REGISTRATION_NO => __('settings.tax_registration_no'),
            TenantBranding::KEY_RECEIPT_HEADER_LINES => __('branding.attributes.receipt_header_lines'),
            TenantBranding::KEY_RECEIPT_FOOTER_TEXT => __('branding.attributes.receipt_footer_text'),
            TenantBranding::KEY_THEME_COLOR => __('branding.attributes.system_theme_color'),
            self::LEGACY_LOGO_FIELD => __('branding.attributes.logo_file'),
            TenantLogoVariant::Light->legacySettingsField() => __('branding.attributes.logo_file'),
            TenantLogoVariant::Dark->legacySettingsField() => __('branding.attributes.logo_file'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            TenantSettings::KEY_BUSINESS_DAY_CUTOFF.'.regex' => __('settings.business_day_cutoff_invalid'),
            TenantBranding::KEY_RECEIPT_FOOTER_TEXT.'.not_regex' => __('branding.receipt_text_no_markup'),
        ];
    }

    /**
     * BRND-5: sanitize the legacy logo uploads (magic bytes, no SVG/GIF/ICO, dimensions,
     * re-encode) during validation, so a bad image is a 422 and nothing is saved.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $sanitizer = app(ImageSanitizer::class);

                foreach ($this->legacyLogoFields() as $variant => $field) {
                    $file = $this->file($field);

                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    try {
                        $this->sanitizedLogos[$variant] = $sanitizer->sanitize($file, BrandAssetSpec::logo(), $field);
                    } catch (ValidationException $e) {
                        $validator->errors()->merge($e->errors());
                    }
                }
            },
        ];
    }

    /**
     * Sanitized legacy logo uploads, keyed by TenantLogoVariant value.
     *
     * @return array<string, SanitizedImage>
     */
    public function sanitizedLogos(): array
    {
        return $this->sanitizedLogos;
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input(TenantBranding::KEY_RECEIPT_HEADER_LINES);

        if (is_array($lines)) {
            $this->merge([
                TenantBranding::KEY_RECEIPT_HEADER_LINES => implode("\n", array_map(
                    static fn (mixed $line): string => is_scalar($line) ? (string) $line : '',
                    $lines,
                )),
            ]);
        }
    }

    /**
     * variant value => form field; `logo_file` only when no explicit light file was sent.
     *
     * @return array<string, string>
     */
    private function legacyLogoFields(): array
    {
        $light = TenantLogoVariant::Light->legacySettingsField();

        return [
            TenantLogoVariant::Light->value => $this->hasFile($light) ? $light : self::LEGACY_LOGO_FIELD,
            TenantLogoVariant::Dark->value => TenantLogoVariant::Dark->legacySettingsField(),
        ];
    }

    /**
     * @return list<string>
     */
    private function logoRules(): array
    {
        $spec = BrandAssetSpec::logo();

        return ['nullable', 'file', 'mimes:'.implode(',', $spec->allowedExtensions), 'max:'.$spec->maxKb];
    }

    private function themeColorRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && ! TenantBranding::isValidThemeColor($value)) {
                $fail(__('branding.theme_color_invalid'));
            }
        };
    }

    private function receiptHeaderRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if (preg_match(TenantBranding::MARKUP_PATTERN, $value) === 1) {
                $fail(__('branding.receipt_text_no_markup'));

                return;
            }

            $lines = array_values(array_filter(
                array_map('trim', preg_split('/\R/u', $value) ?: []),
                static fn (string $line): bool => $line !== '',
            ));

            if (count($lines) > TenantBranding::MAX_HEADER_LINES) {
                $fail(__('branding.receipt_header_too_many_lines', ['max' => TenantBranding::MAX_HEADER_LINES]));

                return;
            }

            foreach ($lines as $line) {
                if (mb_strlen($line) > TenantBranding::MAX_HEADER_LINE_LENGTH) {
                    $fail(__('branding.receipt_header_line_too_long', ['max' => TenantBranding::MAX_HEADER_LINE_LENGTH]));

                    return;
                }
            }
        };
    }

    /**
     * The 6 selectable currencies, plus the tenant's currently stored legacy currency (if
     * any) so re-saving an unchanged form never fails.
     *
     * @return list<string>
     */
    private function savableCurrencies(): array
    {
        $allowed = TenantSettings::selectableCurrencies();
        $stored = app(TenantSettings::class)->currency();

        if (! in_array($stored, $allowed, true)) {
            $allowed[] = $stored;
        }

        return $allowed;
    }
}
