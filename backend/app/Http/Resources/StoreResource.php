<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'code'      => $this->code,
            'type'      => $this->type,
            'is_main'   => (bool)$this->is_main,
            'is_active' => (bool)$this->is_active,
            'phone'     => $this->phone,
            'address'   => $this->address,
        ];
    }
}
