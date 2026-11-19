<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\LimitResource;
use App\Models\Customer;
use App\Services\Entitlements\PlanEnforcer;
use App\Services\Settings\TenantSettings;
use App\Support\PartyLimitCounter;
use Illuminate\Support\Facades\DB;

/**
 * POS quick-add customer. PLAN-11: takes a `customers` plan slot like CreateCustomerAction,
 * checked under the tenant lock inside the transaction.
 */
class QuickCreateCustomerAction
{
    public function __construct(
        private readonly PlanEnforcer $planEnforcer,
        private readonly TenantSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated StoreQuickCustomerRequest data
     */
    public function execute(array $data): Customer
    {
        return DB::transaction(function () use ($data): Customer {
            $this->planEnforcer->assertWithinLimit(LimitResource::Customers, fn (): int => PartyLimitCounter::customers());

            return Customer::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'price_tier' => $data['price_tier'] ?? 'retail',
                'address' => $data['address'] ?? null,
                'is_active' => true,
                'current_balance' => '0.000',
                // SHOP-03: a POS quick-add customer gets the shop's default credit limit (null = no limit).
                'credit_limit' => $this->settings->customersDefaultCreditLimit(),
            ]);
        });
    }
}
