<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PLAN-14: one plan change of the central audit log. Values are never returned, only which
 * fields were changed.
 *
 * @mixin CentralAuditLog
 */
final class PlanChangeLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $changed = $this->properties['changed'] ?? [];
        $causer = $this->causer;

        return [
            'id' => $this->id,
            'event' => $this->event,
            'changed' => is_array($changed) ? array_values(array_map('strval', $changed)) : [],
            'operator' => $causer instanceof CentralUser ? $causer->name : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
