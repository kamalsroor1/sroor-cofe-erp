<?php

declare(strict_types=1);

namespace App\Actions\Central;

use App\Enums\CentralAuditEvent;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/v1/super-admin/auth/logout (IDEN-1.3): revokes ONLY the token used for this
 * request (other sessions of the operator stay valid) and audits `logout`.
 */
final class LogoutCentralUserAction
{
    public function __construct(
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(CentralUser $user): void
    {
        // AuthenticateCentral always attaches the CentralPersonalAccessToken it resolved; this
        // action is only reachable behind it.
        $token = $user->currentAccessToken();

        DB::connection($user->getConnectionName())->transaction(function () use ($user, $token): void {
            $sessionId = (int) $token->getKey();
            $token->delete();

            $this->auditLogger->record(
                CentralAuditEvent::Logout,
                ['session_id' => $sessionId],
                actor: $user,
                subject: $user,
            );
        });
    }
}
