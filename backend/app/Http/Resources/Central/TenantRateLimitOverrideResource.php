<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\TenantRateLimitOverride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantRateLimitOverride
 */
final class TenantRateLimitOverrideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TenantRateLimitOverride $override */
        $override = $this->resource;

        return [
            'id' => (int) $override->getKey(),
            'tenant_id' => (string) $override->tenant_id,
            'limits' => [
                'tenant_login_per_ip_per_minute' => $override->tenant_login_per_ip_per_minute,
                'tenant_login_per_login_per_minute' => $override->tenant_login_per_login_per_minute,
                'tenant_resolve_per_minute' => $override->tenant_resolve_per_minute,
            ],
            'reason' => $override->reason,
            'central_user_id' => $override->central_user_id,
            'expires_at' => $override->expires_at->toIso8601String(),
            'created_at' => $override->created_at?->toIso8601String(),
        ];
    }
}
