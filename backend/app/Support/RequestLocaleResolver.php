<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Services\Settings\TenantSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * SETG-3: decides the language of one API request.
 *
 * Precedence (first supported value wins):
 *   1. users.locale of the authenticated tenant user (saved preference, nullable);
 *   2. the `X-Locale` request header — only `ar` / `en` (trimmed, case-insensitive),
 *      anything else is ignored and never echoed;
 *   3. the tenant default (SETG-1 TenantSettings::defaultLocale(), tenant DB only);
 *   4. 'ar'.
 *
 * TODO(CTO): the plan lists "user ← tenant default ← ar" and only says X-Locale is limited
 * to ar|en. The header is placed between the user preference and the tenant default so a
 * guest (login screen) or a user without a saved preference can still pick a language,
 * while a saved preference always wins. The SPA currently always sends X-Locale (falling
 * back to 'ar'), so for users without a saved preference the tenant default only applies
 * to clients that omit the header — see the SETG-3 report.
 */
final class RequestLocaleResolver
{
    public const HEADER = 'X-Locale';

    public const FALLBACK = 'ar';

    public function __construct(
        private readonly TenantSettings $tenantSettings,
    ) {}

    /**
     * Resolve and apply the locale for $request; returns the applied locale.
     */
    public function apply(Request $request, ?User $user = null): string
    {
        $locale = $this->resolve($request, $user);

        if (App::getLocale() !== $locale) {
            App::setLocale($locale);
        }

        return $locale;
    }

    public function resolve(Request $request, ?User $user = null): string
    {
        return self::pick(
            $user?->locale,
            $request->headers->get(self::HEADER),
            $this->tenantDefault(),
        );
    }

    /**
     * Pure precedence rule (unit-tested): user → header → tenant default → 'ar'.
     */
    public static function pick(mixed $userLocale, mixed $headerLocale, ?string $tenantDefault): string
    {
        return self::normalize($userLocale)
            ?? self::normalize($headerLocale)
            ?? self::normalize($tenantDefault)
            ?? self::FALLBACK;
    }

    /**
     * Map raw input to a supported locale or null. Never returns caller-controlled text.
     */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $candidate = strtolower(trim($value));

        foreach (TenantSettings::SUPPORTED_LOCALES as $supported) {
            if ($candidate === $supported) {
                return $supported;
            }
        }

        return null;
    }

    private function tenantDefault(): ?string
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        return $this->tenantSettings->defaultLocale();
    }
}
