<?php

declare(strict_types=1);

namespace App\Actions\POS;

use App\Models\Item;
use App\Models\Store;
use App\Services\Pos\InvalidScaleBarcodeException;
use App\Services\Pos\ScaleBarcodeParser;
use Illuminate\Support\Facades\DB;

/**
 * POS-01: resolves ONE scanned code to ONE item of the active store. Exact match only,
 * never LIKE: first the item code, then (when the store enabled scale labels) the decoded PLU.
 *
 * Returns null when nothing matches. Throws InvalidScaleBarcodeException for a scale label
 * whose check digit is wrong (never guess a weight from a damaged label).
 */
final class LookupPosBarcodeAction
{
    public function __construct(
        private readonly GetStorePosSettingsAction $getStorePosSettingsAction,
        private readonly ScaleBarcodeParser $parser,
    ) {}

    /**
     * @return array{item: Item, quantity: string|null, scanned_price: string|null, source: string}|null
     */
    public function execute(string $code, Store $store): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $storeId = (int) $store->id;

        $item = $this->findByCode($code, $storeId);
        if ($item instanceof Item) {
            return ['item' => $item, 'quantity' => null, 'scanned_price' => null, 'source' => 'code'];
        }

        $config = $this->getStorePosSettingsAction->execute($storeId)->scaleConfig();
        $scale = $this->parser->parse($code, $config);
        if ($scale === null) {
            return null;
        }

        $item = $this->findByCode($scale->plu, $storeId);
        if (! $item instanceof Item) {
            return null;
        }

        $quantity = $scale->quantity;
        if ($quantity === null && $scale->price !== null) {
            if (bccomp((string) $item->selling_price, '0', 3) <= 0) {
                throw InvalidScaleBarcodeException::unpricedItem($code);
            }
            // Price label: quantity = labelled price / unit price (bcdiv, truncated to 3 decimals).
            $quantity = bcdiv($scale->price, (string) $item->selling_price, 3);
        }

        return [
            'item' => $item,
            'quantity' => $quantity,
            'scanned_price' => $scale->price,
            'source' => 'scale',
        ];
    }

    private function findByCode(string $code, int $storeId): ?Item
    {
        return Item::query()
            ->where('items.is_active', true)
            ->where('items.code', $code)
            ->leftJoin('store_stocks', function ($join) use ($storeId): void {
                $join->on('store_stocks.item_id', '=', 'items.id')
                    ->where('store_stocks.store_id', '=', $storeId);
            })
            ->select('items.*', DB::raw('COALESCE(store_stocks.quantity, items.current_stock) as calculated_stock'))
            ->first();
    }
}
