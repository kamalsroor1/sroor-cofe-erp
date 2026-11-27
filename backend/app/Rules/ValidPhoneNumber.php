<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates customer/supplier phone numbers:
 * - Allows null / empty strings when combined with `nullable`
 * - Accepts ASCII and Arabic-Indic / Extended Arabic-Indic digits
 * - Allows optional leading `+` and standard separators (spaces, hyphens, parentheses)
 * - Rejects letters, HTML tags, and other invalid characters
 * - Requires 7 to 15 digits per number; several numbers may be separated by / , ; or ،
 * - Ignores invisible direction marks and non-breaking spaces
 */
final class ValidPhoneNumber implements ValidationRule
{
    public const MIN_DIGITS = 7;

    public const MAX_DIGITS = 15;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('validation.phone')->translate();

            return;
        }

        // Strip invisible direction marks / NBSP that come with numbers pasted from WhatsApp or contacts.
        $cleaned = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{00A0}\x{FEFF}]/u', ' ', $value) ?? $value;
        $normalized = self::toAsciiDigits(trim($cleaned));

        if ($normalized === '') {
            return;
        }

        // A field may hold more than one number ("010… / 011…"); each part must be a valid phone.
        foreach (preg_split('/\s*[\/,;،]\s*/u', $normalized) ?: [] as $part) {
            if (! self::isValidSingle(trim($part))) {
                $fail('validation.phone')->translate();

                return;
            }
        }
    }

    private static function isValidSingle(string $phone): bool
    {
        if (preg_match('/^\+?[\d(][\d\s\-()]*\d$/', $phone) !== 1) {
            return false;
        }

        $digitCount = strlen(preg_replace('/\D/', '', $phone) ?? '');

        return $digitCount >= self::MIN_DIGITS && $digitCount <= self::MAX_DIGITS;
    }

    private static function toAsciiDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
