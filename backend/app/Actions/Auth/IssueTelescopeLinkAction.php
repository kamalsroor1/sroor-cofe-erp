<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\CentralAuditEvent;
use App\Enums\MonitoringTool;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use App\Support\PlatformSuperAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issues a short-lived, single-use signed URL that logs a central super admin into
 * the `central_web` session and opens one monitoring dashboard (Telescope, Pulse or
 * Horizon, see MonitoringTool). The tool travels in the signed query (`t`), so it cannot be
 * swapped without breaking the signature. Replaces the old `/telescope-access?token=` bridge, which
 * exposed bearer tokens in URLs, history and access logs.
 *
 * The nonce lives in the (central) cache for TTL_SECONDS and maps to the issuing
 * CentralUser (IDEN-1.4: platform operators are CentralUser only, never App\Models\User);
 * ConsumeTelescopeLinkAction pulls it exactly once.
 */
final class IssueTelescopeLinkAction
{
    public const TTL_SECONDS = 60;

    public const CACHE_PREFIX = 'telescope-link:';

    public function __construct(
        private readonly CentralAuditLogger $auditLogger
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException when the dashboard is not served in this environment
     */
    public function execute(CentralUser $user, MonitoringTool $tool = MonitoringTool::Telescope): string
    {
        if (! PlatformSuperAdmin::check($user)) {
            throw new AuthorizationException(__('auth.telescope_forbidden'));
        }

        if (! $tool->isEnabled()) {
            throw ValidationException::withMessages([
                'tool' => __('auth.monitoring_tool_disabled'),
            ]);
        }

        $nonce = Str::random(64);
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);

        Cache::put(self::CACHE_PREFIX.$nonce, $user->getKey(), $expiresAt);

        // Central audit log only (actor = the CentralUser): the tenant-side activity log
        // could attribute the row to a `users` row that merely shares the operator's id.
        // The nonce is a credential and is never logged.
        $this->auditLogger->record(
            CentralAuditEvent::TelescopeLinkIssued,
            ['expires_at' => $expiresAt->toIso8601String(), 'tool' => $tool->value],
            actor: $user,
        );

        return URL::temporarySignedRoute('telescope.access', $expiresAt, ['n' => $nonce, 't' => $tool->value]);
    }
}
