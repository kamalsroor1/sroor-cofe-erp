<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\Enums\CentralAuditEvent;
use App\Enums\SignupRequestStatus;
use App\Models\CentralUser;
use App\Models\SignupRequest;
use App\Services\CentralAuditLogger;
use App\Services\TenantProvisionerService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * PRD-05: reject a pending signup request with a reason. The row is locked FOR UPDATE and
 * re-checked as pending (409 otherwise).
 */
final class RejectSignupRequestAction
{
    public function __construct(
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(int $signupRequestId, string $reason, CentralUser $operator): SignupRequest
    {
        return DB::connection(TenantProvisionerService::centralConnection())->transaction(function () use ($signupRequestId, $reason, $operator): SignupRequest {
            $signup = SignupRequest::query()->whereKey($signupRequestId)->lockForUpdate()->firstOrFail();

            if ($signup->status !== SignupRequestStatus::Pending) {
                throw new ConflictHttpException((string) __('signup.errors.not_pending'));
            }

            $signup->forceFill([
                'status' => SignupRequestStatus::Rejected->value,
                'rejection_reason' => $reason,
                'reviewed_by' => $operator->getKey(),
                'reviewed_at' => now(),
            ])->save();

            $this->auditLogger->record(
                CentralAuditEvent::SignupRequestRejected,
                ['signup_request_id' => $signup->id],
                actor: $operator,
                subject: $signup,
            );

            return $signup;
        });
    }
}
