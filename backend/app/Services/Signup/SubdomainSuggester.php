<?php

declare(strict_types=1);

namespace App\Services\Signup;

use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Models\Domain;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Rules\AvailableSubdomain;
use App\Support\PlatformHosts;
use Illuminate\Support\Str;

/**
 * SIGN-11: workspace codes for self-signups.
 *
 *  - isAvailable(): valid format, not reserved, not owned by a tenant / domain and not held by
 *    another self-signup that already proved its email (verified, waiting in the manual queue or
 *    being provisioned). Unverified signups never hold a code (they would let anyone squat names).
 *  - suggest(): a free code derived from a base (the business name, transliterated): the base,
 *    then base-2 ... base-9, then base-<4 random chars>.
 */
final class SubdomainSuggester
{
    private const FALLBACK_BASE = 'shop';

    /** Signup statuses that hold their preferred subdomain. */
    private const HOLDING_STATUSES = [
        SignupRequestStatus::Verified->value,
        SignupRequestStatus::Pending->value,
    ];

    public function isAvailable(string $code, ?int $exceptSignupId = null): bool
    {
        if (preg_match(AvailableSubdomain::PATTERN, $code) !== 1
            || str_starts_with($code, 'xn--') || substr($code, 2, 2) === '--'
            || in_array($code, AvailableSubdomain::reserved(), true)) {
            return false;
        }

        if (Tenant::query()->where('slug', $code)->orWhere('id', $code)->exists()
            || Domain::query()->where('domain', PlatformHosts::tenantHost($code))->exists()) {
            return false;
        }

        return ! SignupRequest::query()
            ->where('signup_flow', SignupFlow::SelfSignup->value)
            ->where('preferred_subdomain', $code)
            ->whereIn('status', self::HOLDING_STATUSES)
            ->when($exceptSignupId !== null, fn ($query) => $query->whereKeyNot($exceptSignupId))
            ->exists();
    }

    public function suggest(string $base, ?int $exceptSignupId = null): string
    {
        $base = $this->baseFrom($base);

        if ($this->isAvailable($base, $exceptSignupId)) {
            return $base;
        }

        for ($i = 2; $i <= 9; $i++) {
            $candidate = $this->withSuffix($base, (string) $i);
            if ($this->isAvailable($candidate, $exceptSignupId)) {
                return $candidate;
            }
        }

        do {
            $candidate = $this->withSuffix($base, Str::lower(Str::random(4)));
        } while (! $this->isAvailable($candidate, $exceptSignupId));

        return $candidate;
    }

    /** A valid code from free text (Arabic is transliterated), at most 24 chars to leave room for a suffix. */
    private function baseFrom(string $text): string
    {
        $slug = Str::slug(Str::ascii($text, 'ar'));
        $slug = trim(substr($slug, 0, 24), '-');

        if (strlen($slug) < 3 || preg_match(AvailableSubdomain::PATTERN, $slug) !== 1) {
            return self::FALLBACK_BASE;
        }

        return $slug;
    }

    private function withSuffix(string $base, string $suffix): string
    {
        return trim(substr($base, 0, 30 - strlen($suffix) - 1), '-').'-'.$suffix;
    }
}
