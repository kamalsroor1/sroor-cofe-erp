<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Enums\PlatformAssetSlot;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * POST /api/v1/super-admin/platform-settings/assets/{slot} (BRND-2), multipart `file`.
 *
 * Only presence and the size ceiling are checked here; the real type/dimension checks and
 * the re-encode are ImageSanitizer's job (magic bytes, never the client mime or name).
 */
final class UploadPlatformAssetRequest extends FormRequest
{
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
            'file' => ['required', 'file', 'max:'.$this->slot()->spec()->maxKb],
        ];
    }

    /** The route constrains {slot} to PlatformAssetSlot values (anything else is a 404). */
    public function slot(): PlatformAssetSlot
    {
        return PlatformAssetSlot::from((string) $this->route('slot'));
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('file');

        // Guaranteed by the `file` rule; kept for the type system.
        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
