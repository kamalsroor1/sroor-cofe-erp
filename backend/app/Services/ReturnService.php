<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\Supplier;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function __construct(
        protected StockService $stockService,
        protected CustomerBalanceService $customerBalanceService,
        protected AuditLogService $auditLogService,
        protected TenantClock $tenantClock,
        protected PurchaseService $purchaseService,
        protected ReturnSourceService $returnSourceService,
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
        return DB::transaction(function () use ($data) {
            $invoiceId = $data['invoice_id'] ?? null;

            $totalAmount = '0.000';
            $returnNumber = $data['return_number'] ?? $this->generateUniqueNumber('RET-SALES');
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

    /**
     * Process purchase return to supplier (deducts stock from warehouse)
     */
    public function createPurchaseReturn(array $data): ReturnDocument
    {
        return DB::transaction(function () use ($data) {
            $purchaseId = $data['purchase_id'] ?? null;

            $totalAmount = '0.000';
            $returnNumber = $data['return_number'] ?? $this->generateUniqueNumber('RET-PURCH');
            $storeId = $data['store_id'] ?? ($purchaseId !== null ? Purchase::query()->whereKey($purchaseId)->value('store_id') : null) ?? Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id;

            // Lock order: document -> party -> lines -> items (same as cancelPurchase).
            $purchase = $purchaseId !== null ? $this->returnSourceService->lockDocument(ReturnSourceService::PURCHASE, (int) $purchaseId, (int) $storeId) : null;
            $supplier = Supplier::where('id', $data['supplier_id'])->lockForUpdate()->firstOrFail();

            // BE-01: against a purchase, the lines, quantities and prices come from the purchase.
            $lines = $purchase !== null
                ? $this->returnSourceService->resolveLines(ReturnSourceService::PURCHASE, $purchase, (int) $supplier->id, $data['items'])
                : $this->manualLines($data['items']);

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

            // Adjust supplier balance: purchases - payments - returns
            $totalPurchases = Purchase::where('supplier_id', $supplier->id)->where('status', 'confirmed')->sum('net_total');
            $totalPayments = Payment::where('supplier_id', $supplier->id)->sum('amount');
            $totalReturns = ReturnDocument::where('supplier_id', $supplier->id)->where('return_type', 'purchase_return')->sum('total_amount');

            $bal = bcsub(bcsub((string) $totalPurchases, (string) $totalPayments, 3), (string) $totalReturns, 3);
            $supplier->current_balance = $bal;
            $supplier->save();

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

    public function generateUniqueNumber(string $prefix): string
    {
        // SETG-2 ext: the date in the number is the business date (tenant clock + cutoff).
        $datePrefix = $prefix.'-'.str_replace('-', '', $this->tenantClock->businessDate());

        $lastReturn = ReturnDocument::withTrashed()
            ->where('return_number', 'LIKE', $datePrefix.'-%')
            ->orderBy('return_number', 'desc')
            ->first();

        if ($lastReturn) {
            $parts = explode('-', $lastReturn->return_number);
            $lastSequence = (int) end($parts);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        do {
            $candidate = $datePrefix.'-'.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
            $exists = ReturnDocument::withTrashed()->where('return_number', $candidate)->exists();
            if ($exists) {
                $nextSequence++;
            }
        } while ($exists);

        return $candidate;
    }
}
