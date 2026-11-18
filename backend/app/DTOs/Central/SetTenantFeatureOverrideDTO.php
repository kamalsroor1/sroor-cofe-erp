<?php

declare(strict_types=1);

namespace App\DTOs\Central;

use App\Enums\FeatureOverrideMode;
use Illuminate\Support\Carbon;

/**
 * Input of App\Actions\Tenants\SetTenantFeatureOverrideAction (PLAN-12).
 * `featureKey` is the canonical catalog key (aliases are resolved by the Form Request).
 */
final readonly class SetTenantFeatureOverrideDTO
{
    public function __construct(
        public string $tenantId,
        public string $featureKey,
        public FeatureOverrideMode $mode,
        public string $reason,
        public ?Carbon $expiresAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated SetTenantFeatureOverrideRequest data
     */
    public static function fromArray(string $tenantId, array $data): self
    {
        $expires = $data['expires_at'] ?? null;

        return new self(
            tenantId: $tenantId,
            featureKey: (string) $data['feature'],
            mode: FeatureOverrideMode::from((string) $data['mode']),
            reason: trim((string) $data['reason']),
            expiresAt: $expires === null || $expires === '' ? null : Carbon::parse((string) $expires),
        );
    }

    /**
     * @return array{tenant_id: string, feature: string, mode: string, reason: string, expires_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'feature' => $this->featureKey,
            'mode' => $this->mode->value,
            'reason' => $this->reason,
            'expires_at' => $this->expiresAt?->toIso8601String(),
        ];
    }
}
