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

    /** POS-02: is this customer a generic walk-in (cash) customer? Classifies the row itself; no query. */
    public function isWalkIn(Customer $customer): bool
    {
        return (string) $customer->phone === self::PLACEHOLDER_PHONE
            || in_array((string) $customer->name, self::walkInNames(), true);
    }

    /** @return list<string> */
    private static function walkInNames(): array
    {
        return [
            ...self::LEGACY_NAMES,
            (string) __('pos.walk_in_customer', [], 'ar'),
            (string) __('pos.walk_in_customer', [], 'en'),
            (string) __('pos.walk_in_customer'),
        ];
    }

    private function findExisting(): ?Customer
    {
        return Customer::query()
            ->whereIn('name', self::walkInNames())
            ->orWhere('phone', self::PLACEHOLDER_PHONE)
            ->orderBy('id')
            ->first();
    }

    public function execute(): Customer
    {
        $existing = $this->findExisting();

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
