<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\DTOs\Customers\CustomerDTO;
use App\Models\Customer;
use App\Models\OpeningBalance;
use App\Services\CustomerBalanceService;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateCustomerAction
{
    public function __construct(
        private readonly CustomerBalanceService $balances,
        private readonly TenantClock $clock,
    ) {}

    /**
     * Create customer inside DB transaction. A non-zero opening balance is stored as an
     * OpeningBalance entry (like the PRD-06 import) and the balance is recalculated through
     * CustomerBalanceService: writing current_balance directly would be dropped by the next updateBalance().
     */
    public function execute(CustomerDTO $dto): Customer
    {
        return DB::transaction(function () use ($dto) {
            $customer = Customer::create([
                'name' => $dto->name,
                'phone' => $dto->phone,
                'address' => $dto->address,
                'tax_number' => $dto->tax_number,
                'current_balance' => '0.000',
                'is_active' => $dto->is_active,
                'notes' => $dto->notes,
            ]);

            $opening = $this->openingAmount($dto->opening_balance);
            if (bccomp($opening, '0.000', 3) !== 0) {
                OpeningBalance::query()->create([
                    'party_type' => OpeningBalance::PARTY_CUSTOMER,
                    'party_id' => $customer->id,
                    'amount' => $opening,
                    'balance_date' => $this->clock->businessDate(),
                    'notes' => __('import.opening_balance_note_manual'),
                    'user_id' => Auth::id(),
                ]);
                $this->balances->updateBalance((int) $customer->id);
                $customer->refresh();
            }

            return $customer;
        });
    }

    private function openingAmount(string $value): string
    {
        if (preg_match('/^-?\d+(\.\d+)?$/', trim($value)) !== 1) {
            throw ValidationException::withMessages(['opening_balance' => __('validation.numeric', ['attribute' => 'opening_balance'])]);
        }

        return bcadd(trim($value), '0', 3);
    }
}
