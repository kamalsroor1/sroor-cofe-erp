<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Actions\Customers\ResolveWalkInCustomerAction;

/**
 * SEC-1 (L13): a sale without customer_id is booked on the walk-in customer, resolved (and
 * created if missing) in passedValidation(): after authorize() and the rules passed, never in
 * prepareForValidation() where an unauthorized request could write a customer row.
 *
 * The using request declares `customer_id` as nullable and normalizes "" / "null" to null.
 */
trait ResolvesWalkInCustomer
{
    private ?int $walkInCustomerId = null;

    protected function passedValidation(): void
    {
        if ($this->input('customer_id') === null) {
            $this->walkInCustomerId = (int) app(ResolveWalkInCustomerAction::class)->execute()->id;
            $this->merge(['customer_id' => $this->walkInCustomerId]);
        }
    }

    /**
     * @param  array-key|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        if ($this->walkInCustomerId !== null) {
            $data['customer_id'] = $this->walkInCustomerId;
        }

        return data_get($data, $key, $default);
    }

    /** Empty, "null" and missing customer ids all mean "walk-in customer". */
    protected function normalizeWalkInCustomerId(): void
    {
        $customerId = $this->input('customer_id');

        if ($customerId === null || $customerId === '' || $customerId === 'null') {
            $this->merge(['customer_id' => null]);
        }
    }
}
