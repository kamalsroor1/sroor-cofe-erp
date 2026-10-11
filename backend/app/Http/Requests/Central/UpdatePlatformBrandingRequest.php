<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\DTOs\Central\PlatformSettingsDTO;
use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PUT /api/v1/super-admin/platform-settings (BRND-2), super_admin.settings.manage + step-up.
 *
 * Strict partial update: only PlatformSettingsDTO::EDITABLE keys, each optional; any other
 * key (including the asset slots, which have their own endpoints) is a 422, and so is an
 * empty body. A nullable field sent as null/'' resets it to the config default; name and
 * short_name can be changed but never blanked. No markup in text values.
 */
final class UpdatePlatformBrandingRequest extends FormRequest
{
    private const NO_MARKUP = 'not_regex:/[<>]/';

    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::SettingsManage);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', self::NO_MARKUP],
            'short_name' => ['sometimes', 'required', 'string', 'max:30', self::NO_MARKUP],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255', self::NO_MARKUP],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:150', self::NO_MARKUP],
            'primary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'support_email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:100'],
            'support_phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ()\-]{2,29}$/'],
            'website_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url:https,http'],
            'powered_by_enabled' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sent = array_map('strval', array_keys($this->all()));

                foreach (array_diff($sent, PlatformSettingsDTO::EDITABLE) as $unknown) {
                    $validator->errors()->add($unknown, __('super.platform_branding.unknown_field'));
                }

                if (array_intersect($sent, PlatformSettingsDTO::EDITABLE) === []) {
                    $validator->errors()->add('settings', __('super.platform_branding.nothing_to_update'));
                }
            },
        ];
    }
}
