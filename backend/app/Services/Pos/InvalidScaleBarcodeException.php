<?php

declare(strict_types=1);

namespace App\Services\Pos;

use RuntimeException;

/**
 * POSB-2: the code IS a scale label (enabled, known prefix, expected length) but cannot be
 * trusted, e.g. a wrong check digit. Carries a translation key instead of a message so the
 * parser stays free of framework calls; callers render it with localizedMessage().
 */
final class InvalidScaleBarcodeException extends RuntimeException
{
    public const INVALID_CHECK_DIGIT = 'pos.scale_barcode_invalid_check_digit';

    public function __construct(
        public readonly string $translationKey,
        public readonly string $barcode,
    ) {
        parent::__construct($translationKey);
    }

    public static function invalidCheckDigit(string $barcode): self
    {
        return new self(self::INVALID_CHECK_DIGIT, $barcode);
    }

    public function localizedMessage(): string
    {
        return (string) __($this->translationKey);
    }
}
