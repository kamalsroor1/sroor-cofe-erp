<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\PlatformSuperAdmin;
use Illuminate\Support\Facades\Cache;

/**
 * Redeems a Telescope link nonce issued by IssueTelescopeLinkAction.
 *
 * Single use: the nonce is pulled from the cache, and an atomic Cache::add() marker
 * guarantees that two concurrent requests with the same nonce cannot both succeed.
 * Returns the super admin to log in, or null when the nonce is unknown, expired,
 * already used, or the user is no longer an active platform super admin.
 */
final class ConsumeTelescopeLinkAction
{
    private const USED_PREFIX = 'telescope-link-used:';

    public function execute(string $nonce): ?User
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

        $user = User::query()
            ->whereKey((int) $userId)
            ->where('is_active', true)
            ->first();

        return PlatformSuperAdmin::check($user) ? $user : null;
    }
}
