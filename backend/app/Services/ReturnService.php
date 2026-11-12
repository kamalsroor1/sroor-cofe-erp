<?php

namespace App\Services;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\Supplier;
use App\Support\Database\TransactionRetry;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Auth;

class ReturnService
{
    public function __construct(
        protected StockService $stockService,
        protected CustomerBalanceService $customerBalanceService,
        protected AuditLogService $auditLogService,
        protected TenantClock $tenantClock,
        protected PurchaseService $purchaseService,
        protected ReturnSourceService $returnSourceService,
        protected DocumentNumberService $documentNumbers,
        protected ResolveWalkInCustomerAction $walkInCustomer,
        protected SupplierBalanceService $supplierBalanceService,
    ) {}

    /**
     * The user a document is attributed to: the authenticated user, else the explicit
     * `user_id` a job/command passed in $data, else NULL ("system"). Never a made-up id.
     *
     * @param  array<string, mixed>  $data
     */
    private function actorId(array $data = []): ?int
    {
        $authId = Auth::id();
        if ($authId !== null) {
            return (int) $authId;
        }

        return isset($data['user_id']) && is_numeric($data['user_id']) ? (int) $data['user_id'] : null;
    }

    /**
     * Dispatch and create return document based on return_type
     */
    public function createReturn(array $data): ReturnDocument
    {
        if (($data['return_type'] ?? '') === 'purchase_return') {
            return $this->createPurchaseReturn($data);
        }

        return $this->createSalesReturn($data);
    }

    /**
     * Process sales return from customer (with stock return to inventory)
     */
    public function createSalesReturn(array $data): ReturnDocument
    {
        // BUG-04: the day's counter rows exist before the transaction takes its first read.
        if (empty($data['return_number'])) {
            $this->documentNumbers->prepareFor($this->returnNumberPrefix('RET-SALES'), ReturnDocument::class, 'return_number');
        }
        $this->documentNumbers->prepareDated('PAY-RET', Payment::class, 'payment_number');

        // BUG-07: restarted as a whole on a lock conflict (1213/1205/1020); only plain input is
        // captured, every row is read again inside and a failed attempt's numbers roll back.
        return TransactionRetry::run(function () use ($data) {
            $invoiceId = $data['invoice_id'] ?? null;

            $totalAmount = '0.000';
            $storeId = $data['store_id'] ?? ($invoiceId ? Invoice::query()->whereKey($invoiceId)->value('store_id') : null) ?? Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id;

            // Lock order: document -> party -> lines -> items (same as cancelInvoice).
            $invoice = $invoiceId ? $this->returnSourceService->lockDocument(ReturnSourceService::SALES, (int) $invoiceId, (int) $storeId) : null;
            $customer = Customer::where('id', $data['customer_id'])->lockForUpdate()->firstOrFail();

            // BE-01: against an invoice, the lines, quantities and prices come from the invoice.
            // Without one, only the item, quantity and price are taken from the request: a
            // client can never inject a source-line link.
            $lines = $invoice !== null
                ? $this->returnSourceService->resolveLines(ReturnSourceService::SALES, $invoice, (int) $customer->id, $data['items'])
                : $this->manualLines($data['items']);

            // BUG-07 lock order: document -> party -> items / store_stocks (sorted) -> numbers.
            $this->stockService->lockStockRows(array_column($lines, 'item_id'), [$storeId]);

            // BE-09: allocate the number after the document and party locks.
            $returnNumber = $data['return_number'] ?? $this->generateUniqueNumber('RET-SALES');

            // BE-10: how the customer is paid back. The anonymous walk-in customer has no account
            // to credit, so that return is always handed back in cash.
            $refundMethod = $this->walkInCustomer->isWalkIn($customer)
                ? ReturnDocument::REFUND_CASH
                : $this->resolveRefundMethod($data['refund_method'] ?? null);

            $returnDoc = ReturnDocument::create([
                'return_number' => $returnNumber,
                'return_type' => 'sales_return',
                'invoice_id' => $invoiceId,
                'purchase_id' => null,
                'customer_id' => $customer->id,
                'supplier_id' => null,
                'user_id' => $this->actorId($data),
                'store_id' => $storeId,
                'total_amount' => '0.000',
                'return_date' => $data['return_date'] ?? $this->tenantClock->businessDate(),
                'reason' => $data['reason'] ?? 'مرتجع مبيعات',
                'refund_method' => $refundMethod,
            ]);

            foreach ($lines as $line) {
                $item = Item::where('id', $line['item_id'])->lockForUpdate()->firstOrFail();
                $qty = (string) $line['quantity'];
                $unitPrice = (string) $line['unit_price'];
                // SETG-13: half-up at scale 3 exactly like InvoiceService. A line taken from an
                // invoice carries its own prorated refund (the sum of a line's refunds is exact).
                $lineTotal = (string) ($line['total_price'] ?? Decimal::mul($qty, $unitPrice));

                $returnDoc->items()->create([
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                    'invoice_item_id' => $line['invoice_item_id'] ?? null,
                ]);

                // Return stock to warehouse/store
                $this->stockService->addStock(
                    item: $item,
                    quantity: $qty,
                    unitCost: $item->cost_price,
                    source: $returnDoc,
                    documentNumber: $returnDoc->return_number,
                    movementType: 'sales_return_in',
                    notes: "مرتجع مبيعات للعميل {$customer->name} بمستند رقم {$returnDoc->return_number}",
                    storeId: $storeId,
                    actorId: $this->actorId($data),
                );

                $totalAmount = bcadd($totalAmount, $lineTotal, 3);
            }

            $returnDoc->update(['total_amount' => $totalAmount]);

            // BE-10: cash leaves the drawer through its own (negative) voucher; the amount is the
            // return's total, never a client figure. A 'balance' refund books nothing but the
            // customer balance below.
            if ($refundMethod === ReturnDocument::REFUND_CASH && bccomp($totalAmount, '0.000', 3) > 0) {
                if ($invoice !== null) {
                    $this->assertCashRefundWithinPaid((int) $invoice->id, $totalAmount);
                }
                $this->bookCashRefund($returnDoc, $customer, $totalAmount, $this->actorId($data));
            }

            // Update customer balance (reduces debt)
            $this->customerBalanceService->updateBalance($customer->id);

            $this->auditLogService->log(
                action: 'sales_return_created',
                auditable: $returnDoc,
                oldValues: null,
                newValues: $returnDoc->toArray(),
                actorId: $this->actorId($data),
            );

            return $returnDoc;
        });
    }

