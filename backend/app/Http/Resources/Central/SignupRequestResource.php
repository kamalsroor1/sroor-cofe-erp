<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\SignupRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PRD-05: a signup request as the platform console sees it. Never user_agent.
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
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
