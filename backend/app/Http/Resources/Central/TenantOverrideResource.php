<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\TenantFeatureOverride;
use App\Models\TenantLimitOverride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PLAN-12: one per-shop override as the console sees it. Wraps a TenantLimitOverride or a
 * TenantFeatureOverride; the static shapers are reused inside the overrides listing.
 *
 * @mixin TenantLimitOverride|TenantFeatureOverride
 */
final class TenantOverrideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return $row instanceof TenantLimitOverride ? self::limit($row) : self::feature($row);
    }

    /**
     * @return array<string, mixed>
     */
    public static function limit(TenantLimitOverride $row): array
    {
        return [
            'resource' => $row->resource,
            'value' => $row->value,
            'reason' => $row->reason,
            'expires_at' => $row->expires_at?->toIso8601String(),
            'is_expired' => $row->isExpired(),
            'created_by' => self::creator($row->creator?->getKey(), $row->creator?->name, $row->created_by),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function feature(TenantFeatureOverride $row): array
    {
        return [
            'feature' => $row->feature_key,
            'mode' => $row->mode->value,
            'reason' => $row->reason === TenantFeatureOverride::LEGACY_REASON ? (string) __('plan_overrides.legacy_migrated_reason') : $row->reason,
            'expires_at' => $row->expires_at?->toIso8601String(),
            'is_expired' => $row->isExpired(),
            'created_by' => self::creator($row->creator?->getKey(), $row->creator?->name, $row->created_by),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    private static function creator(mixed $id, ?string $name, ?int $createdBy): ?array
    {
        if ($createdBy === null) {
            return null;
        }

        return ['id' => (int) ($id ?? $createdBy), 'name' => $name];
    }
}
