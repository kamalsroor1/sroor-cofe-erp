<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\Actions\Tenants\ProvisionTenantAction;
use App\DTOs\CreateTenantDTO;
use App\DTOs\Signup\ApprovedSignupResult;
use App\DTOs\Signup\ApproveSignupRequestDTO;
use App\Enums\CentralAuditEvent;
use App\Enums\SignupRequestStatus;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use App\Services\TenantProvisionerService;
use App\Support\Auth\TemporaryPassword;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * PRD-05: approve a pending signup request by provisioning its tenant through the SAME
 * path as POST /api/v1/super-admin/tenants (ProvisionTenantAction), never a copy of it.
 *
 * One central transaction: the request row is locked FOR UPDATE and re-checked as pending
 * (409 otherwise, so two operators cannot provision it twice); the tenant is registered
 * (the database itself is created afterwards by the queued ProvisionTenantJob, dispatched
 * after commit); then the request is marked approved with tenant_id, reviewer and time, and
 * the audit row is written. A provisioning failure rolls everything back and the request
 * stays pending. The audit row never carries the owner password.
 *
 * SELL-01: without `owner_password` the server generates a temporary one (returned once in the
 * result, never stored in plain). Either way provisioning flags the owner `must_change_password`,
 * so the operator's knowledge of it ends at the owner's first sign-in.
 */
final class ApproveSignupRequestAction
{
    public function __construct(
        private readonly ProvisionTenantAction $provisionTenant,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(ApproveSignupRequestDTO $dto, CentralUser $operator): ApprovedSignupResult
    {
        $temporaryPassword = $dto->ownerPassword === null ? TemporaryPassword::generate() : null;
        $ownerPassword = $dto->ownerPassword ?? (string) $temporaryPassword;

        $signup = DB::connection(TenantProvisionerService::centralConnection())->transaction(function () use ($dto, $operator, $ownerPassword): SignupRequest {
            $signup = SignupRequest::query()->whereKey($dto->signupRequestId)->lockForUpdate()->firstOrFail();

            if ($signup->status !== SignupRequestStatus::Pending) {
                throw new ConflictHttpException((string) __('signup.errors.not_pending'));
            }

            $plan = $this->resolvePlan($dto->planCode);
            $tenant = $this->provision($signup, $dto, $plan, $ownerPassword);

            $signup->forceFill([
                'status' => SignupRequestStatus::Approved->value,
                'tenant_id' => (string) $tenant->getTenantKey(),
                'reviewed_by' => $operator->getKey(),
                'reviewed_at' => now(),
            ])->save();

            $this->auditLogger->record(
                CentralAuditEvent::SignupRequestApproved,
                ['signup_request_id' => $signup->id, 'slug' => $tenant->slug, 'plan_code' => $plan->slug],
                actor: $operator,
                subject: $tenant,
            );

            return $signup;
        });

        return new ApprovedSignupResult($signup, $dto->ownerEmail, $temporaryPassword);
    }

    /** Requested plan, else the catalog's trial plan (first active plan with trial days). */
    private function resolvePlan(?string $planCode): Plan
    {
        $query = Plan::query()->where('is_active', true);

        $plan = $planCode !== null
            ? $query->where('slug', $planCode)->first()
            : $query->where('trial_days', '>', 0)->orderBy('sort_order')->orderBy('id')->first();

        if (! $plan instanceof Plan) {
            throw ValidationException::withMessages(['plan_code' => (string) __('signup.errors.no_trial_plan')]);
        }

        return $plan;
    }

    private function provision(SignupRequest $signup, ApproveSignupRequestDTO $dto, Plan $plan, #[SensitiveParameter] string $ownerPassword): Tenant
    {
        $tenantDto = CreateTenantDTO::fromArray([
            'name' => $signup->business_name,
            'slug' => $dto->subdomain,
            'email' => $dto->ownerEmail,
            'phone' => $signup->phone,
            'plan_id' => $plan->id,
            'password' => $ownerPassword,
            'trial_days' => $plan->trial_days,
        ]);

        try {
            return $this->provisionTenant->execute($tenantDto);
        } catch (HttpResponseException|ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Signup request provisioning failed', ['signup_request_id' => $signup->id, 'exception' => $e]);

            throw ValidationException::withMessages(['subdomain' => (string) __('signup.errors.provisioning_failed')]);
        }
    }
}
