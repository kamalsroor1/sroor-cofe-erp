<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\Stock\InsufficientStockException;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use App\Support\Database\TransactionRetry;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Auth;

class StockTransferService
{
    protected DocumentNumberService $documentNumbers;

    public function __construct(
        protected ?ActivityLogService $activityLogService = null,
        protected ?TenantClock $tenantClock = null,
        ?DocumentNumberService $documentNumbers = null,
    ) {
        $this->activityLogService = $this->activityLogService ?: app(ActivityLogService::class);
        $this->tenantClock = $this->tenantClock ?: app(TenantClock::class);
        $this->documentNumbers = $documentNumbers ?: app(DocumentNumberService::class);
    }

    /**
     * The user a transfer (and its stock movements) is attributed to: the authenticated
     * user, else the explicit actor the caller passed (job/command), else NULL ("system").
     * Never a made-up id.
     */
    private function actorId(mixed $explicit = null): ?int
    {
        $authId = Auth::id();
        if ($authId !== null) {
            return (int) $authId;
        }

        return is_numeric($explicit) ? (int) $explicit : null;
    }

    /**
     * Create and execute a stock transfer between two stores/warehouses atomically
     */
    public function createTransfer(array $data): StockTransfer
    {
        $fromStoreId = (int) $data['from_store_id'];
        $toStoreId = (int) $data['to_store_id'];

        if ($fromStoreId === $toStoreId) {
            throw BusinessRuleException::make('inventory.transfer_same_location');
        }

        // BUG-04: the day's TRF counter row exists before the transaction takes its first read.
        if (empty($data['transfer_number'])) {
            $this->documentNumbers->prepareFor($this->transferNumberPrefix(), StockTransfer::class, 'transfer_number');
        }

        // BUG-07: restarted as a whole on a lock conflict (1213/1205/1020); only plain input is
        // captured, every row is read again inside and a failed attempt's number rolls back.
        return TransactionRetry::run(function () use ($data, $fromStoreId, $toStoreId) {
            // BUG-07: stable lock order first, so opposing transfers (1->2 and 2->1) and sales of
            // the same items queue on the same rows in the same order instead of deadlocking.
            $this->stockService()->lockStockRows(array_column((array) $data['items'], 'item_id'), [$fromStoreId, $toStoreId]);

            $fromStore = Store::where('id', $fromStoreId)->firstOrFail();
            $toStore = Store::where('id', $toStoreId)->firstOrFail();

            $transferNumber = $data['transfer_number'] ?? $this->generateUniqueNumber();
            $userId = $this->actorId($data['user_id'] ?? null);
            $transferDate = $data['transfer_date'] ?? $this->tenantClock->businessDate();
            $status = $data['status'] ?? 'confirmed';

            $transfer = StockTransfer::create([
                'transfer_number' => $transferNumber,
                'from_store_id' => $fromStoreId,
                'to_store_id' => $toStoreId,
                'user_id' => $userId,
                'transfer_date' => $transferDate,
                'status' => $status,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                $itemId = (int) $line['item_id'];
                $quantity = (string) $line['quantity'];

                if (bccomp($quantity, '0.000', 3) <= 0) {
                    continue;
                }

                // 1. Lock master item
                $item = Item::where('id', $itemId)->lockForUpdate()->firstOrFail();

                // 2. Lock and check source store stock
                $fromStock = StoreStock::where('store_id', $fromStoreId)
                    ->where('item_id', $itemId)
                    ->lockForUpdate()
                    ->first();

                if (! $fromStock || bccomp((string) $fromStock->quantity, $quantity, 3) < 0) {
                    $available = $fromStock ? (string) $fromStock->quantity : '0.000';
                    throw InsufficientStockException::forStore((string) $item->name, (string) $fromStore->name, (string) $available, (string) $item->unit);
                }

                // 3. If transfer is confirmed, move stock immediately
                if ($status === 'confirmed') {
                    // Deduct from source store
                    $fromStockBefore = (string) $fromStock->quantity;
                    $fromStockAfter = bcsub($fromStockBefore, $quantity, 3);
                    $fromStock->quantity = $fromStockAfter;
                    $fromStock->save();

                    // Add to destination store
                    $toStock = StoreStock::firstOrCreate(
                        [
                            'store_id' => $toStoreId,
                            'item_id' => $itemId,
                        ],
                        [
                            'quantity' => '0.000',
                            'min_stock' => $item->min_stock_level,
                            'custom_selling_price' => null,
                        ]
                    );

                    $toStock = StoreStock::where('id', $toStock->id)->lockForUpdate()->first();
                    $toStockBefore = (string) $toStock->quantity;
                    $toStockAfter = bcadd($toStockBefore, $quantity, 3);
                    $toStock->quantity = $toStockAfter;
                    $toStock->save();

                    // Record Movement for Source Store (transfer_out)
                    StockMovement::create([
                        'item_id' => $item->id,
                        'store_id' => $fromStoreId,
                        'movement_type' => 'transfer_out',
                        'quantity' => $quantity,
                        'stock_before' => $fromStockBefore,
                        'stock_after' => $fromStockAfter,
                        'unit_cost' => $item->cost_price,
                        'source_type' => StockTransfer::class,
                        'source_id' => $transfer->id,
                        'document_number' => $transfer->transfer_number,
                        'user_id' => $userId,
                        'notes' => "تحويل صادر إلى [{$toStore->name}] بإذن رقم {$transfer->transfer_number}",
                    ]);

                    // Record Movement for Destination Store (transfer_in)
                    StockMovement::create([
                        'item_id' => $item->id,
                        'store_id' => $toStoreId,
                        'movement_type' => 'transfer_in',
                        'quantity' => $quantity,
                        'stock_before' => $toStockBefore,
                        'stock_after' => $toStockAfter,
                        'unit_cost' => $item->cost_price,
                        'source_type' => StockTransfer::class,
                        'source_id' => $transfer->id,
                        'document_number' => $transfer->transfer_number,
                        'user_id' => $userId,
                        'notes' => "تحويل وارد من [{$fromStore->name}] بإذن رقم {$transfer->transfer_number}",
                    ]);
                }

                // 4. Create Transfer Item record
                $transfer->items()->create([
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                ]);
            }

            $this->activityLogService->log(
                module: 'inventory',
                action: 'created',
                description: "تم إنشاء إذن تحويل مخزني جديد رقم [{$transfer->transfer_number}] من ({$fromStore->name}) إلى ({$toStore->name}) بعدد (".count($data['items']).') صنف',
                subject: $transfer,
                properties: [
                    'transfer_number' => $transfer->transfer_number,
                    'from_store' => $fromStore->name,
                    'to_store' => $toStore->name,
                    'items_count' => count($data['items']),
                ],
                storeId: $fromStoreId
            );

            return $transfer;
        });
    }

    /**
     * Cancel an existing transfer with safe rollback of stock.
     *
     * $actorId is the explicit actor for callers without an authenticated user (jobs,
     * commands); the authenticated user always wins, and with neither the reversal
     * movements are attributed to NULL ("system").
     */
    public function cancelTransfer(StockTransfer $transfer, ?string $reason = null, ?int $actorId = null): StockTransfer
    {
        $userId = $this->actorId($actorId);

        $transferId = (int) $transfer->id;

        // BUG-07: restarted as a whole on a lock conflict; only the id is captured.
        return TransactionRetry::run(function () use ($transferId, $reason, $userId) {
            $lockedTransfer = StockTransfer::where('id', $transferId)->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status === 'cancelled') {
                throw BusinessRuleException::conflict('inventory.transfer_already_cancelled');
            }

            if ($lockedTransfer->status === 'confirmed') {
                // BUG-07: the lines' items / both stores' store_stocks in the global sorted order.
                $this->stockService()->lockStockRows(
                    $lockedTransfer->items->pluck('item_id'),
                    [$lockedTransfer->from_store_id, $lockedTransfer->to_store_id],
                );

                $fromStore = $lockedTransfer->fromStore;
                $toStore = $lockedTransfer->toStore;

                foreach ($lockedTransfer->items as $line) {
                    $item = Item::where('id', $line->item_id)->lockForUpdate()->firstOrFail();
                    $quantity = (string) $line->quantity;

                    // 1. Lock destination stock and check if it has enough to return
                    $toStock = StoreStock::where('store_id', $lockedTransfer->to_store_id)
                        ->where('item_id', $item->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $toStock || bccomp((string) $toStock->quantity, $quantity, 3) < 0) {
                        $available = $toStock ? (string) $toStock->quantity : '0.000';
                        throw BusinessRuleException::conflict('inventory.transfer_cancel_stock_consumed', ['item' => $item->name, 'store' => $toStore->name, 'available' => $available, 'required' => $quantity]);
                    }

                    // 2. Deduct from destination
                    $toStockBefore = (string) $toStock->quantity;
                    $toStockAfter = bcsub($toStockBefore, $quantity, 3);
                    $toStock->quantity = $toStockAfter;
                    $toStock->save();

                    // 3. Return to source
                    $fromStock = StoreStock::firstOrCreate(
                        [
                            'store_id' => $lockedTransfer->from_store_id,
                            'item_id' => $item->id,
                        ],
                        [
                            'quantity' => '0.000',
                            'min_stock' => $item->min_stock_level,
                            'custom_selling_price' => null,
                        ]
                    );

                    $fromStock = StoreStock::where('id', $fromStock->id)->lockForUpdate()->first();
                    $fromStockBefore = (string) $fromStock->quantity;
                    $fromStockAfter = bcadd($fromStockBefore, $quantity, 3);
                    $fromStock->quantity = $fromStockAfter;
                    $fromStock->save();

                    // 4. Record reversal movements
                    StockMovement::create([
                        'item_id' => $item->id,
                        'store_id' => $lockedTransfer->to_store_id,
                        'movement_type' => 'transfer_reversal_out',
                        'quantity' => $quantity,
                        'stock_before' => $toStockBefore,
                        'stock_after' => $toStockAfter,
                        'unit_cost' => $item->cost_price,
                        'source_type' => StockTransfer::class,
                        'source_id' => $lockedTransfer->id,
                        'document_number' => $lockedTransfer->transfer_number,
                        'user_id' => $userId,
                        'notes' => "عكس تحويل ملغي من [{$toStore->name}] بإذن رقم {$lockedTransfer->transfer_number}",
                    ]);

                    StockMovement::create([
                        'item_id' => $item->id,
                        'store_id' => $lockedTransfer->from_store_id,
                        'movement_type' => 'transfer_reversal_in',
                        'quantity' => $quantity,
                        'stock_before' => $fromStockBefore,
                        'stock_after' => $fromStockAfter,
                        'unit_cost' => $item->cost_price,
                        'source_type' => StockTransfer::class,
                        'source_id' => $lockedTransfer->id,
                        'document_number' => $lockedTransfer->transfer_number,
                        'user_id' => $userId,
                        'notes' => "إعادة بضاعة تحويل ملغي إلى [{$fromStore->name}] بإذن رقم {$lockedTransfer->transfer_number}",
                    ]);
                }
            }

            $lockedTransfer->status = 'cancelled';
            if ($reason) {
                $lockedTransfer->notes = ($lockedTransfer->notes ? $lockedTransfer->notes.' | ' : '').'سبب الإلغاء: '.$reason;
            }
            $lockedTransfer->save();

            $this->activityLogService->log(
                module: 'inventory',
                action: 'cancelled',
                description: "تم إلغاء إذن التحويل المخزني رقم [{$lockedTransfer->transfer_number}] وإعادة الأصناف للمخزن. سبب الإلغاء: ".($reason ?: 'بدون ملاحظات'),
                subject: $lockedTransfer,
                properties: ['reason' => $reason],
                storeId: $lockedTransfer->from_store_id
            );

            return $lockedTransfer;
        });
    }

    /**
     * Allocates the next number from the locked sequence: it CONSUMES a number (no previews).
     * Call it inside the transaction that inserts the document, after the party/document locks.
     */
    public function generateUniqueNumber(): string
    {
        return $this->documentNumbers->nextFor($this->transferNumberPrefix(), 4, StockTransfer::class, 'transfer_number');
    }

    private function transferNumberPrefix(): string
    {
        // SETG-2 ext: the number carries the tenant business date, not the server date.
        return 'TRF-'.str_replace('-', '', $this->tenantClock->businessDate());
    }

    private function stockService(): StockService
    {
        return app(StockService::class);
    }
}
