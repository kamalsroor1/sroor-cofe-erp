<?php

namespace App\Http\Resources;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Models\Customer;
use App\Support\Money\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class POSCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'price_tier' => $this->price_tier ?? 'retail',
            'current_balance' => Decimal::normalize((string) ($this->current_balance ?? 0)),
            'address' => $this->address,
            'is_walk_in' => app(ResolveWalkInCustomerAction::class)->isWalkIn($this->resource),
        ];
    }
}
