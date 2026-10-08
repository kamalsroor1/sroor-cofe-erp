<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Shared checkout contract for sales invoices (/invoices) and POS checkout (/pos/checkout):
 * split payments[], invoice expenses (additional_expenses[] or the SPA alias expenses[]),
 * and legacy payment value aliases.
 */
trait ValidatesCheckoutPayments
{
    /** Mirrors App\Enums\PaymentMethod. */
    protected const CHECKOUT_PAYMENT_METHODS = 'cash,instapay,e_wallet,visa,bank_transfer,check,other';

    protected const CHECKOUT_EXPENSE_PAID_BY = 'customer_account,treasury_cash,treasury_instapay,treasury_e_wallet';

    /**
     * Normalize legacy aliases before validation:
     *  - payment method 'smart_wallet' => 'e_wallet' (header and every split line)
     *  - payment_type 'bank_transfer' => payment_type 'cash' + payment_method 'bank_transfer'
     *    (invoices.payment_type only stores cash/credit/partial).
     */
    protected function normalizeCheckoutPayload(): void
    {
        $merge = [];

        if ($this->input('payment_method') === 'smart_wallet') {
            $merge['payment_method'] = 'e_wallet';
        }

        if ($this->input('payment_type') === 'bank_transfer') {
            $merge['payment_type'] = 'cash';
            $merge['payment_method'] = 'bank_transfer';
        }

        $payments = $this->input('payments');
        if (is_array($payments)) {
            foreach ($payments as $i => $payment) {
                if (is_array($payment) && ($payment['method'] ?? null) === 'smart_wallet') {
                    $payments[$i]['method'] = 'e_wallet';
                }
            }
            $merge['payments'] = $payments;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Rules for payments[] and the invoice expenses lists. Both expense keys are validated
     * so errors are reported under the key the client actually sent.
     */
    protected function checkoutPaymentAndExpenseRules(): array
    {
        $rules = [
            'payments' => ['nullable', 'array', 'max:10'],
            'payments.*.method' => ['required', 'string', 'in:'.self::CHECKOUT_PAYMENT_METHODS],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ];

        foreach (['additional_expenses', 'expenses'] as $key) {
            $rules[$key] = ['nullable', 'array', 'max:20'];
            $rules["{$key}.*.title"] = ['required', 'string', 'max:150'];
            $rules["{$key}.*.amount"] = ['required', 'numeric', 'gt:0', 'decimal:0,3'];
            $rules["{$key}.*.paid_by"] = ['nullable', 'string', 'in:'.self::CHECKOUT_EXPENSE_PAID_BY];
            $rules["{$key}.*.allocation_method"] = ['nullable', 'in:by_quantity,by_value,equal'];
            $rules["{$key}.*.notes"] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }
}
