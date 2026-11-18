<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\Actions\Tenants\ToggleTenantStatusAction;
use App\Enums\TenantStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CentralUser;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Support\Tenancy\TenantSuspensionReason;

/**
 * SIGN-12: suspend the shop created from a signup, from the console's signup log. Reuses the
 * super-admin suspension (ToggleTenantStatusAction -> TransitionTenantStatusAction: row lock,
 * state machine, lifecycle event, audit, token revocation), never a copy. A signup without a
 * tenant is 409; any move the state machine refuses keeps its own 403/409/422.
 */
final class SuspendSignupTenantAction
{
    public function __construct(private readonly ToggleTenantStatusAction $toggleStatus) {}

    public function execute(int $signupRequestId, TenantSuspensionReason $reason, ?string $note, CentralUser $operator): Tenant
    {
        $signup = SignupRequest::query()->findOrFail($signupRequestId);

        if ($signup->tenant_id === null || ! Tenant::query()->whereKey($signup->tenant_id)->exists()) {
            throw BusinessRuleException::conflict('self_signup.errors.no_tenant');
        }

        return $this->toggleStatus->execute(
            $signup->tenant_id,
            TenantStatus::Suspended,
            $operator,
            0,
            $reason,
            $note,
        );
    }
}
