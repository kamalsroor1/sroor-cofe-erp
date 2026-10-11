<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * The output of ImageSanitizer: an image freshly re-encoded by GD, so it carries no
 * EXIF/metadata, text chunks, or bytes appended after the image data.
 */
final readonly class SanitizedImage
{
    /**
     * @param  'png'|'webp'|'jpg'  $extension
     */
    public function __construct(
        public string $binary,
        public string $extension,
        public string $mime,
        public int $width,
        public int $height,
        public string $sha256,
    ) {}
}
