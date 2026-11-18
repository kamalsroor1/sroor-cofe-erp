<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PLAN-14: one (shop, limited resource) row of the near-limit list. Wraps the plain array built
 * by App\Actions\Plans\ListShopsNearLimitAction.
 *
 * @property array<string, mixed> $resource
 */
final class NearLimitShopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'tenant_id' => $row['tenant_id'],
            'tenant_name' => $row['tenant_name'],
            'tenant_status' => $row['tenant_status'],
            'plan' => ['id' => $row['plan_id'], 'name' => $row['plan_name']],
            'resource' => $row['resource'],
            'label' => $row['label'],
            'unit' => $row['unit'],
            'used' => $row['used'],
            'limit' => $row['limit'],
            'percent' => $row['percent'],
            'over_limit' => $row['over_limit'],
        ];
    }
}
