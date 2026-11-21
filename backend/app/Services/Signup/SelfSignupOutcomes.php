<?php

declare(strict_types=1);

namespace App\Services\Signup;

use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Jobs\NotifyPlatformOfSelfSignupIssueJob;
use App\Mail\SignupAccountReadyMail;
use App\Mail\SignupProvisioningDelayedMail;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Services\TenantProvisionerService;
use App\Support\PlatformHosts;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * SIGN-12: the end of a self-signup's provisioning, recorded on its `signup_requests` row. Shared by
 * ProvisionVerifiedSignupAction (registration refused), the TenantProvisioningFinished listener
 * (job outcome) and GetSelfSignupStatusAction (self-healing when the listener was missed).
 *
 * Each method: one central transaction, the row locked, idempotent; both stamp `terminal_at` (the
 * browser token dies 1 hour later, SignupRequest::forLiveToken):
 *  - ready(): `provisioning` / `failed` -> `provisioned`; the password hash is dropped (the tenant
 *    has it); "your shop is ready" is queued ONCE (ready_mail_sent_at);
 *  - failed(): -> `failed` with a code, the password hash dropped; the "we're on it" mail is queued ONCE
 *    (failure_mail_sent_at) and the platform is alerted on Telegram. Mails and the alert leave
 *    after the commit.
 */
final class SelfSignupOutcomes
{
    public const FAILURE_REGISTRATION = 'registration_failed';

    public const FAILURE_PLAN_UNAVAILABLE = 'plan_unavailable';

    public const FAILURE_PROVISIONING = 'provisioning_failed';

    /** The tenant of a self-signup is ready. Returns false when no self-signup owns $tenantId. */
    public function ready(string $tenantId): bool
    {
        return $this->connection()->transaction(function () use ($tenantId): bool {
            $signup = $this->lockedForTenant($tenantId, [SignupRequestStatus::Provisioning, SignupRequestStatus::Failed]);
            if (! $signup instanceof SignupRequest) {
                return false;
            }

            $tenant = Tenant::query()->find($tenantId);
            if (! $tenant instanceof Tenant || ! $tenant->provisioningStatus()->isReady()) {
                return false;
            }

            $sendMail = $signup->ready_mail_sent_at === null;

            $signup->forceFill([
                'status' => SignupRequestStatus::Provisioned->value,
                'provisioned_at' => $signup->provisioned_at ?? now(),
                'failure_code' => null,
                'password_hash' => null,
                'terminal_at' => now(),
                'ready_mail_sent_at' => $signup->ready_mail_sent_at ?? now(),
            ])->save();

            if ($sendMail) {
                $this->queueReadyMail($signup, $tenant);
            }

            return true;
        });
    }

    /** The provisioning job gave up on the tenant of a self-signup. */
    public function failedForTenant(string $tenantId): void
    {
        $signupId = SignupRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('signup_flow', SignupFlow::SelfSignup->value)
            ->where('status', SignupRequestStatus::Provisioning->value)
            ->value('id');

        if ($signupId !== null) {
            $this->failed((int) $signupId, self::FAILURE_PROVISIONING);
        }
    }

    /** Mark a verified / provisioning self-signup failed, tell the owner and the platform. */
    public function failed(int $signupId, string $code): void
    {
        $this->connection()->transaction(function () use ($signupId, $code): void {
            $signup = SignupRequest::query()->whereKey($signupId)->lockForUpdate()->first();
            if (! $signup instanceof SignupRequest || ! $signup->isSelfSignup()
                || in_array($signup->status, [SignupRequestStatus::Provisioned, SignupRequestStatus::Approved, SignupRequestStatus::Rejected], true)) {
                return;
            }

            $sendMail = $signup->failure_mail_sent_at === null;

            $signup->forceFill([
                'status' => SignupRequestStatus::Failed->value,
                'failed_at' => now(),
                'failure_code' => $code,
                'failure_mail_sent_at' => $signup->failure_mail_sent_at ?? now(),
                // Security review: final state, the chosen password hash is not kept.
                'password_hash' => null,
                'terminal_at' => now(),
            ])->save();

            if ($sendMail && $signup->email !== null) {
                Mail::to($signup->email)->queue(new SignupProvisioningDelayedMail($signup->business_name, $signup->locale));
            }

            NotifyPlatformOfSelfSignupIssueJob::dispatch(NotifyPlatformOfSelfSignupIssueJob::ISSUE_PROVISIONING_FAILED, $signup->id)->afterCommit();
        });
    }

    /** Sign-in URL of a tenant host. */
    public static function loginUrl(string $slug): string
    {
        return PlatformHosts::url(PlatformHosts::tenantHost($slug), '/login');
    }

    /** SIGN-15: forgot-password page of a tenant host (built from the shop's own domain, never the request Host). */
    public static function forgotPasswordUrl(string $slug): string
    {
        return PlatformHosts::url(PlatformHosts::tenantHost($slug), '/forgot-password');
    }

    private function queueReadyMail(SignupRequest $signup, Tenant $tenant): void
    {
        if ($signup->email === null) {
            return;
        }

        $slug = (string) $tenant->slug;
        $plan = $tenant->plan_id === null ? null : Plan::query()->find($tenant->plan_id);
        $trialEndsAt = $tenant->trial_ends_at === null ? null : CarbonImmutable::instance($tenant->trial_ends_at);

        Mail::to($signup->email)->queue(new SignupAccountReadyMail(
            businessName: $signup->business_name,
            loginUrl: self::loginUrl($slug),
            loginEmail: (string) $tenant->email,
            planName: $plan instanceof Plan ? $plan->name : '',
            trialEndsAt: $trialEndsAt,
            helpUrl: PlatformHosts::url(PlatformHosts::tenantHost($slug), '/help'),
            forgotPasswordUrl: self::forgotPasswordUrl($slug),
            locale: $signup->locale,
        ));
    }

    /**
     * @param  list<SignupRequestStatus>  $statuses
     */
    private function lockedForTenant(string $tenantId, array $statuses): ?SignupRequest
    {
        $signup = SignupRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('signup_flow', SignupFlow::SelfSignup->value)
            ->whereIn('status', array_map(static fn (SignupRequestStatus $status): string => $status->value, $statuses))
            ->lockForUpdate()
            ->first();

        return $signup instanceof SignupRequest ? $signup : null;
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection(TenantProvisionerService::centralConnection());
    }
}
