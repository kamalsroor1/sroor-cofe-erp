<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\CentralUser;
use App\Support\PlatformSuperAdmin;
use Illuminate\Support\Facades\Cache;

/**
 * Redeems a Telescope link nonce issued by IssueTelescopeLinkAction.
 *
 * Single use: the nonce is pulled from the cache, and an atomic Cache::add() marker
 * guarantees that two concurrent requests with the same nonce cannot both succeed.
 * Returns the central super admin (App\Models\CentralUser, central connection) to log
 * into the `central_web` guard, or null when the nonce is unknown, expired, already
 * used, or the operator is no longer an active platform super admin. A `users` row
 * (tenant or legacy central) is never returned, even when it shares the operator's id.
 */
final class ConsumeTelescopeLinkAction
{
    private const USED_PREFIX = 'telescope-link-used:';

    public function execute(string $nonce): ?CentralUser
    {
        if ($nonce === '') {
            return null;
        }

        if (! Cache::add(self::USED_PREFIX.$nonce, true, now()->addSeconds(IssueTelescopeLinkAction::TTL_SECONDS * 2))) {
            return null;
        }

        $userId = Cache::pull(IssueTelescopeLinkAction::CACHE_PREFIX.$nonce);

        if (! is_int($userId) && ! (is_string($userId) && ctype_digit($userId))) {
            return null;
        }

        $user = CentralUser::query()
            ->whereKey((int) $userId)
            ->where('is_active', true)
            ->first();

        return PlatformSuperAdmin::check($user) ? $user : null;
    }
}
