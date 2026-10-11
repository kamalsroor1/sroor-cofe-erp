<?php

declare(strict_types=1);

namespace App\Http\Requests\AppVersions;

use App\Enums\AppPlatform;
use App\Enums\CentralPermission;
use App\Models\AppVersion;
use App\Support\AppReleases\ReleaseBinaryInspector;
use App\Support\AppReleases\ReleaseBinaryReport;
use App\Support\AppReleases\SemverCode;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * POST /api/v1/super-admin/app-versions (multipart). APP-3 extension (W2 batch 5).
 *
 * Field rules first; then after(), only when they all passed:
 *   - version_code strictly above every release of the platform (version_not_newer);
 *   - Windows: version_code == SemverCode(version_name) (windows_code_mismatch), so the
 *     desktop app, which only knows its semver, compares like the server;
 *   - the client extension matches the platform (extension_mismatch);
 *   - ReleaseBinaryInspector on the uploaded bytes (invalid_binary + the reason).
 * The binary is the thing every installed app will run: nothing about it is trusted from
 * the client (name, extension, mime header).
 */
final class StoreAppVersionRequest extends FormRequest
{
    public const MAX_VERSION_CODE = 2147483647;

    /** 150 MB, in kilobytes (Laravel `max` on files). */
    // The apk_file rule keeps the literal (scripts/ops/tests/run-tests.sh checks it against nginx/php limits).
    public const MAX_FILE_KB = 153600;

    private ?ReleaseBinaryReport $binaryReport = null;

    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::AppVersionsManage);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', Rule::enum(AppPlatform::class)],
            'version_name' => ['required', 'string', 'max:11', 'regex:'.SemverCode::PATTERN],
            'version_code' => [
                'required', 'integer', 'min:1', 'max:'.self::MAX_VERSION_CODE,
                Rule::unique(AppVersion::class, 'version_code')->where('platform', (string) $this->input('platform')),
            ],
            'min_version_code' => ['sometimes', 'integer', 'min:1', 'lte:version_code'],
            'is_force_update' => ['sometimes', 'boolean'],
            'release_notes_ar' => ['required', 'string', 'max:5000'],
            'release_notes_en' => ['nullable', 'string', 'max:5000'],
            'apk_file' => ['required', 'file', 'max:153600', 'extensions:apk,exe'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platform.enum' => (string) __('app_releases.platform_not_supported'),
            'version_name.regex' => (string) __('app_releases.version_name_format'),
            'version_name.max' => (string) __('app_releases.version_name_format'),
            'version_code.unique' => (string) __('app_releases.version_code_taken', ['platform' => $this->platformLabel()]),
            'min_version_code.lte' => (string) __('app_releases.min_version_too_high'),
            'apk_file.extensions' => (string) __('app_releases.extension_mismatch'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $platform = AppPlatform::from((string) $this->input('platform'));
                $code = (int) $this->input('version_code');
                $name = (string) $this->input('version_name');

                $max = AppVersion::query()->forPlatform($platform->value)->max('version_code');
                if ($max !== null && $code <= (int) $max) {
                    $validator->errors()->add('version_code', (string) __('app_releases.version_not_newer', [
                        'platform' => $this->platformLabel(),
                        'max' => (int) $max,
                    ]));

                    return;
                }

                if ($platform === AppPlatform::Windows && SemverCode::fromName($name) !== $code) {
                    $validator->errors()->add('version_code', (string) __('app_releases.windows_code_mismatch', [
                        'name' => $name,
                        'code' => SemverCode::fromName($name),
                    ]));

                    return;
                }

                $file = $this->file('apk_file');
                if (! $file instanceof UploadedFile
                    || strtolower($file->getClientOriginalExtension()) !== $platform->extension()) {
                    $validator->errors()->add('apk_file', (string) __('app_releases.extension_mismatch'));

                    return;
                }

                $report = app(ReleaseBinaryInspector::class)->inspect((string) $file->getRealPath(), $platform);
                if (! $report->isValid()) {
                    $validator->errors()->add('apk_file', (string) __('app_releases.invalid_binary'));
                    $validator->errors()->add('apk_file', (string) __('app_releases.binary_errors.'.$report->failure));

                    return;
                }

                $this->binaryReport = $report;
            },
        ];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('apk_file');

        // Guaranteed by the `required|file` rules; kept for the type system.
        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }

    /** The inspector's verdict on the uploaded binary (always valid once validation passed). */
    public function binaryReport(): ReleaseBinaryReport
    {
        if ($this->binaryReport === null) {
            throw new LogicException('binaryReport() is only available after successful validation.');
        }

        return $this->binaryReport;
    }

    private function platformLabel(): string
    {
        $platform = AppPlatform::tryFrom((string) $this->input('platform'));

        return $platform === null ? '' : (string) __('app_releases.platforms.'.$platform->value);
    }
}
