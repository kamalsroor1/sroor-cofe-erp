<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Enums\SignupRequestStatus;
use App\Models\SignupRequest;
use App\Services\Signup\SignupTenantMatcher;
use App\Services\Signup\SignupTrialTerms;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PRD-05: a signup request as the platform console sees it. Never user_agent.
 * SELL-06: `approval_preview` (see SignupTrialTerms::preview()) feeds the approve dialog.
 *
 * @mixin SignupRequest
 */
final class SignupRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'city' => $this->city,
            'business_type' => $this->business_type->value,
            'plan_code' => $this->plan_code,
            'preferred_subdomain' => $this->preferred_subdomain,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'tenant_id' => $this->tenant_id,
            'ip' => $this->ip,
            'terms_accepted_at' => $this->terms_accepted_at?->toIso8601String(),
            'terms_version' => $this->terms_version,
            'created_at' => $this->created_at?->toIso8601String(),
            // SELL-06: what approving it now would create (trial on the requested plan until a
            // date); pending requests only, null when the plan cannot be resolved.
            'approval_preview' => $this->status === SignupRequestStatus::Pending ? $this->approvalPreview() : null,
        ];
    }

    /**
     * SignupTrialTerms::preview() plus `matching_tenants` (SELL-06 hardening: existing shops with
     * the same phone / email, so a repeat trial is visible before approving; never auto-refused).
     *
     * @return array<string, mixed>|null
     */
    private function approvalPreview(): ?array
    {
        $preview = app(SignupTrialTerms::class)->preview($this->plan_code, CarbonImmutable::now());

        if ($preview === null) {
            return null;
        }

        $preview['matching_tenants'] = app(SignupTenantMatcher::class)->matchesFor($this->resource);

        return $preview;
    }
}
