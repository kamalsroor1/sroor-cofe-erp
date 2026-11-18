<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\DTOs\Signup\SelfSignupTokenDTO;
use App\Enums\SignupRequestStatus;
use App\Jobs\ProvisionVerifiedSignupJob;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Services\Signup\SelfSignupOutcomes;
use Illuminate\Validation\ValidationException;

/**
 * SIGN-12: progress of a self-signup for the "preparing your shop" screen (polled).
 *
 * Self-healing, never trusting the client: a `provisioning` row whose tenant is already ready is
 * completed here (SelfSignupOutcomes::ready(), idempotent, the ready mail still goes once) and a
 * row stuck in `verified` for more than STUCK_AFTER_SECONDS gets its provisioning job queued again
 * (unique per signup). Unknown token, or one whose row reached a final state more than
 * signup.token_ttl_after_final_minutes ago (SignupRequest::forLiveToken): generic 422 on `token`.
 */
final class GetSelfSignupStatusAction
{
    private const STUCK_AFTER_SECONDS = 120;

    public function __construct(private readonly SelfSignupOutcomes $outcomes) {}

    /**
     * @return array{signup: SignupRequest, tenant: Tenant|null}
     */
    public function execute(SelfSignupTokenDTO $dto): array
    {
        $signup = SignupRequest::query()
            ->forLiveToken($dto->token)
            ->first();

        if (! $signup instanceof SignupRequest) {
            throw ValidationException::withMessages(['token' => (string) __('self_signup.errors.token_invalid')]);
        }

        $tenant = $signup->tenant_id === null ? null : Tenant::query()->find($signup->tenant_id);

        if ($signup->status === SignupRequestStatus::Provisioning && $tenant instanceof Tenant && $tenant->provisioningStatus()->isReady()
            && $this->outcomes->ready((string) $tenant->getTenantKey())) {
            $signup->refresh();
        }

        if ($signup->status === SignupRequestStatus::Verified
            && $signup->email_verified_at !== null
            && $signup->email_verified_at->lt(now()->subSeconds(self::STUCK_AFTER_SECONDS))) {
            ProvisionVerifiedSignupJob::dispatch((int) $signup->id);
        }

        return ['signup' => $signup, 'tenant' => $tenant];
    }
}
