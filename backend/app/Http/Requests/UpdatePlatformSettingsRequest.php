<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Legacy POST /api/v1/super-admin/settings (deprecated, removed in BRND-11),
 * super_admin.settings.manage + step-up. Same text and format rules as the new
 * PUT /platform-settings (UpdatePlatformBrandingRequest): no markup, RFC e-mail, phone
 * format and length of the stored support_phone.
 */
class UpdatePlatformSettingsRequest extends FormRequest
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
            'platform_name' => ['required', 'string', 'max:100', self::NO_MARKUP],
            'platform_subtitle' => ['nullable', 'string', 'max:255', self::NO_MARKUP],
            'support_email' => ['nullable', 'string', 'email:rfc', 'max:100'],
            'support_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ()\-]{2,29}$/'],
        ];
    }
}
