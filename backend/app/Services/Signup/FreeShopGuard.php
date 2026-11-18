<?php

declare(strict_types=1);

namespace App\Services\Signup;

use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Enums\TenantSignupSource;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Services\Plans\FreePlanIds;
use App\Support\Signup\SignupIdentity;
use Illuminate\Database\Eloquent\Builder;

/**
 * SIGN-13 + security review: one self-signup shop per identity. Applies to EVERY self-signup,
 * whatever plan it asked for (a paid self-signup trial ends on the free plan).
 *
 * An identity is the canonical email (SignupIdentity: gmail dots, +tags, googlemail folded) and
 * the digits-only phone, compared on the stored `canonical_email` / `phone_normalized` columns.
 * Taken when either matches:
 *  - a tenant created by self-signup (any plan, any status) or on a free plan (FreePlanIds), unless
 *    it was purged (its workspace no longer exists);
 *  - optionally, another self-signup whose email was VERIFIED and that is on its way to a shop
 *    (verified, held in the manual queue, provisioning). Unverified signups never block anyone.
 *
 * Callers: VerifySelfSignupAction (once the code proved the email), ProvisionVerifiedSignupAction
 * (re-check under the provisioning lock, tenants only) and ApplyTenantLifecycleAction (trial
 * fallback to free). Never called at signup start: the answer there must not depend on it.
 */
final class FreeShopGuard
{
    /** @var list<string> */
    private const IN_FLIGHT = [
        SignupRequestStatus::Verified->value,
        SignupRequestStatus::Pending->value,
        SignupRequestStatus::Provisioning->value,
    ];

    public function __construct(private readonly FreePlanIds $freePlans) {}

    public function alreadyHasShop(
        ?string $email,
        ?string $phone,
        ?int $exceptSignupId = null,
        ?string $exceptTenantId = null,
        bool $countInFlightSignups = true,
    ): bool {
        return $this->match($email, $phone, $exceptSignupId, $exceptTenantId, $countInFlightSignups) instanceof FreeShopMatch;
    }

    public function match(
        ?string $email,
        ?string $phone,
        ?int $exceptSignupId = null,
        ?string $exceptTenantId = null,
        bool $countInFlightSignups = true,
    ): ?FreeShopMatch {
        $canonicalEmail = SignupIdentity::canonicalEmail($email);
        $normalizedPhone = SignupIdentity::normalizedPhone($phone);

        if ($canonicalEmail === null && $normalizedPhone === null) {
            return null;
        }

        $tenant = $this->matchingTenant($canonicalEmail, $normalizedPhone, $exceptTenantId);
        if ($tenant !== null) {
            $ownedByEmail = $canonicalEmail !== null && $tenant['canonical_email'] === $canonicalEmail;

            return new FreeShopMatch($ownedByEmail ? $tenant['slug'] : null);
        }

        if ($countInFlightSignups && $this->inFlightSignupMatches($canonicalEmail, $normalizedPhone, $exceptSignupId, $exceptTenantId)) {
            return new FreeShopMatch(null);
        }

        return null;
    }

    /**
     * Plain rows (no model hydration: stancl's virtual `data` column is not needed here).
     *
     * @return array{slug: string, canonical_email: string|null}|null
     */
    private function matchingTenant(?string $canonicalEmail, ?string $normalizedPhone, ?string $exceptTenantId): ?array
    {
        $freeIds = $this->freePlans->all();

        $tenants = Tenant::query()
            ->select(['id', 'slug', 'canonical_email', 'phone_normalized'])
            ->where(static function (Builder $query) use ($freeIds): void {
                $query->where('signup_source', TenantSignupSource::SelfSignup->value);
                if ($freeIds !== []) {
                    $query->orWhereIn('plan_id', $freeIds);
                }
            })
            ->whereNull('purge_started_at')
            ->whereNull('purged_at')
            ->when($exceptTenantId !== null, static fn (Builder $query) => $query->whereKeyNot($exceptTenantId))
            ->where(fn (Builder $query) => $this->identity($query, $canonicalEmail, $normalizedPhone))
            ->orderByDesc('created_at')
            ->toBase()
            ->get()
            ->map(static fn (object $row): array => [
                'slug' => (string) ($row->slug ?? ''),
                'canonical_email' => isset($row->canonical_email) ? (string) $row->canonical_email : null,
            ]);

        // Prefer the shop owned by the email (its sign-in link may be mailed to that address).
        $byEmail = $canonicalEmail === null ? null : $tenants->first(static fn (array $row): bool => $row['canonical_email'] === $canonicalEmail);

        return $byEmail ?? $tenants->first();
    }

    private function inFlightSignupMatches(?string $canonicalEmail, ?string $normalizedPhone, ?int $exceptSignupId, ?string $exceptTenantId): bool
    {
        return SignupRequest::query()
            ->where('signup_flow', SignupFlow::SelfSignup->value)
            ->whereNotNull('email_verified_at')
            ->whereIn('status', self::IN_FLIGHT)
            ->when($exceptSignupId !== null, static fn (Builder $query) => $query->whereKeyNot($exceptSignupId))
            ->when($exceptTenantId !== null, static fn (Builder $query) => $query->where(
                static fn (Builder $own) => $own->whereNull('tenant_id')->orWhere('tenant_id', '!=', $exceptTenantId),
            ))
            ->where(fn (Builder $query) => $this->identity($query, $canonicalEmail, $normalizedPhone))
            ->exists();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function identity(Builder $query, ?string $canonicalEmail, ?string $normalizedPhone): void
    {
        if ($canonicalEmail !== null) {
            $query->where('canonical_email', $canonicalEmail);
        }

        if ($normalizedPhone !== null) {
            $query->orWhere('phone_normalized', $normalizedPhone);
        }
    }
}
