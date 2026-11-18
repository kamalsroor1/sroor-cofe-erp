<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Models\SignupRequest;
use Carbon\CarbonInterface;

/**
 * SIGN-13: hard-delete self-signups that never verified their email, older than the cut-off.
 * Only rows still `awaiting_verification` (they own no tenant, no subdomain hold and no
 * verified identity); assisted requests, verified / provisioned / failed self-signups and every
 * tenant are never touched. Deleted in chunks of ids. Returns the number of rows deleted.
 */
final class PruneUnverifiedSignupsAction
{
    private const CHUNK = 500;

    public function execute(CarbonInterface $olderThan): int
    {
        $deleted = 0;

        do {
            $ids = SignupRequest::query()
                ->where('signup_flow', SignupFlow::SelfSignup->value)
                ->where('status', SignupRequestStatus::AwaitingVerification->value)
                ->whereNull('tenant_id')
                ->where('created_at', '<', $olderThan)
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->pluck('id')
                ->all();

            if ($ids !== []) {
                $deleted += SignupRequest::query()
                    ->whereKey($ids)
                    ->where('status', SignupRequestStatus::AwaitingVerification->value)
                    ->delete();
            }
        } while (count($ids) === self::CHUNK);

        return $deleted;
    }
}
