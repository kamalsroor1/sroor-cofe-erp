<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\PlatformSuperAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Issues a short-lived, single-use signed URL that logs a central super admin into
 * the Telescope web session. Replaces the old `/telescope-access?token=` bridge, which
 * exposed bearer tokens in URLs, history and access logs.
 *
 * The nonce lives in the (central) cache for TTL_SECONDS and maps to the issuing user;
 * ConsumeTelescopeLinkAction pulls it exactly once.
 */
final class IssueTelescopeLinkAction
{
    public const TTL_SECONDS = 60;

    public const CACHE_PREFIX = 'telescope-link:';

    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function execute(User $user): string
    {
        if (! PlatformSuperAdmin::check($user)) {
            throw new AuthorizationException(__('auth.telescope_forbidden'));
        }

        $nonce = Str::random(64);
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);

        Cache::put(self::CACHE_PREFIX.$nonce, $user->getKey(), $expiresAt);

        $this->activityLogService->log(
            module: 'super_admin_auth',
            action: 'telescope_link_issued',
            description: __('super.telescope_link_issued_log', ['user' => $user->name]),
            subject: $user,
            userId: $user->id
        );

        return URL::temporarySignedRoute('telescope.access', $expiresAt, ['n' => $nonce]);
    }
}
