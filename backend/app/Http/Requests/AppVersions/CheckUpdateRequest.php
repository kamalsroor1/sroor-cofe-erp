<?php

declare(strict_types=1);

namespace App\Http\Requests\AppVersions;

use App\Enums\AppPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/app/check-update (+ /app/version alias) and GET /api/v1/app/download-apk
 * (+ /app/download-latest-apk alias). Public, rate-limited (throttle:public-api).
 *
 *   platform      android|windows (default android). iOS is not a platform (CTO Q7): 422.
 *   version_code  check-update: the INSTALLED build's code; download-apk: the release to
 *                 download (omitted = the latest advertised release).
 *   version_name / current_version  the installed build's name (echoed back only).
 */
final class CheckUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public OTA update check & download endpoint
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['sometimes', 'string', Rule::enum(AppPlatform::class)],
            // 0 is accepted: desktop builds before APP-7 send version_code=0 ("unknown"); they
            // must still be offered the update instead of getting a 422 forever.
            'version_code' => ['sometimes', 'integer', 'min:0', 'max:'.StoreAppVersionRequest::MAX_VERSION_CODE],
            'version_name' => ['sometimes', 'string', 'max:50'],
            'current_version' => ['sometimes', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platform.enum' => (string) __('app_releases.platform_not_supported'),
        ];
    }
}
