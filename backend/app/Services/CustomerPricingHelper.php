<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;

class CustomerPricingHelper
{
    /**
     * Retrieve the last price a specific customer purchased a specific item for.
     */
    public function getLastSoldPrice(int $customerId, int $itemId, ?int $storeId = null): ?array
    {
        $query = InvoiceItem::whereHas('invoice', function ($q) use ($customerId, $storeId) {
            $q->where('customer_id', $customerId)
                ->where('status', 'confirmed');

            if ($storeId) {
                $q->where('store_id', $storeId);
            }
        })
            ->where('item_id', $itemId)
            ->with('invoice.store')
            ->latest('id');

        $lastItem = $query->first();

        // If not found in this specific store, check customer's history across all stores/vans
        if (! $lastItem && $storeId) {
            $lastItem = InvoiceItem::whereHas('invoice', function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                    ->where('status', 'confirmed');
            })
                ->where('item_id', $itemId)
                ->with('invoice.store')
                ->latest('id')
                ->first();
        }

        if (! $lastItem) {
            return null;
        }

        return [
            'unit_price' => (string) $lastItem->unit_price,
            'invoice_number' => $lastItem->invoice->invoice_number,
            'invoice_date' => $lastItem->invoice->invoice_date ? $lastItem->invoice->invoice_date->format('Y-m-d') : '',
            'quantity' => (string) $lastItem->quantity,
            'store_name' => $lastItem->invoice->store?->name ?? 'الفرع الرئيسي',
        ];
    }

    /**
     * Get recommended pricing breakdown for POS/Invoice screen.
     */
    public function getRecommendedPrice(int $customerId, int $itemId, ?int $storeId = null): array
    {
        $item = Item::find($itemId);
        $masterPrice = $item ? (string) $item->selling_price : '0.000';

        $storePrice = null;
        if ($storeId && $item) {
            $storeStock = StoreStock::where('store_id', $storeId)->where('item_id', $itemId)->first();
            if ($storeStock && $storeStock->custom_selling_price !== null && bccomp((string) $storeStock->custom_selling_price, '0.000', 3) > 0) {
                $storePrice = (string) $storeStock->custom_selling_price;
            }
        }

        $lastCustomerPrice = $customerId && $itemId ? $this->getLastSoldPrice($customerId, $itemId, $storeId) : null;

        $defaultPrice = $storePrice ?? $masterPrice;

        return [
            'default_price' => $defaultPrice,
            'master_price' => $masterPrice,
            'store_custom_price' => $storePrice,
            'last_customer_price' => $lastCustomerPrice,
        ];
    }
}
