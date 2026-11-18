<?php

declare(strict_types=1);

namespace App\Listeners\Signup;

use App\Services\Signup\SelfSignupOutcomes;
use App\Support\Tenancy\TenantProvisioningFinished;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SIGN-12: when ProvisionTenantJob finishes the tenant of a self-signup, record it on the signup
 * row and tell the owner ("your shop is ready" / "we're on it"). Tenants that no self-signup owns
 * (console, assisted approval) are ignored. Never throws into the provisioning job: a miss is
 * healed by the status endpoint (GetSelfSignupStatusAction).
 */
final class CompleteSelfSignupProvisioning
{
    public function __construct(private readonly SelfSignupOutcomes $outcomes) {}

    public function handle(TenantProvisioningFinished $event): void
    {
        try {
            if ($event->succeeded) {
                $this->outcomes->ready($event->tenantId);
            } else {
                $this->outcomes->failedForTenant($event->tenantId);
            }
        } catch (Throwable $e) {
            Log::error('Self-signup provisioning outcome could not be recorded', [
                'tenant_id' => $event->tenantId,
                'succeeded' => $event->succeeded,
                'exception' => $e::class,
            ]);
        }
    }
}
