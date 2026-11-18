<?php

declare(strict_types=1);

namespace App\DTOs\Central;

use App\Enums\LimitResource;
use Illuminate\Support\Carbon;

/**
 * Input of App\Actions\Tenants\SetTenantLimitOverrideAction (PLAN-12).
 * `value` NULL means unlimited for this shop.
 */
final readonly class SetTenantLimitOverrideDTO
{
    public function __construct(
        public string $tenantId,
        public LimitResource $resource,
        public ?int $value,
        public string $reason,
        public ?Carbon $expiresAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated SetTenantLimitOverrideRequest data
     */
    public static function fromArray(string $tenantId, array $data): self
    {
        $value = $data['value'] ?? null;
        $expires = $data['expires_at'] ?? null;

        return new self(
            tenantId: $tenantId,
            resource: LimitResource::from((string) $data['resource']),
            value: $value === null || $value === '' ? null : (int) $value,
            reason: trim((string) $data['reason']),
            expiresAt: $expires === null || $expires === '' ? null : Carbon::parse((string) $expires),
        );
    }

    /**
     * @return array{tenant_id: string, resource: string, value: int|null, reason: string, expires_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'resource' => $this->resource->value,
            'value' => $this->value,
            'reason' => $this->reason,
            'expires_at' => $this->expiresAt?->toIso8601String(),
        ];
    }
}
