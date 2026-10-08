<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\Store;
use Exception;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        protected StockService $stockService,
        protected CustomerBalanceService $customerBalanceService,
        protected AuditLogService $auditLogService,
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * Confirm a sales invoice atomically.
     *
     * Idempotent when $data['client_uuid'] is set: a replay of the same key returns the
     * original invoice (wasRecentlyCreated === false) with no new side effects.
     */
    public function confirmInvoice(array $data): Invoice
    {
        $clientUuid = ! empty($data['client_uuid']) ? (string) $data['client_uuid'] : null;

        try {
            return $this->persistConfirmedInvoice($data, $clientUuid);
        } catch (UniqueConstraintViolationException $e) {
            // Backstop for a concurrent insert of the same client_uuid that slipped past the
            // replay check. Any other unique violation (e.g. invoice_number) is rethrown.
            if ($clientUuid === null) {
                throw $e;
            }

            $existing = Invoice::where('client_uuid', $clientUuid)->first();
            if (! $existing) {
                throw $e;
            }

            $this->assertReplayMatches($existing, (int) $data['customer_id'], $this->resolveInvoiceStoreId($data));

            return $existing;
        }
    }

    private function persistConfirmedInvoice(array $data, ?string $clientUuid): Invoice
    {
        return DB::transaction(function () use ($data, $clientUuid) {
            $customer = Customer::where('id', $data['customer_id'])->lockForUpdate()->firstOrFail();

            $subtotal = '0.000';
            $totalCost = '0.000';

            $storeId = $this->resolveInvoiceStoreId($data);

            if ($clientUuid !== null) {
                $existing = Invoice::where('client_uuid', $clientUuid)->lockForUpdate()->first();
                if ($existing) {
                    $this->assertReplayMatches($existing, (int) $customer->id, $storeId);

                    return $existing;
                }
            }

            $invoiceNumber = $data['invoice_number'] ?? $this->generateUniqueNumber($storeId);

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'client_uuid' => $clientUuid,
                'customer_id' => $customer->id,
                'user_id' => Auth::id() ?? 1,
                'store_id' => $storeId,
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'payment_type' => $data['payment_type'] ?? 'cash',
                'payment_method' => $data['payment_method'] ?? 'cash',
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
                'subtotal' => '0.000',
                'discount_type' => $data['discount_type'] ?? 'fixed',
                'discount_value' => $data['discount_value'] ?? '0.000',
                'discount_amount' => '0.000',
                'net_total' => '0.000',
                'paid_amount' => '0.000',
                'remaining_amount' => '0.000',
                'total_cost' => '0.000',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                // S1: Lock item row and verify stock
                $item = Item::where('id', $line['item_id'])->lockForUpdate()->firstOrFail();

                $qty = (string) $line['quantity'];

                // Enforce integer-only quantity for discrete units
                $discreteUnits = ['قطعة', 'حبة', 'علبة', 'باكت', 'كرتونة', 'شيكارة', 'طرد', 'دستة', 'جوال', 'piece', 'pcs', 'box', 'carton', 'pack', 'unit'];
                $itemUnit = trim(mb_strtolower((string) $item->unit));
                if (in_array($itemUnit, $discreteUnits, true) && fmod((float) $qty, 1.0) != 0.0) {
                    throw new \DomainException("الصنف '{$item->name}' بالقطعة/العلبة يقبل فقط أعداداً صحيحة، ولا يمكن بيع أجزاء أو كسور ({$qty} {$item->unit}).");
                }

                $unitPrice = (string) $line['unit_price'];
                $itemDiscount = (string) ($line['discount_amount'] ?? '0.000');

                // Line Total = (Quantity * Unit Price) - Item Discount
                $grossLineTotal = bcmul($qty, $unitPrice, 3);
                $netLineTotal = bcsub($grossLineTotal, $itemDiscount, 3);
                if (bccomp($netLineTotal, '0.000', 3) < 0) {
                    $netLineTotal = '0.000';
                }

                $effectiveCost = bccomp($item->weighted_avg_cost, '0.000', 3) > 0
                    ? $item->weighted_avg_cost
                    : $item->cost_price;

                $lineCost = bcmul($qty, $effectiveCost, 3);
                $totalCost = bcadd($totalCost, $lineCost, 3);

                // Create invoice item row
                $invoice->items()->create([
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'cost_price' => $effectiveCost,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $itemDiscount,
                    'total_price' => $netLineTotal,
                ]);

                // S2: Deduct stock atomically and log movement
                $this->stockService->deductStock(
                    item: $item,
                    quantity: $qty,
                    source: $invoice,
                    documentNumber: $invoice->invoice_number,
                    movementType: 'sales_out',
                    notes: "صرف مبيعات بالفاتورة رقم {$invoice->invoice_number}",
                    storeId: $storeId
                );

                $subtotal = bcadd($subtotal, $netLineTotal, 3);
            }

            // S3: Invoice-level Discount Calculation
            $discountType = $data['discount_type'] ?? 'fixed';
            $discountValue = $data['discount_value'] ?? '0.000';
            $invoiceDiscountAmount = '0.000';

            if ($discountType === 'percentage') {
                // (Subtotal * Discount Value) / 100
                $invoiceDiscountAmount = bcdiv(bcmul($subtotal, $discountValue, 4), '100', 3);
            } else {
                $invoiceDiscountAmount = $discountValue;
            }

            if (bccomp($invoiceDiscountAmount, $subtotal, 3) > 0) {
                $invoiceDiscountAmount = $subtotal;
            }

            // S3.5: Process Dynamic Additional Expenses
            $rawExpenses = $data['additional_expenses'] ?? [];
            $additionalExpensesTotal = '0.000';
            $customerExpensesTotal = '0.000';

            if (! empty($data['shipping_cost']) && bccomp((string) $data['shipping_cost'], '0.000', 3) > 0 && empty($rawExpenses)) {
                $rawExpenses[] = [
                    'title' => 'مصاريف شحن وتوصيل',
                    'amount' => (string) $data['shipping_cost'],
                    'allocation_method' => 'by_quantity',
                    'paid_by' => 'customer_account',
                ];
            }

            foreach ($rawExpenses as $exp) {
                $expAmount = (string) ($exp['amount'] ?? '0.000');
                if (bccomp($expAmount, '0.000', 3) <= 0) {
                    continue;
                }
                $title = trim($exp['title'] ?? 'مصاريف إضافية');
                $method = $exp['allocation_method'] ?? 'by_quantity';
                $paidBy = $exp['paid_by'] ?? 'customer_account';
                $expNotes = $exp['notes'] ?? null;

                $additionalExpensesTotal = bcadd($additionalExpensesTotal, $expAmount, 3);

                $expenseRecord = $invoice->additionalExpenses()->create([
                    'title' => $title,
                    'amount' => $expAmount,
                    'allocation_method' => $method,
                    'paid_by' => $paidBy,
                    'notes' => $expNotes,
                ]);

                if ($paidBy === 'customer_account' || $paidBy === 'supplier_account') {
                    $customerExpensesTotal = bcadd($customerExpensesTotal, $expAmount, 3);
                } else {
                    $paymentMethod = str_replace('treasury_', '', $paidBy) ?: 'cash';
                    $expenseNumber = 'EXP-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));
                    $expense = Expense::create([
                        'expense_number' => $expenseNumber,
                        'category' => 'shipping',
                        'title' => "مصروف فاتورة [{$invoice->invoice_number}]: {$title}",
                        'amount' => $expAmount,
                        'payment_method' => $paymentMethod,
                        'expense_date' => $invoice->invoice_date,
                        'store_id' => $invoice->store_id,
                        'user_id' => Auth::id() ?? 1,
                        'notes' => "مصروف خدمات/شحن مسدد من الخزينة لفاتورة مبيعات رقم {$invoice->invoice_number}",
                    ]);

                    $expenseRecord->update(['notes' => ($expNotes ? "{$expNotes} | " : '')."رقم المصروف: {$expenseNumber}"]);
                }
            }

            $afterDiscount = bcsub($subtotal, $invoiceDiscountAmount, 3);
            $netTotal = bcadd($afterDiscount, $customerExpensesTotal, 3);

            // S4: Payments and Remaining Amounts (bounds enforced; a violation rolls back the whole transaction)
            $checkout = $this->resolveCheckoutPayments(
                paymentType: (string) ($data['payment_type'] ?? 'cash'),
                payments: $this->normalizeSplitPayments($data['payments'] ?? []),
                netTotal: $netTotal,
                paidAmountInput: isset($data['paid_amount']) ? (string) $data['paid_amount'] : null,
                fallbackMethod: (string) ($data['payment_method'] ?? PaymentMethod::Cash->value),
            );
            $paidAmount = $checkout['paid'];

            $remainingAmount = bcsub($netTotal, $paidAmount, 3);

            $paymentStatus = 'unpaid';
            if (bccomp($remainingAmount, '0.000', 3) === 0) {
                $paymentStatus = 'paid';
            } elseif (bccomp($paidAmount, '0.000', 3) > 0) {
                $paymentStatus = 'partially_paid';
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $invoiceDiscountAmount,
                'shipping_cost' => $customerExpensesTotal,
                'net_total' => $netTotal,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'change_amount' => $checkout['change'],
                'payment_status' => $paymentStatus,
                'total_cost' => $totalCost,
            ]);

            // S5: One voucher per resolved line. Lines already exclude the change, so the
            // recorded payments sum exactly to paid_amount (never inflating balance/treasury).
            $voucherNote = $checkout['split']
                ? __('invoices.payment_note_split', ['number' => $invoice->invoice_number])
                : __('invoices.payment_note_on_issue', ['number' => $invoice->invoice_number]);

            foreach ($checkout['lines'] as $line) {
                Payment::create([
                    'payment_number' => 'PAY-INV-'.strtoupper(uniqid()),
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'user_id' => Auth::id() ?? 1,
                    'amount' => $line['amount'],
                    'payment_date' => $invoice->invoice_date,
                    'payment_method' => $line['method'],
                    'notes' => $voucherNote,
                ]);
            }

            // S6: Update Customer balance
            $this->customerBalanceService->updateBalance($customer->id);

            // S7: Audit & Activity Log
            $this->auditLogService->log(
                action: 'invoice_confirmed',
                auditable: $invoice,
                oldValues: null,
                newValues: $invoice->toArray()
            );

            $this->activityLogService->logSales(
                action: 'created',
                invoice: $invoice,
                description: "تم إنشاء واعتماد فاتورة مبيعات جديدة رقم [{$invoice->invoice_number}] للعميل ({$customer->name}) بقيمة ".number_format((float) $invoice->net_total, 2).' ج.م'
            );

            return $invoice;
        });
    }

    private function resolveInvoiceStoreId(array $data): ?int
    {
        $storeId = $data['store_id'] ?? Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id;

        return $storeId !== null ? (int) $storeId : null;
    }

    /**
     * A replayed client_uuid must belong to the same customer and store; otherwise the key
     * was reused for a different sale and must not leak the original invoice.
     */
    private function assertReplayMatches(Invoice $existing, int $customerId, ?int $storeId): void
    {
        $existingStoreId = $existing->store_id !== null ? (int) $existing->store_id : null;

        if ((int) $existing->customer_id !== $customerId || $existingStoreId !== $storeId) {
            throw ValidationException::withMessages([
                'client_uuid' => [__('invoices.idempotency_key_conflict')],
            ]);
        }
    }

    /**
     * Resolve what is actually paid on checkout (split-payment contract, CTO option A).
     *
     * - credit:  no payments allowed; paid 0.
     * - partial: 0 < paid < net (payments[] total, else paid_amount); never any change.
     * - cash (any other type) with payments[]: total may exceed net only through cash.
     *   Non-cash above net or total below net is rejected. The change is trimmed off the
     *   cash lines from the LAST one backwards; lines reaching 0.000 are dropped, so the
     *   resolved lines always sum exactly to net.
     * - cash without payments[]: paid = net in a single line with $fallbackMethod.
     *
     * Pure calculation: it runs inside the caller's transaction and writes nothing.
     *
     * @param  list<array{method: string, amount: string}>  $payments
     * @return array{paid: string, change: string, split: bool, lines: list<array{method: string, amount: string}>}
     *
     * @throws ValidationException
     */
    private function resolveCheckoutPayments(
        string $paymentType,
        array $payments,
        string $netTotal,
        ?string $paidAmountInput,
        string $fallbackMethod,
    ): array {
        $split = $payments !== [];
        $lines = [];
        $total = '0.000';
        $cashTotal = '0.000';
        $nonCashTotal = '0.000';

        foreach ($payments as $p) {
            $amount = bcadd($p['amount'], '0', 3);
            $lines[] = ['method' => $p['method'], 'amount' => $amount];
            $total = bcadd($total, $amount, 3);
            if ($p['method'] === PaymentMethod::Cash->value) {
                $cashTotal = bcadd($cashTotal, $amount, 3);
            } else {
                $nonCashTotal = bcadd($nonCashTotal, $amount, 3);
            }
        }

        if ($paymentType === 'credit') {
            if ($split) {
                throw ValidationException::withMessages([
                    'payments' => __('invoices.credit_invoice_cannot_have_payments'),
                ]);
            }

            return ['paid' => '0.000', 'change' => '0.000', 'split' => false, 'lines' => []];
        }

        if ($paymentType === 'partial') {
            $paid = $split ? $total : bcadd($paidAmountInput ?? '0', '0', 3);
            if (! (bccomp($paid, '0.000', 3) > 0 && bccomp($paid, $netTotal, 3) < 0)) {
                throw ValidationException::withMessages([
                    'paid_amount' => __('invoices.partial_paid_out_of_range', ['net' => $netTotal]),
                ]);
            }

            if (! $split) {
                $lines = [['method' => $fallbackMethod, 'amount' => $paid]];
            }

            return ['paid' => $paid, 'change' => '0.000', 'split' => $split, 'lines' => $lines];
        }

        // cash (and any fully-paid type such as bank_transfer)
        if (! $split) {
            $lines = bccomp($netTotal, '0.000', 3) > 0
                ? [['method' => $fallbackMethod, 'amount' => $netTotal]]
                : [];

            return ['paid' => $netTotal, 'change' => '0.000', 'split' => false, 'lines' => $lines];
        }

        if (bccomp($nonCashTotal, $netTotal, 3) > 0) {
            throw ValidationException::withMessages([
                'payments' => __('invoices.split_payment_non_cash_exceeds_due', ['non_cash' => $nonCashTotal, 'net' => $netTotal]),
            ]);
        }

        if (bccomp($total, $netTotal, 3) < 0) {
            throw ValidationException::withMessages([
                'payments' => __('invoices.split_payment_below_net', ['paid' => $total, 'net' => $netTotal]),
            ]);
        }

        // nonCash <= net <= total  =>  change = total - net <= cashTotal, so cash always covers it.
        $change = bcsub($total, $netTotal, 3);
        $toTrim = $change;
        for ($i = count($lines) - 1; $i >= 0 && bccomp($toTrim, '0.000', 3) > 0; $i--) {
            if ($lines[$i]['method'] !== PaymentMethod::Cash->value) {
                continue;
            }
            $take = bccomp($lines[$i]['amount'], $toTrim, 3) <= 0 ? $lines[$i]['amount'] : $toTrim;
            $lines[$i]['amount'] = bcsub($lines[$i]['amount'], $take, 3);
            $toTrim = bcsub($toTrim, $take, 3);
        }

        $lines = array_values(array_filter(
            $lines,
            static fn (array $line): bool => bccomp($line['amount'], '0.000', 3) > 0
        ));

        $resolvedTotal = '0.000';
        foreach ($lines as $line) {
            $resolvedTotal = bcadd($resolvedTotal, $line['amount'], 3);
        }
        if (bccomp($toTrim, '0.000', 3) !== 0 || bccomp($resolvedTotal, $netTotal, 3) !== 0) {
            // Unreachable given the checks above; guards the ledger against a future regression.
            throw new \LogicException('Resolved checkout payments do not sum to the invoice net.');
        }

        return ['paid' => $netTotal, 'change' => $change, 'split' => true, 'lines' => $lines];
    }

    /**
     * Normalize split payment lines to ['method' => string, 'amount' => string] with
     * a positive amount; non-array input (e.g. null) yields an empty list.
     *
     * @return list<array{method: string, amount: string}>
     */
    private function normalizeSplitPayments(mixed $payments): array
    {
        if (! is_array($payments)) {
            return [];
        }

        $normalized = [];
        foreach ($payments as $p) {
            if (! is_array($p)) {
                continue;
            }
            $amount = (string) ($p['amount'] ?? '0.000');
            if (bccomp($amount, '0.000', 3) <= 0) {
                continue;
            }
            $normalized[] = [
                'method' => (string) ($p['method'] ?? 'cash'),
                'amount' => $amount,
            ];
        }

        return $normalized;
    }

    /**
     * Cancel an invoice and reverse stock securely
     */
    public function cancelInvoice(Invoice $invoice, string $reason): Invoice
    {
        if (auth()->check() && ! auth()->user()->hasRole('admin')) {
            throw new Exception('عفواً، لا يملك صلاحية إلغاء الفواتير سوى المدير العام.');
        }

        return DB::transaction(function () use ($invoice, $reason) {
            $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->status === 'cancelled') {
                throw new Exception('هذه الفاتورة ملغاة بالفعل مسبقاً.');
            }

            $oldState = $lockedInvoice->toArray();

            // Reverse stock for each line
            foreach ($lockedInvoice->items as $itemLine) {
                $item = Item::where('id', $itemLine->item_id)->lockForUpdate()->firstOrFail();

                $this->stockService->addStock(
                    item: $item,
                    quantity: $itemLine->quantity,
                    unitCost: $itemLine->cost_price,
                    source: $lockedInvoice,
                    documentNumber: $lockedInvoice->invoice_number,
                    movementType: 'cancellation_in',
                    notes: "إلغاء فاتورة مبيعات رقم {$lockedInvoice->invoice_number} - سبب: {$reason}",
                    storeId: $lockedInvoice->store_id
                );
            }

            $lockedInvoice->update([
                'status' => 'cancelled',
                'remaining_amount' => '0.000',
                'notes' => ($lockedInvoice->notes ? $lockedInvoice->notes."\n" : '')."تم الإلغاء: {$reason}",
            ]);

            // Recalculate customer balance
            $this->customerBalanceService->updateBalance($lockedInvoice->customer_id);

            // Audit & Activity log
            $this->auditLogService->log(
                action: 'invoice_cancelled',
                auditable: $lockedInvoice,
                oldValues: $oldState,
                newValues: $lockedInvoice->toArray()
            );

            $this->activityLogService->logSales(
                action: 'cancelled',
                invoice: $lockedInvoice,
                description: "تم إلغاء فاتورة المبيعات رقم [{$lockedInvoice->invoice_number}] وإرجاع بضاعتها للمخزن. سبب الإلغاء: {$reason}",
                properties: ['reason' => $reason]
            );

            return $lockedInvoice;
        });
    }

    /**
     * Update an existing confirmed invoice atomically with inventory and balance adjustments
     */
    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();
            $oldCustomerId = $lockedInvoice->customer_id;
            $newCustomerId = $data['customer_id'];

            // 1. If invoice was confirmed, reverse previous stock back to warehouse
            if ($lockedInvoice->status === 'confirmed') {
                foreach ($lockedInvoice->items as $oldLine) {
                    $item = Item::where('id', $oldLine->item_id)->lockForUpdate()->first();
                    if ($item) {
                        $this->stockService->addStock(
                            item: $item,
                            quantity: $oldLine->quantity,
                            unitCost: $oldLine->cost_price,
                            source: $lockedInvoice,
                            documentNumber: $lockedInvoice->invoice_number,
                            movementType: 'cancellation_in',
                            notes: "إرجاع مخزون لتعديل الفاتورة رقم {$lockedInvoice->invoice_number}",
                            storeId: $lockedInvoice->store_id
                        );
                    }
                }
            }

            // Delete old items and previous stock movements for this invoice
            $lockedInvoice->items()->delete();
            StockMovement::where('source_type', Invoice::class)
                ->where('source_id', $lockedInvoice->id)
                ->delete();

            // 2. Add new items, deduct new stock, calculate new subtotal and total cost
            $subtotal = '0.000';
            $totalCost = '0.000';

            foreach ($data['items'] as $line) {
                $item = Item::where('id', $line['item_id'])->lockForUpdate()->firstOrFail();
                $qty = (string) $line['quantity'];
                $unitPrice = (string) $line['unit_price'];
                $itemDiscount = (string) ($line['discount_amount'] ?? '0.000');

                $grossLineTotal = bcmul($qty, $unitPrice, 3);
                $netLineTotal = bcsub($grossLineTotal, $itemDiscount, 3);
                if (bccomp($netLineTotal, '0.000', 3) < 0) {
                    $netLineTotal = '0.000';
                }

                $effectiveCost = bccomp($item->weighted_avg_cost, '0.000', 3) > 0
                    ? $item->weighted_avg_cost
                    : $item->cost_price;

                $lineCost = bcmul($qty, $effectiveCost, 3);
                $totalCost = bcadd($totalCost, $lineCost, 3);

                $lockedInvoice->items()->create([
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'cost_price' => $effectiveCost,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $itemDiscount,
                    'total_price' => $netLineTotal,
                ]);

                $this->stockService->deductStock(
                    item: $item,
                    quantity: $qty,
                    source: $lockedInvoice,
                    documentNumber: $lockedInvoice->invoice_number,
                    movementType: 'sales_out',
                    notes: "صرف مبيعات بتعديل الفاتورة رقم {$lockedInvoice->invoice_number}",
                    storeId: $lockedInvoice->store_id
                );

                $subtotal = bcadd($subtotal, $netLineTotal, 3);
            }

            // 3. Invoice-level discount calculation
            $discountType = $data['discount_type'] ?? 'fixed';
            $discountValue = (string) ($data['discount_value'] ?? '0.000');
            $invoiceDiscountAmount = '0.000';

            if ($discountType === 'percentage') {
                $invoiceDiscountAmount = bcdiv(bcmul($subtotal, $discountValue, 4), '100', 3);
            } else {
                $invoiceDiscountAmount = $discountValue;
            }

            if (bccomp($invoiceDiscountAmount, $subtotal, 3) > 0) {
                $invoiceDiscountAmount = $subtotal;
            }

            // 3.5. Dynamic additional expenses in edit
            $rawExpenses = $data['additional_expenses'] ?? [];
            $additionalExpensesTotal = '0.000';
            $customerExpensesTotal = '0.000';

            // Delete old additional expenses and their payment vouchers
            foreach ($lockedInvoice->additionalExpenses as $oldExp) {
                if ($oldExp->payment_id) {
                    Payment::where('id', $oldExp->payment_id)->delete();
                }
                $oldExp->delete();
            }

            if (! empty($data['shipping_cost']) && bccomp((string) $data['shipping_cost'], '0.000', 3) > 0 && empty($rawExpenses)) {
                $rawExpenses[] = [
                    'title' => 'مصاريف شحن وتوصيل',
                    'amount' => (string) $data['shipping_cost'],
                    'allocation_method' => 'by_quantity',
                    'paid_by' => 'customer_account',
                ];
            }

            foreach ($rawExpenses as $exp) {
                $expAmount = (string) ($exp['amount'] ?? '0.000');
                if (bccomp($expAmount, '0.000', 3) <= 0) {
                    continue;
                }
                $title = trim($exp['title'] ?? 'مصاريف إضافية');
                $method = $exp['allocation_method'] ?? 'by_quantity';
                $paidBy = $exp['paid_by'] ?? 'customer_account';
                $expNotes = $exp['notes'] ?? null;

                $additionalExpensesTotal = bcadd($additionalExpensesTotal, $expAmount, 3);

                $expenseRecord = $lockedInvoice->additionalExpenses()->create([
                    'title' => $title,
                    'amount' => $expAmount,
                    'allocation_method' => $method,
                    'paid_by' => $paidBy,
                    'notes' => $expNotes,
                ]);

                if ($paidBy === 'customer_account' || $paidBy === 'supplier_account') {
                    $customerExpensesTotal = bcadd($customerExpensesTotal, $expAmount, 3);
                } else {
                    $paymentMethod = str_replace('treasury_', '', $paidBy) ?: 'cash';
                    $expenseNumber = 'EXP-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));
                    $expense = Expense::create([
                        'expense_number' => $expenseNumber,
                        'category' => 'shipping',
                        'title' => "مصروف فاتورة [{$lockedInvoice->invoice_number}]: {$title}",
                        'amount' => $expAmount,
                        'payment_method' => $paymentMethod,
                        'expense_date' => $data['invoice_date'] ?? $lockedInvoice->invoice_date,
                        'store_id' => $lockedInvoice->store_id,
                        'user_id' => Auth::id() ?? 1,
                        'notes' => "مصروف خدمات/شحن مسدد من الخزينة لفاتورة مبيعات رقم {$lockedInvoice->invoice_number}",
                    ]);

                    $expenseRecord->update(['notes' => ($expNotes ? "{$expNotes} | " : '')."رقم المصروف: {$expenseNumber}"]);
                }
            }

            $afterDiscount = bcsub($subtotal, $invoiceDiscountAmount, 3);
            $netTotal = bcadd($afterDiscount, $customerExpensesTotal, 3);

            // 4. Payment calculations
            $paymentType = $data['payment_type'] ?? 'cash';
            $paidAmount = '0.000';

            if ($paymentType === 'cash') {
                $paidAmount = $netTotal;
            } elseif ($paymentType === 'partial') {
                $paidAmount = (string) ($data['paid_amount'] ?? '0.000');
            } else {
                $paidAmount = '0.000';
            }

            $remainingAmount = bcsub($netTotal, $paidAmount, 3);
            if (bccomp($remainingAmount, '0.000', 3) < 0) {
                $remainingAmount = '0.000';
            }

            $paymentStatus = 'unpaid';
            if (bccomp($remainingAmount, '0.000', 3) === 0) {
                $paymentStatus = 'paid';
            } elseif (bccomp($paidAmount, '0.000', 3) > 0) {
                $paymentStatus = 'partially_paid';
            }

            // 5. Update invoice fields
            $lockedInvoice->update([
                'customer_id' => $newCustomerId,
                'invoice_date' => $data['invoice_date'] ?? $lockedInvoice->invoice_date,
                'payment_type' => $paymentType,
                'payment_method' => $data['payment_method'] ?? $lockedInvoice->payment_method ?? 'cash',
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $invoiceDiscountAmount,
                'shipping_cost' => $customerExpensesTotal,
                'net_total' => $netTotal,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_status' => $paymentStatus,
                'total_cost' => $totalCost,
                'notes' => $data['notes'] ?? $lockedInvoice->notes,
            ]);

            // 6. Delete previous payments and re-create payment voucher(s) if paid
            Payment::where('invoice_id', $lockedInvoice->id)->delete();

            if (! empty($data['payments']) && is_array($data['payments'])) {
                foreach ($data['payments'] as $p) {
                    $pAmount = (string) ($p['amount'] ?? '0.000');
                    if (bccomp($pAmount, '0.000', 3) > 0) {
                        Payment::create([
                            'payment_number' => 'PAY-INV-'.strtoupper(uniqid()),
                            'customer_id' => $newCustomerId,
                            'invoice_id' => $lockedInvoice->id,
                            'user_id' => Auth::id() ?? 1,
                            'amount' => $pAmount,
                            'payment_date' => $lockedInvoice->invoice_date,
                            'payment_method' => $p['method'] ?? 'cash',
                            'notes' => "سداد مجزأ عند تعديل الفاتورة رقم {$lockedInvoice->invoice_number}",
                        ]);
                    }
                }
            } elseif (bccomp($paidAmount, '0.000', 3) > 0) {
                Payment::create([
                    'payment_number' => 'PAY-INV-'.strtoupper(uniqid()),
                    'customer_id' => $newCustomerId,
                    'invoice_id' => $lockedInvoice->id,
                    'user_id' => Auth::id() ?? 1,
                    'amount' => $paidAmount,
                    'payment_date' => $lockedInvoice->invoice_date,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'notes' => "سداد عند تعديل الفاتورة رقم {$lockedInvoice->invoice_number}",
                ]);
            }

            // 7. Recalculate customer balances (both old and new)
            if ($oldCustomerId) {
                $this->customerBalanceService->updateBalance($oldCustomerId);
            }
            if ($newCustomerId && $newCustomerId !== $oldCustomerId) {
                $this->customerBalanceService->updateBalance($newCustomerId);
            }

            // 8. Audit & Activity log
            $this->auditLogService->log(
                action: 'invoice_updated',
                auditable: $lockedInvoice,
                oldValues: null,
                newValues: $lockedInvoice->toArray()
            );

            $this->activityLogService->logSales(
                action: 'updated',
                invoice: $lockedInvoice,
                description: "تم تعديل بيانات وأصناف فاتورة المبيعات رقم [{$lockedInvoice->invoice_number}] وإعادة احتساب الرصيد",
                properties: ['net_total' => (string) $lockedInvoice->net_total, 'paid_amount' => (string) $lockedInvoice->paid_amount]
            );

            return $lockedInvoice;
        });
    }

    /**
     * Permanently delete an invoice with complete stock and financial reversal
     */
    public function deleteInvoice(Invoice $invoice): bool
    {
        if (auth()->check() && ! auth()->user()->hasRole('admin')) {
            throw new Exception('عفواً، لا يملك صلاحية حذف الفواتير سوى المدير العام.');
        }

        return DB::transaction(function () use ($invoice) {
            $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();
            $customerId = $lockedInvoice->customer_id;
            $invoiceNumber = $lockedInvoice->invoice_number;

            // 1. If invoice was confirmed, reverse the inventory back to warehouse
            if ($lockedInvoice->status === 'confirmed') {
                foreach ($lockedInvoice->items as $itemLine) {
                    $item = Item::where('id', $itemLine->item_id)->lockForUpdate()->first();
                    if ($item) {
                        $this->stockService->addStock(
                            item: $item,
                            quantity: $itemLine->quantity,
                            unitCost: $itemLine->cost_price,
                            source: $lockedInvoice,
                            documentNumber: $invoiceNumber,
                            movementType: 'cancellation_in',
                            notes: "إرجاع مخزون بسبب حذف الفاتورة رقم {$invoiceNumber}",
                            storeId: $lockedInvoice->store_id
                        );
                    }
                }
            }

            // 2. Delete any payment vouchers linked directly to this invoice
            Payment::where('invoice_id', $lockedInvoice->id)->delete();

            // 3. Delete Stock movements linked to this invoice
            StockMovement::where('source_type', Invoice::class)
                ->where('source_id', $lockedInvoice->id)
                ->delete();

            // 4. Delete invoice items
            $lockedInvoice->items()->delete();

            // 5. Delete the invoice itself
            $lockedInvoice->delete();

            // 6. Recalculate customer balance
            if ($customerId) {
                $this->customerBalanceService->updateBalance($customerId);
            }

            // 7. Audit & Activity log
            $this->auditLogService->log(
                action: 'invoice_deleted',
                auditable: $lockedInvoice,
                oldValues: ['invoice_number' => $invoiceNumber],
                newValues: null
            );

            $this->activityLogService->log(
                module: 'sales',
                action: 'deleted',
                description: "تم حذف فاتورة المبيعات رقم [{$invoiceNumber}] وعكس أثرها المخزني والمالي",
                properties: ['invoice_number' => $invoiceNumber],
                storeId: $lockedInvoice->store_id
            );

            return true;
        });
    }

    public function generateUniqueNumber(?int $storeId = null): string
    {
        $storeCode = null;
        if ($storeId) {
            $store = Store::find($storeId);
            if ($store && ! empty($store->code)) {
                $storeCode = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($store->code));
            } elseif ($store) {
                $storeCode = 'B'.str_pad((string) $store->id, 2, '0', STR_PAD_LEFT);
            }
        }

        if (! $storeCode) {
            $mainStore = Store::getMainStore();
            $storeCode = ($mainStore && ! empty($mainStore->code))
                ? preg_replace('/[^A-Za-z0-9]/', '', strtoupper($mainStore->code))
                : 'MAIN';
        }

        $prefix = "INV-{$storeCode}-".date('Ymd');

        $lastInvoice = Invoice::withTrashed()
            ->where('invoice_number', 'LIKE', $prefix.'-%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice->invoice_number);
            $lastSequence = (int) end($parts);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        do {
            $candidate = $prefix.'-'.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
            $exists = Invoice::withTrashed()->where('invoice_number', $candidate)->exists();
            if ($exists) {
                $nextSequence++;
            }
        } while ($exists);

        return $candidate;
    }
}
