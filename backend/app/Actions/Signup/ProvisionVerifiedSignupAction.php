<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\Actions\Tenants\ProvisionTenantAction;
use App\DTOs\CreateTenantDTO;
use App\Enums\CentralAuditEvent;
use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\Signup\SelfSignupProvisioningRefused;
use App\Jobs\NotifyPlatformOfSelfSignupIssueJob;
use App\Mail\SignupShopExistsMail;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Services\Branding\PlatformBranding;
use App\Services\CentralAuditLogger;
use App\Services\Signup\FreeShopGuard;
use App\Services\Signup\FreeShopMatch;
use App\Services\Signup\SelfSignupOutcomes;
use App\Services\Signup\SignupTrialTerms;
use App\Services\Signup\SubdomainSuggester;
use App\Services\TenantProvisionerService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SIGN-12: provision the tenant of a verified self-signup through the SAME path as the console
 * (ProvisionTenantAction -> TenantProvisionerService -> ProvisionTenantJob), never a copy.
 *
 * One central transaction, the signup row locked FOR UPDATE:
 *  - automatic run (no operator): the row must be `verified`. When today's automatic count
 *    (Africa/Cairo day, signup_daily_cap) is reached, the row goes to the old manual `pending`
 *    queue instead and the platform is alerted on Telegram once per day;
 *  - operator release (POST /super-admin/signup-requests/{id}/provision): a `pending` self-signup
 *    whose email was verified, cap ignored, audited as an approval;
 *  - the tenant is registered with the owner's chosen password HASH (never rehashed,
 *    must_change_password false), signup_source self_signup, the requested plan (a permanent free
 *    plan is born active; a paid plan starts a trial, SignupTrialTerms) and the requested workspace
 *    code, or a free variant when it was taken in the meantime;
 *  - the row becomes `provisioning` with tenant_id. The database is built by ProvisionTenantJob
 *    after the commit; its outcome reaches SelfSignupOutcomes (TenantProvisioningFinished).
 *
 * A refused registration rolls the transaction back and marks the row `failed` (owner mail +
 * Telegram, SelfSignupOutcomes). The automatic run never throws; the operator release answers 422.
 *
 * Security review (automatic run only):
 *  - count-and-claim of the daily caps is serialised by an atomic cache lock
 *    (`signup:provision-cap:{Cairo date}`, waits up to LOCK_WAIT_SECONDS) held until the
 *    transaction committed, so parallel workers cannot both take the last slot. A lock timeout
 *    leaves the row `verified`; the status poll re-queues it (GetSelfSignupStatusAction);
 *  - per-network cap: at most signup.network_cap.per_day automatic provisionings per client
 *    network (signup_requests.ip_network) per Cairo day; over it the row is held `pending` like the
 *    global cap (alert once per day), never refused;
 *  - one shop per identity is re-checked under the lock against existing TENANTS (FreeShopGuard;
 *    in-flight signups are not counted here, or two parallel verified twins would refuse each
 *    other): a match is committed `rejected` and the owner gets SignupShopExistsMail.
 */
final class ProvisionVerifiedSignupAction
{
    private const CAIRO = 'Africa/Cairo';

    private const LOCK_SECONDS = 60;

    private const LOCK_WAIT_SECONDS = 10;

    public function __construct(
        private readonly ProvisionTenantAction $provisionTenant,
        private readonly SignupTrialTerms $trialTerms,
        private readonly SubdomainSuggester $subdomains,
        private readonly SelfSignupOutcomes $outcomes,
        private readonly PlatformBranding $platform,
        private readonly CentralAuditLogger $audit,
        private readonly FreeShopGuard $freeShops,
    ) {}

