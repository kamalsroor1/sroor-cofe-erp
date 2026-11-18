<?php

declare(strict_types=1);

namespace App\Services\Signup;

use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Services\Plans\FreePlanIds;
use App\Support\Signup\EgyptianPhone;
use Illuminate\Database\Eloquent\Builder;

/**
 * SIGN-13: one permanent free shop per verified email and per (normalised Egyptian) phone.
 *
 * Taken when either matches:
 *  - a tenant on a free plan (FreePlanIds), whatever its status (an archived or suspended free
 *    shop still counts: abuse would otherwise be "suspend, sign up again");
 *  - another self-signup whose email was VERIFIED and that is on its way to a free shop
 *    (verified, held in the manual queue, provisioning, provisioned). Unverified signups never
 *    block anyone (they could be typed by a stranger).
 *
 * Paid-plan signups are never limited by this. Checked at signup and again at verification.
 */
final class FreeShopGuard
{
    /** @var list<string> */
    private const COUNTED_STATUSES = [
        SignupRequestStatus::Verified->value,
        SignupRequestStatus::Pending->value,
        SignupRequestStatus::Provisioning->value,
        SignupRequestStatus::Provisioned->value,
    ];

    public function __construct(private readonly FreePlanIds $freePlans) {}

    public function alreadyHasFreeShop(string $email, string $phone, ?int $exceptSignupId = null): bool
    {
        $email = strtolower(trim($email));
        $phones = EgyptianPhone::variants($phone);

        if ($email === '' && $phones === []) {
            return false;
        }

        return $this->freeTenantMatches($email, $phones) || $this->freeSignupMatches($email, $phones, $exceptSignupId);
    }

    /**
     * @param  list<string>  $phones
     */
    private function freeTenantMatches(string $email, array $phones): bool
    {
        $freeIds = $this->freePlans->all();
        if ($freeIds === []) {
            return false;
        }

        return Tenant::query()
            ->whereIn('plan_id', $freeIds)
            ->where(fn (Builder $query) => $this->matching($query, $email, $phones))
            ->exists();
    }

    /**
     * @param  list<string>  $phones
     */
    private function freeSignupMatches(string $email, array $phones, ?int $exceptSignupId): bool
    {
        $freeSlugs = Plan::query()->whereIn('id', $this->freePlans->all())->pluck('slug')->all();
        if ($freeSlugs === []) {
            return false;
        }

        return SignupRequest::query()
            ->where('signup_flow', SignupFlow::SelfSignup->value)
            ->whereNotNull('email_verified_at')
            ->whereIn('status', self::COUNTED_STATUSES)
            ->whereIn('plan_code', $freeSlugs)
            ->when($exceptSignupId !== null, fn (Builder $query) => $query->whereKeyNot($exceptSignupId))
            ->where(fn (Builder $query) => $this->matching($query, $email, $phones))
            ->exists();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $phones
     */
    private function matching(Builder $query, string $email, array $phones): void
    {
        if ($email !== '') {
            $query->whereRaw('LOWER(email) = ?', [$email]);
        }

        if ($phones !== []) {
            $query->orWhereIn('phone', $phones);
        }
    }
}
