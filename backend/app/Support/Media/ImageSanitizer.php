<?php

declare(strict_types=1);

namespace App\Support\Media;

use finfo;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Turns an untrusted brand-image upload into a clean, re-encoded raster image.
 *
 * - The type comes from the magic bytes (finfo), never from the client name, extension or Content-Type.
 * - SVG, GIF and ICO are always refused, whatever the spec says.
 * - Dimensions are read from the header (getimagesizefromstring) before any pixel decoding,
 *   so decompression bombs are refused without allocating the bitmap.
 * - The image is decoded and re-encoded by GD in its own format, which drops EXIF/XMP,
 *   text chunks, ICC profiles and anything appended after the image data (polyglots).
 */
final class ImageSanitizer
{
    /** Hard ceiling on decoded pixels, independent of the spec (decompression-bomb guard). */
    public const MAX_PIXELS = 4096 * 4096;

    private const JPEG_QUALITY = 90;

    private const WEBP_QUALITY = 90;

    /** Raster formats this sanitizer can re-encode, mime => output extension. */
    private const ENCODABLE = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /** Never accepted, even if a spec lists them. */
    private const FORBIDDEN_MIMES = [
        'image/gif',
        'image/x-icon',
        'image/vnd.microsoft.icon',
        'image/ico',
    ];

    public function sanitize(UploadedFile $file, BrandAssetSpec $spec, string $attribute = 'file'): SanitizedImage
    {
        $bytes = $this->readBytes($file, $attribute);

        $maxBytes = $spec->maxKb * 1024;
        if (strlen($bytes) > $maxBytes || ($file->getSize() !== false && $file->getSize() > $maxBytes)) {
            $this->fail($attribute, __('media.too_large', ['max' => $spec->maxKb]));
        }

        $mime = $this->detectMime($bytes);

        if ($this->isSvg($mime, $bytes)) {
            $this->fail($attribute, __('media.svg_forbidden'));
        }

        if (in_array($mime, self::FORBIDDEN_MIMES, true)
            || ! isset(self::ENCODABLE[$mime])
            || ! in_array($mime, $spec->allowedMimes, true)) {
            $this->fail($attribute, $this->typeNotAllowedMessage($spec));
        }

        [$width, $height] = $this->headerDimensions($bytes, $mime, $attribute);

        if ($width * $height > self::MAX_PIXELS) {
            $this->fail($attribute, $this->badDimensionsMessage($spec));
        }

        if ($width < $spec->minW || $height < $spec->minH || $width > $spec->maxW || $height > $spec->maxH) {
            $this->fail($attribute, $this->badDimensionsMessage($spec));
        }

        if ($spec->square && $width !== $height) {
            $this->fail($attribute, __('media.must_be_square'));
        }

        $binary = $this->reencode($bytes, $mime, $width, $height, $attribute);

        return new SanitizedImage(
            binary: $binary,
            extension: self::ENCODABLE[$mime],
            mime: $mime,
            width: $width,
            height: $height,
            sha256: hash('sha256', $binary),
        );
    }

    private function readBytes(UploadedFile $file, string $attribute): string
    {
        if (! $file->isValid()) {
            $this->fail($attribute, __('media.invalid_image'));
        }

        $path = $file->getRealPath();
        $bytes = $path === false ? false : $this->quietly(static fn () => file_get_contents($path));

        if (! is_string($bytes) || $bytes === '') {
            $this->fail($attribute, __('media.invalid_image'));
        }

        return $bytes;
    }

    private function detectMime(string $bytes): string
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        return is_string($mime) ? strtolower($mime) : 'application/octet-stream';
    }

    /**
     * finfo labels most SVGs image/svg+xml, but an SVG without an XML prolog (or renamed .png)
     * can come back as text/xml, text/plain or text/html. Any non-raster type whose head
     * contains an <svg element is treated as SVG so the user gets the specific message.
     */
    private function isSvg(string $mime, string $bytes): bool
    {
        if ($mime === 'image/svg+xml') {
            return true;
        }

        if (isset(self::ENCODABLE[$mime])) {
            return false;
        }

        return stripos(substr($bytes, 0, 4096), '<svg') !== false;
    }

    /**
     * Reads width/height from the image header only, without decoding pixels.
     *
     * @return array{0: int, 1: int}
     */
    private function headerDimensions(string $bytes, string $mime, string $attribute): array
    {
        $info = $this->quietly(static fn () => getimagesizefromstring($bytes));

        if (! is_array($info) || $info['mime'] !== $mime) {
            $this->fail($attribute, __('media.invalid_image'));
        }

        $width = $info[0];
        $height = $info[1];

        if ($width < 1 || $height < 1) {
            $this->fail($attribute, __('media.invalid_image'));
        }

        return [$width, $height];
    }

    private function reencode(string $bytes, string $mime, int $width, int $height, string $attribute): string
    {
        $image = $this->quietly(static fn () => imagecreatefromstring($bytes));

        if (! $image instanceof GdImage) {
            $this->fail($attribute, __('media.invalid_image'));
        }

        try {
            // The decoded bitmap must match the header we validated against.
            if (imagesx($image) !== $width || imagesy($image) !== $height) {
                $this->fail($attribute, __('media.invalid_image'));
            }

            if ($mime !== 'image/jpeg' && ! imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }

            if ($mime !== 'image/jpeg') {
                // Keep the alpha channel exactly as decoded.
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }

            $binary = $this->encode($image, $mime);
        } finally {
            // GdImage is an object since PHP 8.0 (imagedestroy() is a no-op, deprecated in 8.5):
            // dropping the last reference frees the bitmap.
            unset($image);
        }

        if ($binary === '') {
            $this->fail($attribute, __('media.invalid_image'));
        }

        return $binary;
    }

    private function encode(GdImage $image, string $mime): string
    {
        ob_start();

        try {
            $ok = match ($mime) {
                'image/png' => imagepng($image, null, 9),
                'image/jpeg' => imagejpeg($image, null, self::JPEG_QUALITY),
                'image/webp' => imagewebp($image, null, self::WEBP_QUALITY),
                default => false,
            };
        } finally {
            $output = ob_get_clean();
        }

        return $ok && is_string($output) ? $output : '';
    }

    private function typeNotAllowedMessage(BrandAssetSpec $spec): string
    {
        return __('media.type_not_allowed', [
            'types' => implode(', ', array_map('strtoupper', array_values(array_unique(array_map(
                static fn (string $mime): string => self::ENCODABLE[$mime] ?? $mime,
                $spec->allowedMimes,
            ))))),
        ]);
    }

    private function badDimensionsMessage(BrandAssetSpec $spec): string
    {
        return __('media.bad_dimensions', [
            'min_w' => $spec->minW,
            'min_h' => $spec->minH,
            'max_w' => $spec->maxW,
            'max_h' => $spec->maxH,
        ]);
    }

    /**
     * Runs a GD/filesystem call with PHP warnings suppressed: corrupt input makes GD emit
     * warnings, which the framework would turn into exceptions instead of a clean 422.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function quietly(callable $callback): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    private function fail(string $attribute, string $message): never
    {
        throw ValidationException::withMessages([$attribute => $message]);
    }
}