    public function execute(int $signupRequestId, ?CentralUser $operator = null): SignupRequest
    {
        try {
            $signup = $operator === null
                ? Cache::lock('signup:provision-cap:'.CarbonImmutable::now(self::CAIRO)->format('Y-m-d'), self::LOCK_SECONDS)
                    ->block(self::LOCK_WAIT_SECONDS, fn (): SignupRequest => $this->provisionInTransaction($signupRequestId, null))
                : $this->provisionInTransaction($signupRequestId, $operator);
        } catch (LockTimeoutException) {
            Log::warning('Self-signup provisioning waited too long for the daily-cap lock; left verified for a retry', ['signup_request_id' => $signupRequestId]);

            return SignupRequest::query()->findOrFail($signupRequestId);
        } catch (BusinessRuleException $e) {
            throw $e;
        } catch (Throwable $e) {
            $code = $e instanceof SelfSignupProvisioningRefused ? $e->failureCode : SelfSignupOutcomes::FAILURE_REGISTRATION;
            Log::error('Self-signup provisioning failed', ['signup_request_id' => $signupRequestId, 'failure_code' => $code, 'exception' => $e::class]);

            $this->outcomes->failed($signupRequestId, $code);

            if ($operator !== null) {
                throw ValidationException::withMessages(['signup' => (string) __('signup.errors.provisioning_failed')]);
            }

            return SignupRequest::query()->findOrFail($signupRequestId);
        }

        return $signup;
    }

    private function provisionInTransaction(int $signupRequestId, ?CentralUser $operator): SignupRequest
    {
        return DB::connection(TenantProvisionerService::centralConnection())->transaction(
            fn (): SignupRequest => $this->provisionLocked($signupRequestId, $operator),
        );
    }

    private function provisionLocked(int $signupRequestId, ?CentralUser $operator): SignupRequest
    {
        $signup = SignupRequest::query()->whereKey($signupRequestId)->lockForUpdate()->firstOrFail();

        if (! $this->isProvisionable($signup, $operator)) {
            if ($operator !== null) {
                throw BusinessRuleException::conflict('self_signup.errors.not_provisionable');
            }

            return $signup; // already handled (double dispatch): nothing to do
        }

        if ($operator === null) {
            $held = $this->holdOrRefuse($signup);
            if ($held instanceof SignupRequest) {
                return $held;
            }
        }

        $plan = $this->trialTerms->resolvePlan($signup->plan_code);
        if (! $plan instanceof Plan) {
            throw new SelfSignupProvisioningRefused(SelfSignupOutcomes::FAILURE_PLAN_UNAVAILABLE);
        }

        $passwordHash = $signup->password_hash;
        if ($passwordHash === null || $passwordHash === '' || $signup->email === null) {
            throw new SelfSignupProvisioningRefused(SelfSignupOutcomes::FAILURE_REGISTRATION);
        }

        $preferred = (string) $signup->preferred_subdomain;
        $slug = $preferred !== '' && $this->subdomains->isAvailable($preferred, (int) $signup->id)
            ? $preferred
            : $this->subdomains->suggest($preferred !== '' ? $preferred : $signup->business_name, (int) $signup->id);

        $tenant = $this->provisionTenant->execute(CreateTenantDTO::forSelfSignup(
            name: $signup->business_name,
            slug: $slug,
            email: $signup->email,
            phone: $signup->phone,
            planId: (int) $plan->id,
            passwordHash: $passwordHash,
            trialDays: $this->trialTerms->trialDaysFor($plan),
        ));

        $signup->forceFill([
            'status' => SignupRequestStatus::Provisioning->value,
            'tenant_id' => (string) $tenant->getTenantKey(),
            'preferred_subdomain' => $slug,
            'plan_code' => $plan->slug,
            'provisioning_started_at' => now(),
            'failed_at' => null,
            'failure_code' => null,
            'reviewed_by' => $operator?->getKey(),
            'reviewed_at' => $operator === null ? null : now(),
        ])->save();

        if ($operator !== null) {
            $this->audit->record(
                CentralAuditEvent::SignupRequestApproved,
                ['signup_request_id' => $signup->id, 'slug' => $slug, 'plan_code' => $plan->slug, 'self_signup' => true],
                actor: $operator,
                subject: $tenant,
            );
        }

        return $signup;
    }

