<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        protected StockService $stockService,
        protected SupplierBalanceService $supplierBalanceService,
        protected AuditLogService $auditLogService,
        protected ?ActivityLogService $activityLogService = null,
        protected ?TenantClock $tenantClock = null,
    ) {
        $this->activityLogService = $this->activityLogService ?: app(ActivityLogService::class);
        $this->tenantClock = $this->tenantClock ?: app(TenantClock::class);
    }

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
     * Create and confirm purchase invoice with landed costs allocation
     */
    public function createPurchase(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $baseSubtotal = '0.000';
            $storeId = $data['store_id'] ?? Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id;

            $purchase = Purchase::create([
                'purchase_number' => $data['purchase_number'] ?? $this->generateUniqueNumber(),
                'supplier_id' => $data['supplier_id'],
                'user_id' => $this->actorId($data),
                'store_id' => $storeId,
                'purchase_date' => $data['purchase_date'] ?? $this->tenantClock->businessDate(),
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
                'subtotal' => '0.000',
                'discount_amount' => $data['discount_amount'] ?? '0.000',
                'additional_expenses_total' => '0.000',
                'net_total' => '0.000',
                'paid_amount' => '0.000',
                'remaining_amount' => '0.000',
                'supplier_invoice_ref' => $data['supplier_invoice_ref'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $rawExpenses = $data['additional_expenses'] ?? [];
            $additionalExpensesTotal = '0.000';
            $supplierExpensesTotal = '0.000';

            // 1. Calculate overall items quantity and base totals for ratio distribution
            $totalQuantity = '0.000';
            $totalBaseValuation = '0.000';
            $itemsCount = count($data['items']);

            foreach ($data['items'] as $line) {
                $qty = (string) ($line['quantity'] ?? '0.000');
                $baseCost = (string) ($line['cost_price'] ?? $line['unit_cost'] ?? '0.000');
                $totalQuantity = bcadd($totalQuantity, $qty, 3);
                // CTO: half-up everywhere (App\Support\Money\Decimal), new documents only.
                $totalBaseValuation = bcadd($totalBaseValuation, Decimal::mul($qty, $baseCost), 3);
            }

            // 2. Process each item line and allocate landed expenses
            foreach ($data['items'] as $line) {
                $item = Item::where('id', $line['item_id'])->lockForUpdate()->firstOrFail();

                $quantity = (string) $line['quantity'];
                $baseCostPrice = (string) ($line['cost_price'] ?? $line['unit_cost'] ?? '0.000');
                $lineBaseTotal = Decimal::mul($quantity, $baseCostPrice);
                $baseSubtotal = bcadd($baseSubtotal, $lineBaseTotal, 3);

                // Allocate expenses to this line
                $lineAllocatedExpense = '0.000';
                foreach ($rawExpenses as $exp) {
                    $expAmount = (string) ($exp['amount'] ?? '0.000');
                    if (bccomp($expAmount, '0.000', 3) <= 0) {
                        continue;
                    }

                    $method = $exp['allocation_method'] ?? 'by_quantity';
                    $allocated = '0.000';

                    if ($method === 'by_quantity' && bccomp($totalQuantity, '0.000', 3) > 0) {
                        // expense x line.qty / totalQuantity, computed exactly then half-up.
                        $allocated = Decimal::round(bcdiv(bcmul($expAmount, $quantity, 6), $totalQuantity, 12));
                    } elseif ($method === 'by_value' && bccomp($totalBaseValuation, '0.000', 3) > 0) {
                        // expense x line.baseTotal / totalBaseValuation, computed exactly then half-up.
                        $allocated = Decimal::round(bcdiv(bcmul($expAmount, $lineBaseTotal, 6), $totalBaseValuation, 12));
                    } elseif ($method === 'equal' && $itemsCount > 0) {
                        $allocated = Decimal::round(bcdiv($expAmount, (string) $itemsCount, 12));
                    }

                    $lineAllocatedExpense = bcadd($lineAllocatedExpense, $allocated, 3);
                }

                // Landed Unit Cost = Base Cost + (Allocated Expense / Quantity)
                $unitAllocatedExpense = bccomp($quantity, '0.000', 3) > 0
                    ? Decimal::round(bcdiv($lineAllocatedExpense, $quantity, 12))
                    : '0.000';
                $landedUnitCost = bcadd($baseCostPrice, $unitAllocatedExpense, 3);

                // Create PurchaseItem
                $purchase->items()->create([
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'base_cost_price' => $baseCostPrice,
                    'allocated_expense' => $lineAllocatedExpense,
                    'cost_price' => $landedUnitCost, // Landed unit cost
                    'total_price' => $lineBaseTotal,
                ]);

                // Calculate weighted average cost with Landed Unit Cost
                $newWac = $this->calculateWeightedAverageCost(
                    currentStock: (string) $item->current_stock,
                    currentWac: $this->effectiveWeightedAverageCost($item),
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
                    notes: "توريد بضاعة بفاتورة شراء رقم {$purchase->purchase_number}".(bccomp($unitAllocatedExpense, '0.000', 3) > 0 ? " (شامل مصاريف محملة +{$unitAllocatedExpense} ج.م/وحدة)" : ''),
                    storeId: $storeId,
                    actorId: $this->actorId($data),
                );
            }

            // 3. Process and Save Additional Expenses & Generate Treasury Vouchers
            foreach ($rawExpenses as $exp) {
                $expAmount = (string) ($exp['amount'] ?? '0.000');
                if (bccomp($expAmount, '0.000', 3) <= 0) {
                    continue;
                }

                $title = trim($exp['title'] ?? 'مصاريف إضافية');
                $method = $exp['allocation_method'] ?? 'by_quantity';
                $paidBy = $exp['paid_by'] ?? 'supplier_account';
                $expNotes = $exp['notes'] ?? null;

                $additionalExpensesTotal = bcadd($additionalExpensesTotal, $expAmount, 3);

                $expenseRecord = $purchase->additionalExpenses()->create([
                    'title' => $title,
                    'amount' => $expAmount,
                    'allocation_method' => $method,
                    'paid_by' => $paidBy,
                    'notes' => $expNotes,
                ]);

                if ($paidBy === 'supplier_account') {
                    // Charged to supplier balance
                    $supplierExpensesTotal = bcadd($supplierExpensesTotal, $expAmount, 3);
                } else {
                    // Paid from treasury (Cash, Instapay, E-wallet)
                    $paymentMethod = str_replace('treasury_', '', $paidBy);
                    $payment = Payment::create([
                        'payment_number' => 'PAY-EXP-'.strtoupper(uniqid()),
                        'supplier_id' => $purchase->supplier_id,
                        'purchase_id' => $purchase->id,
                        'user_id' => $this->actorId($data),
                        'amount' => $expAmount,
                        'payment_date' => $purchase->purchase_date,
                        'payment_method' => $paymentMethod,
                        'notes' => "سداد مصروف ملحق [{$title}] لفاتورة مشتريات [{$purchase->purchase_number}]",
                    ]);
                    $expenseRecord->update(['payment_id' => $payment->id]);
                }
            }

            // 4. Calculate Net Total
            $discountAmount = (string) ($data['discount_amount'] ?? '0.000');
            $netTotal = bcsub($baseSubtotal, $discountAmount, 3);
            if (bccomp($supplierExpensesTotal, '0.000', 3) > 0) {
                $netTotal = bcadd($netTotal, $supplierExpensesTotal, 3);
            }

            $paidAmount = (string) ($data['paid_amount'] ?? '0.000');
            $remainingAmount = bcsub($netTotal, $paidAmount, 3);

            $paymentStatus = 'unpaid';
            if (bccomp($paidAmount, '0.000', 3) > 0) {
                $paymentStatus = bccomp($paidAmount, $netTotal, 3) >= 0 ? 'paid' : 'partially_paid';
            }

            $purchase->update([
                'subtotal' => $baseSubtotal,
                'discount_amount' => $discountAmount,
                'additional_expenses_total' => $additionalExpensesTotal,
                'net_total' => $netTotal,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_status' => $paymentStatus,
            ]);

            // 5. Record direct supplier payment voucher if paid amount exists
            if (bccomp($paidAmount, '0.000', 3) > 0) {
                Payment::create([
                    'payment_number' => 'PAY-PUR-'.strtoupper(uniqid()),
                    'supplier_id' => $purchase->supplier_id,
                    'purchase_id' => $purchase->id,
                    'user_id' => $this->actorId($data),
                    'amount' => $paidAmount,
                    'payment_date' => $purchase->purchase_date,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'notes' => "سداد دفعة توريد للفاتورة رقم {$purchase->purchase_number}",
                ]);
            }

            // 6. Update supplier balance
            $this->supplierBalanceService->updateBalance($purchase->supplier_id);

            $this->auditLogService->log(
                action: 'purchase_confirmed',
                auditable: $purchase,
                oldValues: null,
                newValues: $purchase->toArray(),
                actorId: $this->actorId($data),
            );

            $this->activityLogService->logPurchase(
                action: 'created',
                purchase: $purchase,
                description: "تم إنشاء فاتورة مشتريات وتوريد بضاعة رقم [{$purchase->purchase_number}] من المورد ({$purchase->supplier?->name}) بإجمالي ".number_format((float) $purchase->net_total, 2).' ج.م'.(bccomp($additionalExpensesTotal, '0.000', 3) > 0 ? ' (شامل مصاريف ملحقة '.number_format((float) $additionalExpensesTotal, 2).' ج.م)' : '')
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
            $lines = $lockedPurchase->items()->orderBy('id')->get();
            $items = $this->lockItemsInIdOrder($lines);

            // 1. Verify stock sufficiency for every item before reversing to prevent negative stock
            foreach ($lines->sortBy('item_id') as $itemLine) {
                $item = $items[$itemLine->item_id];
                $qty = (string) $itemLine->quantity;

                // Check master stock
                if (bccomp((string) $item->current_stock, $qty, 3) < 0) {
                    throw new Exception("تعذر إلغاء الفاتورة: رصيد الصنف [{$item->name}] الحالي ({$item->current_stock} {$item->unit}) أقل من الكمية المطلوب عكسها ({$qty} {$item->unit})، لوجود مبيعات تمت من هذه الشحنة.");
                }

                // Check store stock if assigned to a specific store
                if ($storeId) {
                    $storeStock = StoreStock::where('store_id', $storeId)
                        ->where('item_id', $item->id)
                        ->lockForUpdate()
                        ->first();

                    if ($storeStock && bccomp((string) $storeStock->quantity, $qty, 3) < 0) {
                        $storeName = $lockedPurchase->store?->name ?? "الفرع #{$storeId}";
                        throw new Exception("تعذر إلغاء الفاتورة: رصيد الصنف [{$item->name}] في [{$storeName}] ({$storeStock->quantity} {$item->unit}) غير كافٍ لخصم الكمية ({$qty} {$item->unit}).");
                    }
                }
            }

            // 2. BUG-03: work out every item's reversed cost from the pre-cancel stock. Applied
            //    after the stock movements below, so they keep recording the carried cost.
            $costReversals = $this->reversePurchaseCosts($lockedPurchase, $lines, $items);

            // 3. Reverse stock deductions safely
            foreach ($lines as $itemLine) {
                $item = $items[$itemLine->item_id];
                $qty = (string) $itemLine->quantity;

                $this->stockService->deductStock(
                    item: $item,
                    quantity: $qty,
                    source: $lockedPurchase,
                    documentNumber: $lockedPurchase->purchase_number,
                    movementType: 'purchase_cancel_out',
                    notes: "إلغاء فاتورة مشتريات وتوريد رقم {$lockedPurchase->purchase_number}".($reason ? " - سبب: {$reason}" : ''),
                    storeId: $storeId
                );
            }

            foreach ($costReversals as $itemId => $costs) {
                Item::whereKey($itemId)->update($costs);
            }

            $oldState = $lockedPurchase->toArray();

            // 4. Cancel associated payment vouchers
            Payment::where('purchase_id', $lockedPurchase->id)->delete();

            // 5. Update purchase status
            $cancelNote = 'تم إلغاء الفاتورة وعكس المخزون'.($reason ? " (السبب: {$reason})" : '');
            $lockedPurchase->update([
                'status' => 'cancelled',
                'remaining_amount' => '0.000',
                'notes' => $lockedPurchase->notes ? ($lockedPurchase->notes."\n".$cancelNote) : $cancelNote,
            ]);

            // 6. Recalculate supplier balance
            $this->supplierBalanceService->updateBalance($lockedPurchase->supplier_id);

            // 7. Audit and Activity Logging
            $this->auditLogService->log(
                action: 'purchase_cancelled',
                auditable: $lockedPurchase,
                oldValues: $oldState,
                newValues: $lockedPurchase->toArray()
            );

            $this->activityLogService->logPurchase(
                action: 'cancelled',
                purchase: $lockedPurchase,
                description: "تم إلغاء فاتورة المشتريات رقم [{$lockedPurchase->purchase_number}] وعكس الكميات من المخزون".($reason ? " (السبب: {$reason})" : '')
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

            $lines = $lockedPurchase->items()->orderBy('id')->get();
            $items = $this->lockItemsInIdOrder($lines);

            // BUG-03: cost_price is the last purchase's landed cost, so restoring an older
            // purchase must not overwrite the cost a newer confirmed purchase set.
            $ownsCostPrice = [];
            foreach (array_keys($items) as $itemId) {
                $newest = $this->latestConfirmedPurchaseLine($itemId);
                $ownsCostPrice[$itemId] = $newest === null || (int) $newest->purchase_id < (int) $lockedPurchase->id;
            }

            // 1. Re-add stock line by line in entry order (mirrors createPurchase) and re-apply WAC
            foreach ($lines as $itemLine) {
                $item = Item::where('id', $itemLine->item_id)->lockForUpdate()->firstOrFail();
                $qty = (string) $itemLine->quantity;
                $cost = (string) $itemLine->cost_price;

                $newWac = $this->calculateWeightedAverageCost(
                    currentStock: (string) $item->current_stock,
                    currentWac: $this->effectiveWeightedAverageCost($item),
                    newQuantity: $qty,
                    newCost: $cost
                );

                if ($ownsCostPrice[$item->id]) {
                    $item->cost_price = $cost;
                }
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
            if (bccomp((string) $lockedPurchase->paid_amount, '0.000', 3) > 0) {
                Payment::withTrashed()
                    ->where('purchase_id', $lockedPurchase->id)
                    ->restore();

                // If no trashed payment existed, create a new one
                $hasPayment = Payment::where('purchase_id', $lockedPurchase->id)->exists();
                if (! $hasPayment) {
                    Payment::create([
                        'payment_number' => 'PAY-PUR-'.strtoupper(uniqid()),
                        'supplier_id' => $lockedPurchase->supplier_id,
                        'purchase_id' => $lockedPurchase->id,
                        'user_id' => $this->actorId(),
                        'amount' => $lockedPurchase->paid_amount,
                        'payment_date' => $lockedPurchase->purchase_date,
                        'payment_method' => 'cash',
                        'notes' => "سداد دفعة توريد عند استعادة الفاتورة رقم {$lockedPurchase->purchase_number}",
                    ]);
                }
            }

            // 3. Recompute remaining amount and update status
            $remaining = bcsub((string) $lockedPurchase->net_total, (string) $lockedPurchase->paid_amount, 3);
            $lockedPurchase->update([
                'status' => 'confirmed',
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

            // Delete stock movements linked to this purchase
            StockMovement::where('source_type', Purchase::class)
                ->where('source_id', $lockedPurchase->id)
                ->delete();

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
        $existingStock = bccomp($currentStock, '0.000', 3) > 0 ? $currentStock : '0.000';
        // CTO: half-up everywhere (App\Support\Money\Decimal).
        $existingValuation = Decimal::mul($existingStock, $currentWac ?: '0.000');
        $newValuation = Decimal::mul($newQuantity, $newCost);
        $totalValuation = bcadd($existingValuation, $newValuation, 3);
        $totalQuantity = bcadd($existingStock, $newQuantity, 3);

        if (bccomp($totalQuantity, '0.000', 3) <= 0) {
            return $newCost;
        }

        return Decimal::round(bcdiv($totalValuation, $totalQuantity, 12));
    }

    /**
     * Moving-average reversal (BUG-03): take a document's valuation back out of the
     * current tenant-wide valuation, at the current quantity:
     *
     *   new WAC = (currentStock x currentWac - removedValuation) / (currentStock - removedQuantity)
     *
     * Units sold in between left at the blended average, so this conserves the inventory
     * value rather than jumping back to the pre-purchase average. Computed exactly with
     * bcmath (scale 6 products, scale 12 division), rounded half-up to scale 3 once.
     *
     * Returns null when no average is left to keep (remaining quantity <= 0, or remaining
     * valuation <= 0, e.g. the cost was edited by hand in between); the caller then
     * falls back to the last known cost.
     */
    public function reverseWeightedAverageCost(
        string $currentStock,
        string $currentWac,
        string $removedQuantity,
        string $removedValuation
    ): ?string {
        $remainingQuantity = bcsub($currentStock, $removedQuantity, 3);
        if (bccomp($remainingQuantity, '0.000', 3) <= 0) {
            return null;
        }

        $remainingValuation = bcsub(bcmul($currentStock, $currentWac, 6), $removedValuation, 6);
        if (bccomp($remainingValuation, '0', 6) <= 0) {
            return null;
        }

        return Decimal::round(bcdiv($remainingValuation, $remainingQuantity, 12));
    }

    /**
     * The average an item is carried at: weighted_avg_cost, or cost_price while no average
     * exists yet. Compared with bccomp because the decimal cast yields '0.000' (truthy).
     */
    public function effectiveWeightedAverageCost(Item $item): string
    {
        $wac = (string) $item->weighted_avg_cost;

        return bccomp($wac, '0.000', 3) > 0 ? $wac : (string) ($item->cost_price ?? '0.000');
    }

    /**
     * BUG-03: the costs each item of a purchase being cancelled goes back to. Lines of the
     * same item are reversed together (one rounding step instead of one per line).
     *
     * - weighted_avg_cost: reverseWeightedAverageCost(); when no average is left, the last
     *   known cost = landed cost of the latest other confirmed purchase line of the item,
     *   else the current average is kept (no independent cost evidence exists).
     * - cost_price (last purchase landed cost): only when this purchase is the item's
     *   latest confirmed purchase and still owns the value (not edited by hand since); it
     *   goes back to the previous confirmed purchase's cost, else to the new average.
     *
     * @param  Collection<int, PurchaseItem>  $lines
     * @param  array<int, Item>  $items  locked, keyed by id
     * @return array<int, array<string, string>>
     */
    private function reversePurchaseCosts(Purchase $purchase, Collection $lines, array $items): array
    {
        $reversals = [];

        foreach ($lines->groupBy('item_id') as $itemId => $itemLines) {
            $item = $items[$itemId];
            $quantity = '0.000';
            $valuation = '0';
            foreach ($itemLines as $line) {
                $quantity = bcadd($quantity, (string) $line->quantity, 3);
                $valuation = bcadd($valuation, bcmul((string) $line->quantity, (string) $line->cost_price, 6), 6);
            }

            $previous = $this->latestConfirmedPurchaseLine((int) $itemId, excludePurchaseId: $purchase->id);
            $lastKnownCost = $previous !== null ? (string) $previous->cost_price : null;

            $newWac = $this->reverseWeightedAverageCost(
                currentStock: (string) $item->current_stock,
                currentWac: $this->effectiveWeightedAverageCost($item),
                removedQuantity: $quantity,
                removedValuation: $valuation,
            ) ?? $lastKnownCost ?? (string) $item->weighted_avg_cost;

            $reversals[$itemId] = ['weighted_avg_cost' => $newWac];

            $newest = $this->latestConfirmedPurchaseLine((int) $itemId);
            $ownCost = (string) $itemLines->last()->cost_price;
            if ($newest !== null && (int) $newest->purchase_id === (int) $purchase->id && bccomp((string) $item->cost_price, $ownCost, 3) === 0) {
                $reversals[$itemId]['cost_price'] = $lastKnownCost ?? $newWac;
            }
        }

        return $reversals;
    }

    /**
     * The item's latest line on a confirmed, non-deleted purchase, by entry order (the
     * order in which createPurchase overwrites cost_price).
     */
    private function latestConfirmedPurchaseLine(int $itemId, ?int $excludePurchaseId = null): ?PurchaseItem
    {
        return PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchase_items.item_id', $itemId)
            ->where('purchases.status', 'confirmed')
            ->whereNull('purchases.deleted_at')
            ->when($excludePurchaseId !== null, fn ($query) => $query->where('purchases.id', '!=', $excludePurchaseId))
            ->orderByDesc('purchases.id')
            ->orderByDesc('purchase_items.id')
            ->select('purchase_items.*')
            ->first();
    }

    /**
     * Lock every item a document touches in ascending id order, so two documents that
     * share items never wait on each other in opposite orders.
     *
     * @param  Collection<int, PurchaseItem>  $lines
     * @return array<int, Item> keyed by id
     */
    private function lockItemsInIdOrder(Collection $lines): array
    {
        $ids = $lines->pluck('item_id')->unique()->sort()->values()->all();

        $items = Item::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id')->all();
        if (count($items) !== count($ids)) {
            throw (new ModelNotFoundException)->setModel(Item::class, array_values(array_diff($ids, array_keys($items))));
        }

        return $items;
    }

    public function generateUniqueNumber(): string
    {
        // SETG-2 ext: the date in the number is the business date (tenant clock + cutoff).
        $prefix = 'PUR-'.str_replace('-', '', $this->tenantClock->businessDate());

        $lastPurchase = Purchase::withTrashed()
            ->where('purchase_number', 'LIKE', $prefix.'-%')
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
            $candidate = $prefix.'-'.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
            $exists = Purchase::withTrashed()->where('purchase_number', $candidate)->exists();
            if ($exists) {
                $nextSequence++;
            }
        } while ($exists);

        return $candidate;
    }
}
