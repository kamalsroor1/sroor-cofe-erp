<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class StockTransferService
{
    public function __construct(
        protected ?ActivityLogService $activityLogService = null
    ) {
        $this->activityLogService = $this->activityLogService ?: app(ActivityLogService::class);
    }

    /**
     * Create and execute a stock transfer between two stores/warehouses atomically
     */
    public function createTransfer(array $data): StockTransfer
    {
        $fromStoreId = (int)$data['from_store_id'];
        $toStoreId   = (int)$data['to_store_id'];

        if ($fromStoreId === $toStoreId) {
            throw new Exception("لا يمكن إجراء تحويل مخزني لنفس الفرع أو المخزن.");
        }

        return DB::transaction(function () use ($data, $fromStoreId, $toStoreId) {
            $fromStore = Store::where('id', $fromStoreId)->firstOrFail();
            $toStore   = Store::where('id', $toStoreId)->firstOrFail();

            $transferNumber = $data['transfer_number'] ?? $this->generateUniqueNumber();
            $userId = Auth::id() ?? $data['user_id'] ?? 1;
            $transferDate = $data['transfer_date'] ?? now()->toDateString();
            $status = $data['status'] ?? 'confirmed';

            $transfer = StockTransfer::create([
                'transfer_number' => $transferNumber,
                'from_store_id'   => $fromStoreId,
                'to_store_id'     => $toStoreId,
                'user_id'         => $userId,
                'transfer_date'   => $transferDate,
                'status'          => $status,
                'notes'           => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                $itemId = (int)$line['item_id'];
                $quantity = (string)$line['quantity'];

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

                if (!$fromStock || bccomp((string)$fromStock->quantity, $quantity, 3) < 0) {
                    $available = $fromStock ? (string)$fromStock->quantity : '0.000';
                    throw new Exception("رصيد الصنف [{$item->name}] في [{$fromStore->name}] غير كافٍ لإجراء التحويل. المتوفر حالياً: {$available} {$item->unit}");
                }

                // 3. If transfer is confirmed, move stock immediately
                if ($status === 'confirmed') {
                    // Deduct from source store
                    $fromStockBefore = (string)$fromStock->quantity;
                    $fromStockAfter  = bcsub($fromStockBefore, $quantity, 3);
                    $fromStock->quantity = $fromStockAfter;
                    $fromStock->save();

                    // Add to destination store
                    $toStock = StoreStock::firstOrCreate(
                        [
                            'store_id' => $toStoreId,
                            'item_id'  => $itemId,
                        ],
                        [
                            'quantity'             => '0.000',
                            'min_stock'            => $item->min_stock_level,
                            'custom_selling_price' => null,
                        ]
                    );

                    $toStock = StoreStock::where('id', $toStock->id)->lockForUpdate()->first();
                    $toStockBefore = (string)$toStock->quantity;
                    $toStockAfter  = bcadd($toStockBefore, $quantity, 3);
                    $toStock->quantity = $toStockAfter;
                    $toStock->save();

                    // Record Movement for Source Store (transfer_out)
                    StockMovement::create([
                        'item_id'         => $item->id,
                        'store_id'        => $fromStoreId,
                        'movement_type'   => 'transfer_out',
                        'quantity'        => $quantity,
                        'stock_before'    => $fromStockBefore,
                        'stock_after'     => $fromStockAfter,
                        'unit_cost'       => $item->effectiveCost(),
                        'source_type'     => StockTransfer::class,
                        'source_id'       => $transfer->id,
                        'document_number' => $transfer->transfer_number,
                        'user_id'         => $userId,
                        'notes'           => "تحويل صادر إلى [{$toStore->name}] بإذن رقم {$transfer->transfer_number}",
                    ]);

                    // Record Movement for Destination Store (transfer_in)
                    StockMovement::create([
                        'item_id'         => $item->id,
                        'store_id'        => $toStoreId,
                        'movement_type'   => 'transfer_in',
                        'quantity'        => $quantity,
                        'stock_before'    => $toStockBefore,
                        'stock_after'     => $toStockAfter,
                        'unit_cost'       => $item->effectiveCost(),
                        'source_type'     => StockTransfer::class,
                        'source_id'       => $transfer->id,
                        'document_number' => $transfer->transfer_number,
                        'user_id'         => $userId,
                        'notes'           => "تحويل وارد من [{$fromStore->name}] بإذن رقم {$transfer->transfer_number}",
                    ]);
                }

                // 4. Create Transfer Item record
                $transfer->items()->create([
                    'item_id'  => $itemId,
                    'quantity' => $quantity,
                ]);
            }

            $this->activityLogService->log(
                module: 'inventory',
                action: 'created',
                description: "تم إنشاء إذن تحويل مخزني جديد رقم [{$transfer->transfer_number}] من ({$fromStore->name}) إلى ({$toStore->name}) بعدد (" . count($data['items']) . ") صنف",
                subject: $transfer,
                properties: [
                    'transfer_number' => $transfer->transfer_number,
                    'from_store'      => $fromStore->name,
                    'to_store'        => $toStore->name,
                    'items_count'     => count($data['items']),
                ],
                storeId: $fromStoreId
            );

            return $transfer;
        });
    }

    /**
     * Cancel an existing transfer with safe rollback of stock
     */
    public function cancelTransfer(StockTransfer $transfer, ?string $reason = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $reason) {
            $lockedTransfer = StockTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status === 'cancelled') {
                throw new Exception("إذن التحويل ملغي مسبقاً.");
            }

            if ($lockedTransfer->status === 'confirmed') {
                $fromStore = $lockedTransfer->fromStore;
                $toStore   = $lockedTransfer->toStore;

                foreach ($lockedTransfer->items as $line) {
                    $item = Item::where('id', $line->item_id)->lockForUpdate()->firstOrFail();
                    $quantity = (string)$line->quantity;

                    // 1. Lock destination stock and check if it has enough to return
                    $toStock = StoreStock::where('store_id', $lockedTransfer->to_store_id)
                        ->where('item_id', $item->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$toStock || bccomp((string)$toStock->quantity, $quantity, 3) < 0) {
                        $available = $toStock ? (string)$toStock->quantity : '0.000';
                        throw new Exception("لا يمكن إلغاء التحويل لأن رصيد الصنف [{$item->name}] في [{$toStore->name}] تم التصرف فيه (المتبقي: {$available}، والمطلوب إرجاعه: {$quantity}).");
                    }

                    // 2. Deduct from destination
                    $toStockBefore = (string)$toStock->quantity;
                    $toStockAfter  = bcsub($toStockBefore, $quantity, 3);
                    $toStock->quantity = $toStockAfter;
                    $toStock->save();

                    // 3. Return to source
                    $fromStock = StoreStock::firstOrCreate(
                        [
                            'store_id' => $lockedTransfer->from_store_id,
                            'item_id'  => $item->id,
                        ],
                        [
                            'quantity'             => '0.000',
                            'min_stock'            => $item->min_stock_level,
                            'custom_selling_price' => null,
                        ]
                    );

                    $fromStock = StoreStock::where('id', $fromStock->id)->lockForUpdate()->first();
                    $fromStockBefore = (string)$fromStock->quantity;
                    $fromStockAfter  = bcadd($fromStockBefore, $quantity, 3);
                    $fromStock->quantity = $fromStockAfter;
                    $fromStock->save();

                    // 4. Record reversal movements
                    StockMovement::create([
                        'item_id'         => $item->id,
                        'store_id'        => $lockedTransfer->to_store_id,
                        'movement_type'   => 'transfer_reversal_out',
                        'quantity'        => $quantity,
                        'stock_before'    => $toStockBefore,
                        'stock_after'     => $toStockAfter,
                        'unit_cost'       => $item->effectiveCost(),
                        'source_type'     => StockTransfer::class,
                        'source_id'       => $lockedTransfer->id,
                        'document_number' => $lockedTransfer->transfer_number,
                        'user_id'         => Auth::id() ?? 1,
                        'notes'           => "عكس تحويل ملغي من [{$toStore->name}] بإذن رقم {$lockedTransfer->transfer_number}",
                    ]);

                    StockMovement::create([
                        'item_id'         => $item->id,
                        'store_id'        => $lockedTransfer->from_store_id,
                        'movement_type'   => 'transfer_reversal_in',
                        'quantity'        => $quantity,
                        'stock_before'    => $fromStockBefore,
                        'stock_after'     => $fromStockAfter,
                        'unit_cost'       => $item->effectiveCost(),
                        'source_type'     => StockTransfer::class,
                        'source_id'       => $lockedTransfer->id,
                        'document_number' => $lockedTransfer->transfer_number,
                        'user_id'         => Auth::id() ?? 1,
                        'notes'           => "إعادة بضاعة تحويل ملغي إلى [{$fromStore->name}] بإذن رقم {$lockedTransfer->transfer_number}",
                    ]);
                }
            }

            $lockedTransfer->status = 'cancelled';
            if ($reason) {
                $lockedTransfer->notes = ($lockedTransfer->notes ? $lockedTransfer->notes . " | " : '') . "سبب الإلغاء: " . $reason;
            }
            $lockedTransfer->save();

            $this->activityLogService->log(
                module: 'inventory',
                action: 'cancelled',
                description: "تم إلغاء إذن التحويل المخزني رقم [{$lockedTransfer->transfer_number}] وإعادة الأصناف للمخزن. سبب الإلغاء: " . ($reason ?: 'بدون ملاحظات'),
                subject: $lockedTransfer,
                properties: ['reason' => $reason],
                storeId: $lockedTransfer->from_store_id
            );

            return $lockedTransfer;
        });
    }

    /**
     * Generate sequential unique transfer document number
     */
    public function generateUniqueNumber(): string
    {
        $prefix = 'TRF-' . date('Ymd');
        
        $lastTransfer = StockTransfer::withTrashed()
            ->where('transfer_number', 'LIKE', $prefix . '-%')
            ->orderBy('transfer_number', 'desc')
            ->first();

        if ($lastTransfer) {
            $parts = explode('-', $lastTransfer->transfer_number);
            $lastSequence = (int) end($parts);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        do {
            $candidate = $prefix . '-' . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
            $exists = StockTransfer::withTrashed()->where('transfer_number', $candidate)->exists();
            if ($exists) {
                $nextSequence++;
            }
        } while ($exists);

        return $candidate;
    }
}
