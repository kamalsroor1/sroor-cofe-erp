<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Models\TreasuryTransfer;
use App\Support\Money\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TreasuryTransfer
 */
class TreasuryTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'from_method' => $this->from_method,
            'from_label' => PaymentMethod::tryFrom((string) $this->from_method)?->label() ?? $this->from_method,
            'to_method' => $this->to_method,
            'to_label' => PaymentMethod::tryFrom((string) $this->to_method)?->label() ?? $this->to_method,
            'amount' => Decimal::normalize((string) $this->amount),
            'transfer_fee' => Decimal::normalize((string) $this->transfer_fee),
            'transfer_date' => $this->transfer_date?->toDateString(),
            'notes' => $this->notes,
            'user_name' => $this->creator?->name,
            'store_name' => $this->store?->name,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
