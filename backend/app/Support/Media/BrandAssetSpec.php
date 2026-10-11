<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Upload rules for one kind of brand image (logo, favicon, app icon).
 *
 * The mime list is what ImageSanitizer enforces against the magic-byte mime.
 * The extension list is informational (for FormRequest `mimes:` rules and UI hints).
 * The client's file name and extension are never trusted.
 */
final readonly class BrandAssetSpec
{
    /**
     * @param  list<string>  $allowedMimes
     * @param  list<string>  $allowedExtensions
     */
    public function __construct(
        public array $allowedMimes,
        public array $allowedExtensions,
        public int $maxKb,
        public int $minW,
        public int $minH,
        public int $maxW,
        public int $maxH,
        public bool $square = false,
    ) {}

    public static function logo(): self
    {
        return new self(
            allowedMimes: ['image/png', 'image/jpeg', 'image/webp'],
            allowedExtensions: ['png', 'jpg', 'jpeg', 'webp'],
            maxKb: 2048,
            minW: 64,
            minH: 64,
            maxW: 2048,
            maxH: 2048,
        );
    }

    public static function favicon(): self
    {
        return new self(
            allowedMimes: ['image/png'],
            allowedExtensions: ['png'],
            maxKb: 256,
            minW: 32,
            minH: 32,
            maxW: 512,
            maxH: 512,
            square: true,
        );
    }

    public static function appIcon(): self
    {
        return new self(
            allowedMimes: ['image/png'],
            allowedExtensions: ['png'],
            maxKb: 1024,
            minW: 192,
            minH: 192,
            maxW: 1024,
            maxH: 1024,
            square: true,
        );
    }
}
