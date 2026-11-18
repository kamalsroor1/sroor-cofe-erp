<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Security review (SIGN-11): a public signup's business / owner name ends up in platform emails,
 * Telegram alerts and the console, so it must never carry a link: no URL scheme, no `www.`, no
 * `@`, no domain-like token (`name.com`, `shop.eg`, ...). Lookalike dots (one-dot leader,
 * fullwidth and ideographic full stops) count as dots.
 */
final class NoLinksInName implements ValidationRule
{
    /** Common TLDs (and the second-level parts of `.com.eg`-style names, caught through `com`). */
    private const TLDS = 'com|net|org|app|io|eg|co|info|xyz|me|link|biz|online|site|shop|store|top|club|live|'
        .'tk|ml|ga|cf|gq|ru|cn|ly|to|sh|gg|ai|dev|page|click|today|vip|win|bid|icu|cc|tv|us|uk|de|fr|sa|ae|'
        .'email|pro|ws|su|tech|website|space|fun|buzz|rest|cam|gov|edu';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (self::containsLink($value)) {
            $fail('self_signup.validation.no_links')->translate();
        }
    }

    public static function containsLink(string $value): bool
    {
        $text = mb_strtolower(strtr($value, [
            "\u{2024}" => '.', "\u{FF0E}" => '.', "\u{3002}" => '.', "\u{FE52}" => '.', "\u{FF61}" => '.',
            "\u{FF20}" => '@', "\u{FE6B}" => '@',
        ]));

        if (str_contains($text, '@') || str_contains($text, 'www.') || preg_match('~[a-z][a-z0-9+.-]*://~u', $text) === 1) {
            return true;
        }

        return preg_match('~[\p{L}\p{N}-]+\.(?:'.self::TLDS.')(?![\p{L}\p{N}])~u', $text) === 1;
    }
}