    private function resolveRefundMethod(mixed $requested): string
    {
        return $requested === ReturnDocument::REFUND_CASH ? ReturnDocument::REFUND_CASH : ReturnDocument::REFUND_BALANCE;
    }

    /**
     * BE-10 fraud guard: cash given back against an invoice can never exceed the money the
     * customer actually paid on it, net of the cash already refunded on it. Runs under the
     * invoice lock taken at the start of the return, so two refunds cannot both pass.
     */
    private function assertCashRefundWithinPaid(int $invoiceId, string $required): void
    {
        $available = $this->returnSourceService->cashRefundable([$invoiceId])[$invoiceId];

        if (bccomp($required, $available, 3) > 0) {
            throw BusinessRuleException::make('returns.cash_refund_exceeds_paid', [
                'available' => bccomp($available, '0.000', 3) < 0 ? '0.000' : $available,
                'required' => $required,
            ]);
        }
    }

    /**
     * BE-10: the cash voucher of a cash refund. A NEGATIVE cash payment of the return's store:
     * the shift, the daily journal and the treasury see the money leave, and the customer
     * balance (invoices - payments - returns) stays net-zero for it (return -X, voucher +X).
     * Called inside the return's transaction after the document/party locks, so the payment
     * number is allocated after the return number.
     */
    private function bookCashRefund(ReturnDocument $returnDoc, Customer $customer, string $amount, ?int $actorId): Payment
    {
        return Payment::create([
            'payment_number' => $this->documentNumbers->nextDated('PAY-RET', 6, Payment::class, 'payment_number'),
            'customer_id' => $customer->id,
            'supplier_id' => null,
            'invoice_id' => $returnDoc->invoice_id,
            'purchase_id' => null,
            'store_id' => $returnDoc->store_id,
            'return_id' => $returnDoc->id,
            'user_id' => $actorId,
            'amount' => bcsub('0.000', $amount, 3),
            // The cash leaves the drawer today, even for a back-dated return.
            'payment_date' => $this->tenantClock->businessDate(),
            'payment_method' => 'cash',
            'notes' => __('returns.refund_voucher_note', ['number' => $returnDoc->return_number]),
        ]);
    }

