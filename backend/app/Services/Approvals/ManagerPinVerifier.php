<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Models\User;
use App\Support\ActiveStore;
use App\Support\RateLimitKey;
use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The single manager-PIN verifier shared by every "a manager approves this" flow
 * (below-floor sale price, over-cap discount, cancelling an invoice with cash payments).
 *
 * One limiter key namespace (`manager-pin:{tenant scope}:approver|actor:{id}`) so that wrong PINs
 * guessed through one flow count against every other flow, and a tenant never shares a bucket
 * with another tenant. Callers decide WHAT the approver must be allowed to do via $authorize.
 * Every failure (unknown user / not allowed / no store access / no PIN / wrong PIN) shares one
 * generic message so nothing is leaked about which condition failed.
 */
final class ManagerPinVerifier
{
    public const MAX_ATTEMPTS_PER_APPROVER = 5;

    public const MAX_ATTEMPTS_PER_ACTOR = 10;

    public const DECAY_SECONDS = 300;

    public static function approverKey(int $approverId): string
    {
        return 'manager-pin:'.RateLimitKey::scope().':approver:'.$approverId;
    }

    /**
     * @param  Closure(User): bool  $authorize  whether this approver may approve the requested action
     *
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    public function verify(int $approverId, string $pin, ?User $actor, ?int $storeId, Closure $authorize): User
    {
        $scope = RateLimitKey::scope();
        $approverKey = self::approverKey($approverId);
        $actorKey = 'manager-pin:'.$scope.':actor:'.($actor instanceof User ? (string) $actor->id : 'guest');

        if (RateLimiter::tooManyAttempts($approverKey, self::MAX_ATTEMPTS_PER_APPROVER)
            || RateLimiter::tooManyAttempts($actorKey, self::MAX_ATTEMPTS_PER_ACTOR)) {
            $wait = max(RateLimiter::availableIn($approverKey), RateLimiter::availableIn($actorKey), 1);

            throw new ThrottleRequestsException(__('pos.override_throttled'), null, ['Retry-After' => (string) $wait]);
        }

        $approver = User::query()->whereKey($approverId)->where('is_active', true)->first();

        $valid = $approver instanceof User
            && $authorize($approver)
            && ($storeId === null || ActiveStore::canAccess($approver, $storeId))
            && is_string($approver->pos_pin)
            && $approver->pos_pin !== ''
            && Hash::check($pin, $approver->pos_pin);

        if (! $valid) {
            RateLimiter::hit($approverKey, self::DECAY_SECONDS);
            RateLimiter::hit($actorKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'override.pin' => __('pos.override_invalid'),
            ]);
        }

        RateLimiter::clear($approverKey);

        return $approver;
    }
}
