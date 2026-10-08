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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function __construct(
        protected StockService $stockService,
        protected CustomerBalanceService $customerBalanceService,
        protected AuditLogService $auditLogService
    ) {}

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
            $customer = Customer::where('id', $data['customer_id'])->lockForUpdate()->firstOrFail();
            $invoiceId = $data['invoice_id'] ?? null;
            $invoice = $invoiceId ? Invoice::where('id', $invoiceId)->first() : null;

            $totalAmount = '0.000';
            $returnNumber = $data['return_number'] ?? $this->generateUniqueNumber('RET-SALES');
            $storeId = $data['store_id'] ?? ($invoice?->store_id ?? Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id);

            $returnDoc = ReturnDocument::create([
                'return_number' => $returnNumber,
                'return_type' => 'sales_return',
                'invoice_id' => $invoiceId,
                'purchase_id' => null,
                'customer_id' => $customer->id,
                'supplier_id' => null,
                'user_id' => Auth::id() ?? 1,
                'store_id' => $storeId,
                'total_amount' => '0.000',
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? 'مرتجع مبيعات',
            ]);

            foreach ($data['items'] as $line) {
                $item = Item::where('id', $line['item_id'])->lockForUpdate()->firstOrFail();
                $qty = (string) $line['quantity'];
                $unitPrice = (string) $line['unit_price'];
                $lineTotal = bcmul($qty, $unitPrice, 3);

                $returnDoc->items()->create([
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
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
                    storeId: $storeId
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
                newValues: $returnDoc->toArray()
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
            $supplier = Supplier::where('id', $data['supplier_id'])->lockForUpdate()->firstOrFail();
            $purchaseId = $data['purchase_id'] ?? null;

            $totalAmount = '0.000';
            $returnNumber = $data['return_number'] ?? $this->generateUniqueNumber('RET-PURCH');
            $storeId = $data['store_id'] ?? ($purchase?->store_id ?? Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id);

            $returnDoc = ReturnDocument::create([
                'return_number' => $returnNumber,
                'return_type' => 'purchase_return',
                'invoice_id' => null,
                'purchase_id' => $purchaseId,
                'customer_id' => null,
                'supplier_id' => $supplier->id,
                'user_id' => Auth::id() ?? 1,
                'store_id' => $storeId,
                'total_amount' => '0.000',
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? 'مرتجع مشتريات للمورد',
            ]);

            foreach ($data['items'] as $line) {
                $item = Item::where('id', $line['item_id'])->lockForUpdate()->firstOrFail();
                $qty = (string) $line['quantity'];
                $unitPrice = (string) ($line['unit_price'] ?? $item->cost_price);
                $lineTotal = bcmul($qty, $unitPrice, 3);

                $returnDoc->items()->create([
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                ]);

                // Deduct stock sent back to supplier
                $this->stockService->deductStock(
                    item: $item,
                    quantity: $qty,
                    source: $returnDoc,
                    documentNumber: $returnDoc->return_number,
                    movementType: 'purchase_return_out',
                    notes: "مرتجع مشتريات للمورد {$supplier->name} بمستند رقم {$returnDoc->return_number}",
                    storeId: $storeId
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
                newValues: $returnDoc->toArray()
            );

            return $returnDoc;
        });
    }

    public function generateUniqueNumber(string $prefix): string
    {
        $datePrefix = $prefix.'-'.date('Ymd');

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
            $candidate = $datePrefix.'-'.str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
            $exists = ReturnDocument::withTrashed()->where('return_number', $candidate)->exists();
            if ($exists) {
                $nextSequence++;
            }
        } while ($exists);

        return $candidate;
    }
}
