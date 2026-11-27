<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\DTOs\Customers\CustomerDTO;
use App\Enums\LimitResource;
use App\Models\Customer;
use App\Models\OpeningBalance;
use App\Services\CustomerBalanceService;
use App\Services\Entitlements\PlanEnforcer;
use App\Services\Settings\TenantSettings;
use App\Support\PartyLimitCounter;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateCustomerAction
{
    public function __construct(
        private readonly CustomerBalanceService $balances,
        private readonly TenantClock $clock,
        private readonly PlanEnforcer $planEnforcer,
        private readonly TenantSettings $settings,
    ) {}

    /**
     * Create customer inside DB transaction. A non-zero opening balance is stored as an
     * OpeningBalance entry (like the PRD-06 import) and the balance is recalculated through
     * CustomerBalanceService: writing current_balance directly would be dropped by the next updateBalance().
     */
    public function execute(CustomerDTO $dto): Customer
    {
        return DB::transaction(function () use ($dto) {
            // PLAN-11: plan customers limit, counted under the tenant lock (walk-in not counted).
            $this->planEnforcer->assertWithinLimit(LimitResource::Customers, fn (): int => PartyLimitCounter::customers());

            $customer = Customer::create([
                'name' => $dto->name,
                'phone' => $dto->phone,
                'address' => $dto->address,
                'tax_number' => $dto->tax_number,
                'current_balance' => '0.000',
                // SHOP-03: no credit_limit field in the request = the shop default (null = no limit).
                'credit_limit' => $dto->credit_limit_provided ? $dto->credit_limit : $this->settings->customersDefaultCreditLimit(),
                'price_tier' => $dto->price_tier,
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
