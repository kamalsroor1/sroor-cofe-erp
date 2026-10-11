<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\TenantLogoVariant;
use App\Support\Media\BrandAssetSpec;
use App\Support\Media\ImageSanitizer;
use App\Support\Media\SanitizedImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * BRND-5: POST /api/v1/settings/branding/logo/{variant} (multipart, field `file`).
 * settings.manage. The upload is checked by its real content (ImageSanitizer: magic bytes,
 * no SVG/GIF/ICO, 64–2048 px, re-encoded); the `mimes`/`max` rules only fail fast.
 */
class UploadTenantBrandAssetRequest extends FormRequest
{
    private ?SanitizedImage $sanitized = null;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $spec = BrandAssetSpec::logo();

        return [
            'file' => ['required', 'file', 'mimes:'.implode(',', $spec->allowedExtensions), 'max:'.$spec->maxKb],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => __('branding.attributes.logo_file')];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');

                if ($validator->errors()->isNotEmpty() || ! $file instanceof UploadedFile) {
                    return;
                }

                try {
                    $this->sanitized = app(ImageSanitizer::class)->sanitize($file, BrandAssetSpec::logo(), 'file');
                } catch (ValidationException $e) {
                    $validator->errors()->merge($e->errors());
                }
            },
        ];
    }

    public function variant(): TenantLogoVariant
    {
        return TenantLogoVariant::from((string) $this->route('variant'));
    }

    public function sanitizedImage(): SanitizedImage
    {
        if ($this->sanitized === null) {
            throw new LogicException('sanitizedImage() is only available after validation passed.');
        }

        return $this->sanitized;
    }
}
