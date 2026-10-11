<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;

/**
 * The shop's walk-in (cash) customer a sale without a customer is booked on: the existing one
 * (legacy names or the placeholder phone), else created once.
 *
 * SEC-1 (L13): only ever called AFTER the request was authorized and validated (see
 * Http\Requests\Concerns\ResolvesWalkInCustomer), so an unauthorized request never writes a
 * customer row.
 */
final class ResolveWalkInCustomerAction
{
    /** Placeholder phone of the walk-in customer (also the lookup key of legacy rows). */
    public const PLACEHOLDER_PHONE = '0000000000';

    /** Names legacy tenants created the walk-in customer with (data lookup, not UI text). */
    private const LEGACY_NAMES = ['نقدي عام', 'عميل نقدي'];

    public function execute(): Customer
    {
        $existing = Customer::query()
            ->whereIn('name', [...self::LEGACY_NAMES, (string) __('pos.walk_in_customer')])
            ->orWhere('phone', self::PLACEHOLDER_PHONE)
            ->orderBy('id')
            ->first();

        if ($existing instanceof Customer) {
            return $existing;
        }

        return Customer::query()->create([
            'name' => __('pos.walk_in_customer'),
            'phone' => self::PLACEHOLDER_PHONE,
            'current_balance' => 0,
            'price_tier' => 'retail',
            'is_active' => true,
        ]);
    }
}
