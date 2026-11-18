<?php

declare(strict_types=1);

namespace App\Http\Resources\Signup;

use App\Enums\SignupRequestStatus;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Services\Signup\SelfSignupOutcomes;
use App\Support\Signup\SignupSecrets;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SIGN-12: GET /api/v1/signup/status. `ready` + `login_url` once the shop can be used (provisioned
 * by the automatic path, or approved by an operator and provisioned). Never the email in full, a
 * password, the tenant id or operator fields.
 */
final class SelfSignupStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{signup: SignupRequest, tenant: Tenant|null} $state */
        $state = $this->resource;
        $signup = $state['signup'];
        $tenant = $state['tenant'];

        $ready = $tenant instanceof Tenant
            && $tenant->provisioningStatus()->isReady()
            && in_array($signup->status, [SignupRequestStatus::Provisioned, SignupRequestStatus::Approved], true);

        return [
            'status' => $signup->status->value,
            'ready' => $ready,
            'login_url' => $ready ? SelfSignupOutcomes::loginUrl((string) $tenant->slug) : null,
            'business_name' => $signup->business_name,
            'email_masked' => SignupSecrets::maskEmail((string) $signup->email),
            'subdomain' => $ready ? (string) $tenant->slug : $signup->preferred_subdomain,
        ];
    }
}
