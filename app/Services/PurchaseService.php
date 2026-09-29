<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\Item;
use App\Models\Payment;
use App\Support\WeightedAverageCost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class PurchaseService
{
    public function __construct(
        protected StockService $stockService,
        protected SupplierBalanceService $supplierBalanceService,
        protected AuditLogService $auditLogService,
        protected ?ActivityLogService $activityLogService = null
    ) {
        $this->activityLogService = $this->activityLogService ?: app(ActivityLogService::class);
    }

    /**
     * Create and confirm purchase invoice with landed costs allocation
     */
    public function createPurchase(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $baseSubtotal = '0.000';
            $storeId = $data['store_id'] ?? Auth::user()?->getCurrentStore()?->id ?? \App\Models\Store::getMainStore()?->id;

            $purchase = Purchase::create([
                'purchase_number'           => $data['purchase_number'] ?? $this->generateUniqueNumber(),
                'supplier_id'               => $data['supplier_id'],
                'user_id'                   => Auth::id() ?? 1,
                'store_id'                  => $storeId,
                'purchase_date'             => $data['purchase_date'] ?? now()->toDateString(),
                'status'                    => 'confirmed',
                'payment_status'            => 'unpaid',
                'subtotal'                  => '0.000',
                'discount_amount'           => $data['discount_amount'] ?? '0.000',
                'additional_expenses_total' => '0.000',
                'net_total'                 => '0.000',
                'paid_amount'               => '0.000',
                'remaining_amount'          => '0.000',
                'supplier_invoice_ref'      => $data['supplier_invoice_ref'] ?? null,
                'notes'                     => $data['notes'] ?? null,
            ]);

            $rawExpenses = $data['additional_expenses'] ?? [];
            $additionalExpensesTotal = '0.000';
            $supplierExpensesTotal = '0.000';

            // 1. Calculate overall items quantity and base totals for ratio distribution
            $totalQuantity = '0.000';
            $totalBaseValuation = '0.000';
            $itemsCount = count($data['items']);

            foreach ($data['items'] as $line) {
                $qty = (string)($line['quantity'] ?? '0.000');
                $baseCost = (string)($line['cost_price'] ?? '0.000');

                // A zero-cost purchase line seeds a zero cost into the weighted average: reject it.
                if (bccomp($baseCost, '0.000', 3) <= 0) {
                    $lineItemName = Item::whereKey($line['item_id'] ?? null)->value('name') ?? '';
                    throw new Exception("لا يمكن حفظ فاتورة الشراء: سعر تكلفة الصنف [{$lineItemName}] يجب أن يكون أكبر من صفر.");
                }

                $totalQuantity = bcadd($totalQuantity, $qty, 3);
                $totalBaseValuation = bcadd($totalBaseValuation, bcmul($qty, $baseCost, 3), 3);
            }

            // Invoice-level discount is allocated to lines by value so it lowers the landed unit cost.
            $purchaseDiscount = (string)($data['discount_amount'] ?? '0.000');
            if ($purchaseDiscount === '' || !is_numeric($purchaseDiscount)) {
                $purchaseDiscount = '0.000';
            }
            if (bccomp($purchaseDiscount, '0.000', 3) < 0) {
                throw new Exception('لا يمكن أن يكون خصم فاتورة الشراء بقيمة سالبة.');
            }
            if (bccomp($purchaseDiscount, '0.000', 3) > 0 && bccomp($purchaseDiscount, $totalBaseValuation, 3) >= 0) {
                throw new Exception('خصم فاتورة الشراء يجب أن يكون أقل من إجمالي قيمة الأصناف، وإلا ستصبح تكلفة البضاعة صفراً.');
            }

            // 2. Process each item line and allocate landed expenses
            foreach ($data['items'] as $line) {
                $item = Item::where('id', $line['item_id'])->lockForUpdate()->firstOrFail();

                $quantity = (string)$line['quantity'];
                $baseCostPrice = (string)$line['cost_price'];
                $lineBaseTotal = bcmul($quantity, $baseCostPrice, 3);
                $baseSubtotal = bcadd($baseSubtotal, $lineBaseTotal, 3);

                // Allocate expenses to this line
                $lineAllocatedExpense = '0.000';
                foreach ($rawExpenses as $exp) {
                    $expAmount = (string)($exp['amount'] ?? '0.000');
                    if (bccomp($expAmount, '0.000', 3) <= 0) {
                        continue;
                    }

                    $method = $exp['allocation_method'] ?? 'by_quantity';
                    $allocated = '0.000';

                    if ($method === 'by_quantity' && bccomp($totalQuantity, '0.000', 3) > 0) {
                        // Ratio = line.qty / totalQuantity
                        $ratio = bcdiv($quantity, $totalQuantity, 6);
                        $allocated = bcmul($expAmount, $ratio, 3);
                    } elseif ($method === 'by_value' && bccomp($totalBaseValuation, '0.000', 3) > 0) {
                        // Ratio = line.baseTotal / totalBaseValuation
                        $ratio = bcdiv($lineBaseTotal, $totalBaseValuation, 6);
                        $allocated = bcmul($expAmount, $ratio, 3);
                    } elseif ($method === 'equal' && $itemsCount > 0) {
                        $allocated = bcdiv($expAmount, (string)$itemsCount, 3);
                    }

                    $lineAllocatedExpense = bcadd($lineAllocatedExpense, $allocated, 3);
                }

                // Allocate the invoice-level discount by line value
                $lineAllocatedDiscount = '0.000';
                if (bccomp($purchaseDiscount, '0.000', 3) > 0 && bccomp($totalBaseValuation, '0.000', 3) > 0) {
                    $lineAllocatedDiscount = bcdiv(bcmul($purchaseDiscount, $lineBaseTotal, 6), $totalBaseValuation, 3);
                }

                // Landed Unit Cost = Base Cost + (Allocated Expense - Allocated Discount) / Quantity
                $unitAllocatedExpense = bccomp($quantity, '0.000', 3) > 0
                    ? bcdiv($lineAllocatedExpense, $quantity, 3)
                    : '0.000';
                $unitAllocatedDiscount = bccomp($quantity, '0.000', 3) > 0
                    ? bcdiv($lineAllocatedDiscount, $quantity, 3)
                    : '0.000';
                $landedUnitCost = bcsub(bcadd($baseCostPrice, $unitAllocatedExpense, 3), $unitAllocatedDiscount, 3);
                if (bccomp($landedUnitCost, '0.000', 3) <= 0) {
                    throw new Exception("لا يمكن حفظ فاتورة الشراء: التكلفة الصافية للصنف [{$item->name}] بعد الخصم أصبحت صفراً أو أقل.");
                }

                // Create PurchaseItem
                $purchase->items()->create([
                    'item_id'           => $item->id,
                    'quantity'          => $quantity,
                    'base_cost_price'   => $baseCostPrice,
                    'allocated_expense' => $lineAllocatedExpense,
                    'cost_price'        => $landedUnitCost, // Landed unit cost
                    'total_price'       => $lineBaseTotal,
                ]);

                // Calculate weighted average cost with Landed Unit Cost
                $newWac = $this->calculateWeightedAverageCost(
                    currentStock: (string)$item->current_stock,
                    currentWac: $item->effectiveCost(),
                    newQuantity: $quantity,
                    newCost: $landedUnitCost
                );

                $item->cost_price = $landedUnitCost;
                $item->weighted_avg_cost = $newWac;
                $item->save();

                // Add to inventory with Landed Cost
                $this->stockService->addStock(
                    item: $item,
                    quantity: $quantity,
                    unitCost: $landedUnitCost,
                    source: $purchase,
                    documentNumber: $purchase->purchase_number,
                    movementType: 'purchase_in',
                    notes: "توريد بضاعة بفاتورة شراء رقم {$purchase->purchase_number}"
                        . (bccomp($unitAllocatedExpense, '0.000', 3) > 0 ? " (شامل مصاريف محملة +{$unitAllocatedExpense} ج.م/وحدة)" : '')
                        . (bccomp($unitAllocatedDiscount, '0.000', 3) > 0 ? " (بعد خصم موزع -{$unitAllocatedDiscount} ج.م/وحدة)" : ''),
                    storeId: $storeId
                );
            }

            // 3. Process and Save Additional Expenses & Generate Treasury Vouchers
            foreach ($rawExpenses as $exp) {
                $expAmount = (string)($exp['amount'] ?? '0.000');
                if (bccomp($expAmount, '0.000', 3) <= 0) {
                    continue;
                }

                $title = trim($exp['title'] ?? 'مصاريف إضافية');
                $method = $exp['allocation_method'] ?? 'by_quantity';
                $paidBy = $exp['paid_by'] ?? 'supplier_account';
                $expNotes = $exp['notes'] ?? null;

                $additionalExpensesTotal = bcadd($additionalExpensesTotal, $expAmount, 3);

                $expenseRecord = $purchase->additionalExpenses()->create([
                    'title'             => $title,
                    'amount'            => $expAmount,
                    'allocation_method' => $method,
                    'paid_by'           => $paidBy,
                    'notes'             => $expNotes,
                ]);

                if ($paidBy === 'supplier_account') {
                    // Charged to supplier balance
                    $supplierExpensesTotal = bcadd($supplierExpensesTotal, $expAmount, 3);
                } else {
                    // Paid from treasury (Cash, Instapay, E-wallet)
                    $paymentMethod = str_replace('treasury_', '', $paidBy);
                    $payment = Payment::create([
                        'payment_number' => 'PAY-EXP-' . strtoupper(uniqid()),
                        'supplier_id'    => $purchase->supplier_id,
                        'purchase_id'    => $purchase->id,
                        'user_id'        => Auth::id() ?? 1,
                        'amount'         => $expAmount,
                        'payment_date'   => $purchase->purchase_date,
                        'payment_method' => $paymentMethod,
                        'notes'          => "سداد مصروف ملحق [{$title}] لفاتورة مشتريات [{$purchase->purchase_number}]",
                    ]);
                    $expenseRecord->update(['payment_id' => $payment->id]);
                }
            }

            // 4. Calculate Net Total
            $discountAmount = $purchaseDiscount;
            $netTotal = bcsub($baseSubtotal, $discountAmount, 3);
            if (bccomp($supplierExpensesTotal, '0.000', 3) > 0) {
                $netTotal = bcadd($netTotal, $supplierExpensesTotal, 3);
            }

            $paidAmount = (string)($data['paid_amount'] ?? '0.000');
            $remainingAmount = bcsub($netTotal, $paidAmount, 3);

            $paymentStatus = 'unpaid';
            if (bccomp($paidAmount, '0.000', 3) > 0) {
                $paymentStatus = bccomp($paidAmount, $netTotal, 3) >= 0 ? 'paid' : 'partially_paid';
            }

            $purchase->update([
                'subtotal'                  => $baseSubtotal,
                'discount_amount'           => $discountAmount,
                'additional_expenses_total' => $additionalExpensesTotal,
                'net_total'                 => $netTotal,
                'paid_amount'               => $paidAmount,
                'remaining_amount'          => $remainingAmount,
                'payment_status'            => $paymentStatus,
            ]);

            // 5. Record direct supplier payment voucher if paid amount exists
            if (bccomp($paidAmount, '0.000', 3) > 0) {
                Payment::create([
                    'payment_number' => 'PAY-PUR-' . strtoupper(uniqid()),
                    'supplier_id'    => $purchase->supplier_id,
                    'purchase_id'    => $purchase->id,
                    'user_id'        => Auth::id() ?? 1,
                    'amount'         => $paidAmount,
                    'payment_date'   => $purchase->purchase_date,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'notes'          => "سداد دفعة توريد للفاتورة رقم {$purchase->purchase_number}",
                ]);
            }

            // 6. Update supplier balance
            $this->supplierBalanceService->updateBalance($purchase->supplier_id);

            $this->auditLogService->log(
                action: 'purchase_confirmed',
                auditable: $purchase,
                oldValues: null,
                newValues: $purchase->toArray()
            );

            $this->activityLogService->logPurchase(
                action: 'created',
                purchase: $purchase,
                description: "تم إنشاء فاتورة مشتريات وتوريد بضاعة رقم [{$purchase->purchase_number}] من المورد ({$purchase->supplier?->name}) بإجمالي " . number_format((float)$purchase->net_total, 2) . " ج.م" . (bccomp($additionalExpensesTotal, '0.000', 3) > 0 ? " (شامل مصاريف ملحقة " . number_format((float)$additionalExpensesTotal, 2) . " ج.م)" : '')
            );

            return $purchase;
        });
    }

    /**
     * Cancel a purchase invoice and securely reverse stock and supplier balances
     */
    public function cancelPurchase(Purchase $purchase, ?string $reason = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason) {
            $lockedPurchase = Purchase::where('id', $purchase->id)->lockForUpdate()->firstOrFail();

            if ($lockedPurchase->status === 'cancelled') {
                throw new Exception("فاتورة المشتريات رقم [{$lockedPurchase->purchase_number}] ملغاة بالفعل مسبقاً.");
            }

            $storeId = $lockedPurchase->store_id;

            // 1. Verify stock sufficiency for every item before reversing to prevent negative stock
            foreach ($lockedPurchase->items as $itemLine) {
                $item = Item::where('id', $itemLine->item_id)->lockForUpdate()->firstOrFail();
                $qty = (string)$itemLine->quantity;

                // Check master stock
                if (bccomp((string)$item->current_stock, $qty, 3) < 0) {
                    throw new Exception("تعذر إلغاء الفاتورة: رصيد الصنف [{$item->name}] الحالي ({$item->current_stock} {$item->unit}) أقل من الكمية المطلوب عكسها ({$qty} {$item->unit})، لوجود مبيعات تمت من هذه الشحنة.");
                }

                // Check store stock if assigned to a specific store
                if ($storeId) {
                    $storeStock = \App\Models\StoreStock::where('store_id', $storeId)
                        ->where('item_id', $item->id)
                        ->lockForUpdate()
                        ->first();

                    if ($storeStock && bccomp((string)$storeStock->quantity, $qty, 3) < 0) {
                        $storeName = $lockedPurchase->store?->name ?? "الفرع #{$storeId}";
                        throw new Exception("تعذر إلغاء الفاتورة: رصيد الصنف [{$item->name}] في [{$storeName}] ({$storeStock->quantity} {$item->unit}) غير كافٍ لخصم الكمية ({$qty} {$item->unit}).");
                    }
                }
            }

            // 2. Reverse stock and take the cancelled quantity back out of the weighted average cost
            foreach ($lockedPurchase->items as $itemLine) {
                $item = Item::where('id', $itemLine->item_id)->lockForUpdate()->firstOrFail();
                $qty = (string)$itemLine->quantity;
                $lineCost = (string)$itemLine->cost_price;

                $item->weighted_avg_cost = WeightedAverageCost::remove(
                    stock: (string)$item->current_stock,
                    wac: $item->effectiveCost(),
                    qty: $qty,
                    unitCost: $lineCost
                );
                $item->save();

                $this->stockService->deductStock(
                    item: $item,
                    quantity: $qty,
                    source: $lockedPurchase,
                    documentNumber: $lockedPurchase->purchase_number,
                    movementType: 'purchase_cancel_out',
                    notes: "إلغاء فاتورة مشتريات وتوريد رقم {$lockedPurchase->purchase_number}" . ($reason ? " - سبب: {$reason}" : ''),
                    storeId: $storeId,
                    unitCost: $lineCost
                );
            }

            $oldState = $lockedPurchase->toArray();

            // 3. Cancel associated payment vouchers
            Payment::where('purchase_id', $lockedPurchase->id)->delete();

            // 4. Update purchase status
            $cancelNote = "تم إلغاء الفاتورة وعكس المخزون" . ($reason ? " (السبب: {$reason})" : "");
            $lockedPurchase->update([
                'status'           => 'cancelled',
                'remaining_amount' => '0.000',
                'notes'            => $lockedPurchase->notes ? ($lockedPurchase->notes . "\n" . $cancelNote) : $cancelNote,
            ]);

            // 5. Recalculate supplier balance
            $this->supplierBalanceService->updateBalance($lockedPurchase->supplier_id);

            // 6. Audit and Activity Logging
            $this->auditLogService->log(
                action: 'purchase_cancelled',
                auditable: $lockedPurchase,
                oldValues: $oldState,
                newValues: $lockedPurchase->toArray()
            );

            $this->activityLogService->logPurchase(
                action: 'cancelled',
                purchase: $lockedPurchase,
                description: "تم إلغاء فاتورة المشتريات رقم [{$lockedPurchase->purchase_number}] وعكس الكميات من المخزون" . ($reason ? " (السبب: {$reason})" : '')
            );

            return $lockedPurchase;
        });
    }

    /**
     * Restore a cancelled purchase invoice and return inventory back to warehouse
     */
    public function restorePurchase(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            $lockedPurchase = Purchase::where('id', $purchase->id)->lockForUpdate()->firstOrFail();

            if ($lockedPurchase->status !== 'cancelled') {
                throw new Exception("فاتورة المشتريات رقم [{$lockedPurchase->purchase_number}] ليست ملغاة.");
            }

            $storeId = $lockedPurchase->store_id;

            // 1. Re-add stock for each line item and recalculate WAC
            foreach ($lockedPurchase->items as $itemLine) {
                $item = Item::where('id', $itemLine->item_id)->lockForUpdate()->firstOrFail();
                $qty = (string)$itemLine->quantity;
                $cost = (string)$itemLine->cost_price;

                $newWac = $this->calculateWeightedAverageCost(
                    currentStock: (string)$item->current_stock,
                    currentWac: $item->effectiveCost(),
                    newQuantity: $qty,
                    newCost: $cost
                );

                $item->cost_price = $cost;
                $item->weighted_avg_cost = $newWac;
                $item->save();

                $this->stockService->addStock(
                    item: $item,
                    quantity: $qty,
                    unitCost: $cost,
                    source: $lockedPurchase,
                    documentNumber: $lockedPurchase->purchase_number,
                    movementType: 'purchase_restore_in',
                    notes: "استعادة فاتورة مشتريات ملغاة رقم {$lockedPurchase->purchase_number}",
                    storeId: $storeId
                );
            }

            // 2. Restore or re-create payment vouchers if there was a paid amount
            if (bccomp((string)$lockedPurchase->paid_amount, '0.000', 3) > 0) {
                Payment::withTrashed()
                    ->where('purchase_id', $lockedPurchase->id)
                    ->restore();

                // If no trashed payment existed, create a new one
                $hasPayment = Payment::where('purchase_id', $lockedPurchase->id)->exists();
                if (!$hasPayment) {
                    Payment::create([
                        'payment_number' => 'PAY-PUR-' . strtoupper(uniqid()),
                        'supplier_id'    => $lockedPurchase->supplier_id,
                        'purchase_id'    => $lockedPurchase->id,
                        'user_id'        => Auth::id() ?? 1,
                        'amount'         => $lockedPurchase->paid_amount,
                        'payment_date'   => $lockedPurchase->purchase_date,
                        'payment_method' => 'cash',
                        'notes'          => "سداد دفعة توريد عند استعادة الفاتورة رقم {$lockedPurchase->purchase_number}",
                    ]);
                }
            }

            // 3. Recompute remaining amount and update status
            $remaining = bcsub((string)$lockedPurchase->net_total, (string)$lockedPurchase->paid_amount, 3);
            $lockedPurchase->update([
                'status'           => 'confirmed',
                'remaining_amount' => $remaining,
            ]);

            // 4. Recalculate supplier balance
            $this->supplierBalanceService->updateBalance($lockedPurchase->supplier_id);

            // 5. Audit & Activity log
            $this->auditLogService->log(
                action: 'purchase_restored',
                auditable: $lockedPurchase,
                oldValues: null,
                newValues: $lockedPurchase->toArray()
            );

            $this->activityLogService->logPurchase(
                action: 'restored',
                purchase: $lockedPurchase,
                description: "تم استعادة وتأكيد فاتورة المشتريات رقم [{$lockedPurchase->purchase_number}] وإعادة إيداع بضاعتها بالمخزون"
            );

            return $lockedPurchase;
        });
    }

    /**
     * Permanently delete / cancel purchase invoice with full inventory reversal
     */
    public function deletePurchase(Purchase $purchase): bool
    {
        return DB::transaction(function () use ($purchase) {
            $lockedPurchase = Purchase::where('id', $purchase->id)->lockForUpdate()->firstOrFail();

            if ($lockedPurchase->status === 'confirmed') {
                $this->cancelPurchase($lockedPurchase, 'حذف الفاتورة من النظام');
            }

            // Stock movements are kept: the purchase_in / purchase_cancel_out pair is the audit trail.

            // Soft delete purchase items and purchase
            $lockedPurchase->items()->delete();
            $lockedPurchase->delete();

            $this->supplierBalanceService->updateBalance($lockedPurchase->supplier_id);

            return true;
        });
    }

    /**
     * Calculate Weighted Average Cost (WAC)
     */
    public function calculateWeightedAverageCost(
        string $currentStock,
        string $currentWac,
        string $newQuantity,
        string $newCost
    ): string {
        return WeightedAverageCost::add(
            $currentStock,
            $currentWac !== '' ? $currentWac : '0.000',
            $newQuantity,
            $newCost
        );
    }

    public function generateUniqueNumber(): string
    {
        $prefix = 'PUR-' . date('Ymd');
        
        $lastPurchase = Purchase::withTrashed()
            ->where('purchase_number', 'LIKE', $prefix . '-%')
            ->orderBy('purchase_number', 'desc')
            ->first();

        if ($lastPurchase) {
            $parts = explode('-', $lastPurchase->purchase_number);
            $lastSequence = (int) end($parts);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        do {
            $candidate = $prefix . '-' . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
            $exists = Purchase::withTrashed()->where('purchase_number', $candidate)->exists();
            if ($exists) {
                $nextSequence++;
            }
        } while ($exists);

        return $candidate;
    }
}
