<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Exceptions\CreditLimitExceededException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Pricing\SalePriceGuard;
use App\Services\Settings\TenantSettings;
use App\Support\Money\Decimal;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;

/**
 * SHOP-03: a customer's credit limit (`customers.credit_limit`, NULL = no limit) on credit / partial sales
 * (POS checkout, POST /invoices, editing a sales invoice).
 *
 *  - preflight(): BEFORE the invoice transaction. When the request carries a manager `override` and the
 *    sale MAY go over the limit (upper bound: the current balance + every line at its submitted / list
 *    price + expenses charged to the customer, minus what is paid now), the manager PIN is verified for
 *    `customers.override_credit_limit` through SalePriceGuard (one PIN check per request, limiter hits
 *    survive). A PIN already verified for a price / discount approval is reused without a new check.
 *  - assertWithinLimit(): INSIDE the transaction, on the customer row locked FOR UPDATE and after the
 *    balance was recalculated with this invoice. Refuses (422 `customer.credit_limit_exceeded`) when the
 *    new balance is above the limit AND the sale raised the balance, unless the acting user holds the
 *    permission or a manager approved it in preflight(); an approved breach is activity-logged.
 *
 * A cash sale paid in full never changes the balance, so the limit is never checked for it. A guard built
 * without an actor (commands, jobs, seeders) enforces nothing, like the price and discount guards.
 */
final class CustomerCreditLimitGuard
{
    public const PERMISSION = 'customers.override_credit_limit';

    public const ACTIVITY_ACTION = 'credit_limit_override';

    private ?int $approverId = null;

    public function __construct(
        private readonly ?User $actor,
        private readonly SalePriceGuard $priceGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the sale request (items, payment_type, paid_amount, payments, expenses)
     *
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    public function preflight(Customer $customer, ?int $storeId, array $data, string $balanceCredit = '0.000'): void
    {
        if (! $this->actor instanceof User || $this->approverId !== null || ! $this->priceGuard->hasOverride()) {
            return;
        }

        $limit = $customer->credit_limit;
        if ($limit === null || ($data['payment_type'] ?? 'cash') === 'cash' || $this->actor->can(self::PERMISSION)) {
            return;
        }

        $upperBound = bcadd(bcsub((string) $customer->current_balance, $balanceCredit, 3), $this->maxNewCredit($customer, $storeId, $data), 3);
        if (bccomp($upperBound, Decimal::normalize((string) $limit), 3) <= 0) {
            return;
        }

        $this->approverId = $this->priceGuard->approveCreditLimit($storeId);
    }

    /**
     * @throws CreditLimitExceededException
     */
    public function assertWithinLimit(Customer $customer, string $balanceBefore, string $balanceAfter, Invoice $invoice): void
    {
        if (! $this->actor instanceof User || $customer->credit_limit === null) {
            return;
        }

        $limit = Decimal::normalize((string) $customer->credit_limit);
        // Only a sale that RAISES the balance above the limit is refused: editing an old invoice down,
        // or a sale on a customer already over the limit that adds nothing, stays possible.
        if (bccomp($balanceAfter, $balanceBefore, 3) <= 0 || bccomp($balanceAfter, $limit, 3) <= 0) {
            return;
        }

        $approverId = $this->actor->can(self::PERMISSION) ? (int) $this->actor->id : $this->approverId;
        $added = bcsub($balanceAfter, $balanceBefore, 3);

        if ($approverId === null) {
            throw CreditLimitExceededException::forCustomer(
                $limit,
                Decimal::normalize($balanceBefore),
                $added,
                app(TenantSettings::class)->currencyLabel(),
            );
        }

        app(ActivityLogService::class)->log(
            module: 'customers',
            action: self::ACTIVITY_ACTION,
            description: __('customers.activity_credit_limit_override', [
                'customer' => $customer->name,
                'number' => $invoice->invoice_number,
                'limit' => $limit,
                'balance' => $balanceAfter,
            ]),
            subject: $customer,
            properties: [
                'invoice_id' => $invoice->id,
                'credit_limit' => $limit,
                'balance_before' => Decimal::normalize($balanceBefore),
                'balance_after' => Decimal::normalize($balanceAfter),
                'override_approved_by' => $approverId,
            ],
            storeId: $invoice->store_id !== null ? (int) $invoice->store_id : null,
        );
    }

    /** The approver of a credit-limit breach verified in preflight() (null = none). */
    public function approverId(): ?int
    {
        return $this->approverId;
    }

    /**
     * Upper bound of the debt this sale adds: no discount is subtracted (discounts only lower it).
     *
     * @param  array<string, mixed>  $data
     */
    private function maxNewCredit(Customer $customer, ?int $storeId, array $data): string
    {
        $total = '0.000';
        $items = Item::query()
            ->whereIn('id', array_map('intval', array_column((array) ($data['items'] ?? []), 'item_id')))
            ->get()
            ->keyBy('id');

        foreach ((array) ($data['items'] ?? []) as $line) {
            $item = $items->get((int) ($line['item_id'] ?? 0));
            if ($item === null) {
                continue;
            }
            $submitted = isset($line['unit_price']) ? trim((string) $line['unit_price']) : '';
            $price = $submitted === '' ? $this->priceGuard->listPrice($item, $customer, $storeId)['price'] : Decimal::normalize($submitted);
            $total = bcadd($total, Decimal::mul(Decimal::normalize((string) ($line['quantity'] ?? '0')), $price), 3);
        }

        $expenses = (array) ($data['additional_expenses'] ?? []);
        if ($expenses === [] && ! empty($data['shipping_cost'])) {
            $expenses[] = ['amount' => (string) $data['shipping_cost'], 'paid_by' => 'customer_account'];
        }
        foreach ($expenses as $expense) {
            if (($expense['paid_by'] ?? 'customer_account') === 'customer_account') {
                $total = bcadd($total, Decimal::normalize((string) ($expense['amount'] ?? '0')), 3);
            }
        }

        if (($data['payment_type'] ?? 'cash') === 'partial') {
            $paid = '0.000';
            foreach ((array) ($data['payments'] ?? []) as $payment) {
                $paid = bcadd($paid, Decimal::normalize((string) ($payment['amount'] ?? '0')), 3);
            }
            if (bccomp($paid, '0.000', 3) === 0) {
                $paid = Decimal::normalize((string) ($data['paid_amount'] ?? '0'));
            }
            $total = bcsub($total, $paid, 3);
        }

        return $total;
    }
}
