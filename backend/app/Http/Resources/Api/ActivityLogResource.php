<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $badge = $this->module_badge;

        return [
            'id'          => $this->id,
            'description' => $this->description,
            'module_icon' => $badge['icon'] ?? '⚙️',
            'user_name'   => $this->user?->name ?? __('common.system'),
            'time_ago'    => $this->created_at?->diffForHumans(),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