    /**
     * Automatic run, under the cap lock: the row held `pending` (a daily cap is reached), refused
     * (`rejected`: its identity already owns a shop), or null when it may be provisioned now.
     */
    private function holdOrRefuse(SignupRequest $signup): ?SignupRequest
    {
        $existing = $this->freeShops->match($signup->email, $signup->phone, (int) $signup->id, countInFlightSignups: false);
        if ($existing instanceof FreeShopMatch) {
            $signup->forceFill([
                'status' => SignupRequestStatus::Rejected->value,
                'rejection_reason' => (string) __('self_signup.console.duplicate_shop_rejected'),
                'password_hash' => null,
                'terminal_at' => now(),
            ])->save();

            if ($signup->email !== null) {
                $loginUrl = $existing->emailOwnedSlug === null || $existing->emailOwnedSlug === '' ? null : SelfSignupOutcomes::loginUrl($existing->emailOwnedSlug);
                Mail::to($signup->email)->queue(new SignupShopExistsMail($loginUrl, $signup->locale));
            }

            return $signup;
        }

        if ($this->dailyCapReached()) {
            $signup->forceFill(['status' => SignupRequestStatus::Pending->value])->save();
            $this->alertCapOncePerDay();

            return $signup;
        }

        if ($this->networkCapReached($signup)) {
            $signup->forceFill(['status' => SignupRequestStatus::Pending->value])->save();
            $this->alertNetworkCapOncePerDay();

            return $signup;
        }

        return null;
    }

    /** Automatic provisionings started today (Cairo day) from the signup's client network. */
    private function networkCapReached(SignupRequest $signup): bool
    {
        $network = $signup->ip_network;
        if ($network === null || $network === '') {
            return false;
        }

        $count = SignupRequest::query()
            ->where('ip_network', $network)
            ->whereNotNull('tenant_id')
            ->whereNull('reviewed_by')
            ->where('signup_flow', SignupFlow::SelfSignup->value)
            ->where('provisioning_started_at', '>=', $this->startOfCairoDay())
            ->count();

        return $count >= self::networkCap();
    }

    private function alertNetworkCapOncePerDay(): void
    {
        $now = CarbonImmutable::now(self::CAIRO);

        if (Cache::add('self-signup:network-cap-alert:'.$now->format('Y-m-d'), true, $now->endOfDay()->addHour())) {
            NotifyPlatformOfSelfSignupIssueJob::dispatch(NotifyPlatformOfSelfSignupIssueJob::ISSUE_NETWORK_CAP, null, self::networkCap())->afterCommit();
        }
    }

    private static function networkCap(): int
    {
        return max(1, (int) config('signup.network_cap.per_day', 3));
    }

    private function startOfCairoDay(): CarbonImmutable
    {
        return CarbonImmutable::now(self::CAIRO)->startOfDay()->setTimezone((string) config('app.timezone'));
    }

    private function isProvisionable(SignupRequest $signup, ?CentralUser $operator): bool
    {
        if (! $signup->isSelfSignup() || $signup->email_verified_at === null) {
            return false;
        }

        return $operator === null
            ? $signup->status === SignupRequestStatus::Verified
            : $signup->status === SignupRequestStatus::Pending;
    }

    /** Automatic provisionings started today (Cairo day) reached signup_daily_cap. */
    private function dailyCapReached(): bool
    {
        $startOfDay = $this->startOfCairoDay();

        $count = SignupRequest::query()
            ->whereNotNull('tenant_id')
            ->whereNull('reviewed_by')
            ->where('signup_flow', SignupFlow::SelfSignup->value)
            ->where('provisioning_started_at', '>=', $startOfDay)
            ->count();

        return $count >= $this->platform->get()->signupDailyCap;
    }

    private function alertCapOncePerDay(): void
    {
        $now = CarbonImmutable::now(self::CAIRO);
        $key = 'self-signup:cap-alert:'.$now->format('Y-m-d');

        if (Cache::add($key, true, $now->endOfDay()->addHour())) {
            NotifyPlatformOfSelfSignupIssueJob::dispatch(
                NotifyPlatformOfSelfSignupIssueJob::ISSUE_CAP_REACHED,
                null,
                $this->platform->get()->signupDailyCap,
            )->afterCommit();
        }
    }
}
