<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SIGN-13: refuses addresses on a disposable / throw-away mail domain (config
 * signup.blocked_email_domains), the domain itself or any subdomain of it. One generic message:
 * it does not say which list matched.
 */
final class AllowedSignupEmailDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isAllowed($value)) {
            $fail('self_signup.validation.email_not_allowed')->translate();
        }
    }

    public static function isAllowed(string $email): bool
    {
        $at = strrpos($email, '@');
        $domain = strtolower(trim($at === false ? $email : substr($email, $at + 1), " \t\n\r\0\x0B."));
        if ($domain === '') {
            return false;
        }

        foreach ((array) config('signup.blocked_email_domains', []) as $blocked) {
            if (! is_string($blocked) || trim($blocked) === '') {
                continue;
            }

            $blocked = strtolower(trim($blocked));
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return false;
            }
        }

        return true;
    }
}
