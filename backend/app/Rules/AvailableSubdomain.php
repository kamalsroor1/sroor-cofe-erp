<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Domain;
use App\Models\Tenant;
use App\Support\PlatformHosts;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * PRD-05: a workspace code usable as "<code>.<platform domain>": 3-30 chars of a-z, 0-9 and
 * "-" (no leading/trailing dash), not on the reserved list (config signup.reserved_subdomains)
 * and not already taken by a tenant slug or a domain.
 */
final class AvailableSubdomain implements ValidationRule
{
    public const PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,28})[a-z0-9]$/';

    /**
     * @param  bool  $checkTaken  false on the PUBLIC form: format + reserved only, so the form
     *                            cannot be used to enumerate existing workspaces.
     */
    public function __construct(private readonly bool $checkTaken = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('signup.validation.subdomain_format')->translate();

            return;
        }

        // Punycode / IDN-style labels ("xn--", or "--" at positions 3-4) are homograph material.
        if (str_starts_with($value, 'xn--') || substr($value, 2, 2) === '--') {
            $fail('signup.validation.subdomain_format')->translate();

            return;
        }

        if (in_array($value, self::reserved(), true)) {
            $fail('signup.validation.subdomain_reserved')->translate();

            return;
        }

        if (! $this->checkTaken) {
            return;
        }

        $taken = Tenant::query()->where('slug', $value)->orWhere('id', $value)->exists()
            || Domain::query()->where('domain', PlatformHosts::tenantHost($value))->exists();

        if ($taken) {
            $fail('signup.validation.subdomain_taken')->translate();
        }
    }

    /**
     * The configured reserved list plus the first label of every platform / console host.
     *
     * @return list<string>
     */
    public static function reserved(): array
    {
        $hosts = array_merge((array) config('central.admin_domains', []), (array) config('tenancy.central_domains', []));
        $labels = [];
        foreach ($hosts as $host) {
            if (is_string($host) && trim($host) !== '') {
                $labels[] = explode('.', strtolower(trim($host)))[0];
            }
        }

        return array_values(array_unique(array_filter(array_merge(
            array_map('strtolower', array_filter((array) config('signup.reserved_subdomains', []), 'is_string')),
            $labels,
        ))));
    }
}
