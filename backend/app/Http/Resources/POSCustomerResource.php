<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class POSCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'price_tier' => $this->price_tier ?? 'retail',
            'current_balance' => (float) ($this->current_balance ?? 0),
            'address' => $this->address,
        ];
    }
}
