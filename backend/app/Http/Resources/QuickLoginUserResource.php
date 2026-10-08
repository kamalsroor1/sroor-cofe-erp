<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class QuickLoginUserResource extends JsonResource
{
    /**
     * Only id + name: the picker must never leak phone/email.
     *
     * @return array{id: int, name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
        ];
    }
}
