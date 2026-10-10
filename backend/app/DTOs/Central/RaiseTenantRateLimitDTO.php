<?php

declare(strict_types=1);

namespace App\DTOs\Central;

use App\Models\TenantRateLimitOverride;

/**
 * Input of App\Actions\Tenants\RaiseTenantRateLimitAction (IDEN-4.6 ext).
 */
final class RaiseTenantRateLimitDTO
{
    /**
     * @param  array<string, int>  $limits  override column => raised per-minute value (see TenantRateLimitOverride::LIMITS)
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly array $limits,
        public readonly int $durationMinutes,
        public readonly string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated RaiseTenantRateLimitRequest data
     */
    public static function fromArray(string $tenantId, array $data): self
    {
        $limits = [];

        foreach (array_keys(TenantRateLimitOverride::LIMITS) as $column) {
            $value = $data[$column] ?? null;

            if ($value !== null && $value !== '') {
                $limits[$column] = (int) $value;
            }
        }

        return new self(
            tenantId: $tenantId,
            limits: $limits,
            durationMinutes: (int) $data['duration_minutes'],
            reason: trim((string) $data['reason']),
        );
    }

    /**
     * @return array{tenant_id: string, limits: array<string, int>, duration_minutes: int, reason: string}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'limits' => $this->limits,
            'duration_minutes' => $this->durationMinutes,
            'reason' => $this->reason,
        ];
    }
}