    /**
     * Process purchase return to supplier (deducts stock from warehouse)
     */
    public function createPurchaseReturn(array $data): ReturnDocument
    {
        // BUG-04: the day's counter row exists before the transaction takes its first read.
        if (empty($data['return_number'])) {
            $this->documentNumbers->prepareFor($this->returnNumberPrefix('RET-PURCH'), ReturnDocument::class, 'return_number');
        }

        // BUG-07: restarted as a whole on a lock conflict (1213/1205/1020), see createSalesReturn().
        return TransactionRetry::run(function () use ($data) {
            $purchaseId = $data['purchase_id'] ?? null;

            $totalAmount = '0.000';
            $storeId = $data['store_id'] ?? ($purchaseId !== null ? Purchase::query()->whereKey($purchaseId)->value('store_id') : null) ?? Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id;

            // Lock order: document -> party -> lines -> items (same as cancelPurchase).
            $purchase = $purchaseId !== null ? $this->returnSourceService->lockDocument(ReturnSourceService::PURCHASE, (int) $purchaseId, (int) $storeId) : null;
            $supplier = Supplier::where('id', $data['supplier_id'])->lockForUpdate()->firstOrFail();

            // BE-01: against a purchase, the lines, quantities and prices come from the purchase.
            $lines = $purchase !== null
                ? $this->returnSourceService->resolveLines(ReturnSourceService::PURCHASE, $purchase, (int) $supplier->id, $data['items'])
                : $this->manualLines($data['items']);

            // BUG-07 lock order: document -> party -> items / store_stocks (sorted) -> numbers.
            $this->stockService->lockStockRows(array_column($lines, 'item_id'), [$storeId]);

            // BE-09: allocate the number after the document and party locks.
            $returnNumber = $data['return_number'] ?? $this->generateUniqueNumber('RET-PURCH');

            $returnDoc = ReturnDocument::create([
                'return_number' => $returnNumber,
                'return_type' => 'purchase_return',
                'invoice_id' => null,
                'purchase_id' => $purchaseId,
                'customer_id' => null,
                'supplier_id' => $supplier->id,
                'user_id' => $this->actorId($data),
                'store_id' => $storeId,
                'total_amount' => '0.000',
                'return_date' => $data['return_date'] ?? $this->tenantClock->businessDate(),
                'reason' => $data['reason'] ?? 'مرتجع مشتريات للمورد',
            ]);

            foreach ($lines as $line) {
                $item = Item::where('id', $line['item_id'])->lockForUpdate()->firstOrFail();
                $qty = (string) $line['quantity'];
                $unitPrice = (string) ($line['unit_price'] ?? $item->cost_price);
                // CTO: half-up everywhere, purchase returns included (App\Support\Money\Decimal).
                $lineTotal = (string) ($line['total_price'] ?? Decimal::mul($qty, $unitPrice));

                $returnDoc->items()->create([
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                    'purchase_item_id' => $line['purchase_item_id'] ?? null,
                    // BE-02: lets deleting the return put the average back exactly.
                    'wac_before' => $this->purchaseService->effectiveWeightedAverageCost($item),
                ]);

                // BUG-03: goods leave at the refunded price, so take that valuation out of the
                // moving average. With no stock or value left the current average is kept.
                $reversedWac = $this->purchaseService->reverseWeightedAverageCost(
                    currentStock: (string) $item->current_stock,
                    currentWac: $this->purchaseService->effectiveWeightedAverageCost($item),
                    removedQuantity: $qty,
                    removedValuation: bcmul($qty, $unitPrice, 6),
                );
                if ($reversedWac !== null) {
                    $item->weighted_avg_cost = $reversedWac;
                    $item->save();
                }

                // Deduct stock sent back to supplier
                $this->stockService->deductStock(
                    item: $item,
                    quantity: $qty,
                    source: $returnDoc,
                    documentNumber: $returnDoc->return_number,
                    movementType: 'purchase_return_out',
                    notes: "مرتجع مشتريات للمورد {$supplier->name} بمستند رقم {$returnDoc->return_number}",
                    storeId: $storeId,
                    actorId: $this->actorId($data),
                );

                $totalAmount = bcadd($totalAmount, $lineTotal, 3);
            }

            $returnDoc->update(['total_amount' => $totalAmount]);

            // BUG-07 review: the one supplier balance formula (opening balance + purchases - payments
            // - returns). The inline copy that was here left out the imported opening balance, so a
            // purchase return wiped it from current_balance until the next recompute.
            $this->supplierBalanceService->updateBalance((int) $supplier->id);

            $this->auditLogService->log(
                action: 'purchase_return_created',
                auditable: $returnDoc,
                oldValues: null,
                newValues: $returnDoc->toArray(),
                actorId: $this->actorId($data),
            );

            return $returnDoc;
        });
    }

    /**
     * Lines of a return with no source document: only item, quantity and price from the
     * request (a link to an invoice/purchase line is set from a resolved source, never here).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function manualLines(array $items): array
    {
        return array_values(array_map(static fn (array $line): array => [
            'item_id' => $line['item_id'],
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'] ?? null,
        ], $items));
    }

    /**
     * Allocates the next number from the locked sequence: it CONSUMES a number (no previews).
     * Call it inside the transaction that inserts the document, after the party/document locks.
     */
    public function generateUniqueNumber(string $prefix): string
    {
        return $this->documentNumbers->nextFor($this->returnNumberPrefix($prefix), 4, ReturnDocument::class, 'return_number');
    }

    private function returnNumberPrefix(string $prefix): string
    {
        // SETG-2 ext: the date in the number is the business date (tenant clock + cutoff).
        return $prefix.'-'.str_replace('-', '', $this->tenantClock->businessDate());
    }
}
