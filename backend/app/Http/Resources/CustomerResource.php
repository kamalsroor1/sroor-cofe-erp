<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Models\Customer;
use App\Support\Money\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'tax_number' => $this->tax_number,
            'current_balance' => Decimal::normalize((string) $this->current_balance),
            'credit_limit' => Decimal::normalize((string) ($this->credit_limit ?? 0)),
            'is_active' => (bool) $this->is_active,
            'is_walk_in' => app(ResolveWalkInCustomerAction::class)->isWalkIn($this->resource),
            'price_tier' => $this->price_tier ?? 'retail',
            'notes' => $this->notes,
            'invoices_count' => $this->whenCounted('invoices', $this->invoices_count),
            'payments_count' => $this->whenCounted('payments', $this->payments_count),
            'can_be_deleted' => method_exists($this->resource, 'canBeDeleted') ? $this->canBeDeleted() : true,
            'deletion_blockers' => method_exists($this->resource, 'getDeletionBlockers') ? $this->getDeletionBlockers() : [],
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
