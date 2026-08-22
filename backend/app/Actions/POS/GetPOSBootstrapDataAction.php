<?php

namespace App\Actions\POS;

use App\Http\Resources\POSItemResource;
use App\Http\Resources\POSCustomerResource;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Store;
use App\Models\CashShift;
use App\Models\User;

class GetPOSBootstrapDataAction
{
    /**
     * تجميع وتهيئة بيانات شاشة الكاشير ونقاط البيع السريعة عبر JsonResources
     */
    public function execute(?User $user): array
    {
        // 1. Resolve Active Store
        $storeId = session('current_store_id');
        $activeStore = null;
        if ($storeId) {
            $activeStore = Store::where('id', $storeId)->where('is_active', true)->first();
        }
        if (!$activeStore && $user) {
            $activeStore = $user->getCurrentStore();
            if ($activeStore) {
                $storeId = $activeStore->id;
            }
        }

        // 2. Active Cashier Shift
        $activeShift = null;
        if ($storeId) {
            $activeShift = CashShift::where('store_id', $storeId)
                ->where('status', 'open')
                ->latest('id')
                ->first();
        }

        // 3. Fast Active Items with Store Stock Left-Join (Ultra-Optimized for 10k+ items)
        if ($storeId) {
            $rawItems = Item::where('items.is_active', true)
                ->leftJoin('store_stocks', function ($join) use ($storeId) {
                    $join->on('store_stocks.item_id', '=', 'items.id')
                         ->where('store_stocks.store_id', '=', $storeId);
                })
                ->select([
                    'items.id',
                    'items.code',
                    'items.name',
                    'items.category',
                    'items.category_id',
                    'items.unit',
                    'items.cost_price',
                    'items.selling_price',
                    'items.min_selling_price',
                    'items.min_stock_level',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(store_stocks.quantity, items.current_stock) as calculated_stock')
                ])
                ->orderBy('items.name')
                ->get();
        } else {
            $rawItems = Item::where('is_active', true)
                ->select([
                    'id', 'code', 'name', 'category', 'category_id', 'unit',
                    'cost_price', 'selling_price', 'min_selling_price', 'min_stock_level',
                    'current_stock as calculated_stock'
                ])
                ->orderBy('name')
                ->get();
        }

        $items = $rawItems->map(function ($it) {
            return [
                'id' => (int)$it->id,
                'name' => (string)$it->name,
                'code' => (string)($it->code ?? ''),
                'category' => (string)($it->category ?: 'عام'),
                'category_id' => $it->category_id ? (int)$it->category_id : null,
                'price_retail' => (float)$it->selling_price,
                'price_wholesale' => (float)(($it->min_selling_price && $it->min_selling_price > 0) ? $it->min_selling_price : $it->selling_price),
                'min_selling_price' => (float)($it->min_selling_price ?? $it->cost_price ?? 0),
                'cost_price' => (float)$it->cost_price,
                'current_stock' => (float)($it->calculated_stock ?? 0),
                'min_stock_level' => (float)($it->min_stock_level ?: 0),
                'unit' => (string)($it->unit ?: 'قطعة'),
            ];
        })->values()->all();

        // 4. Categories list with icons
        if (\App\Models\Category::count() === 0) {
            $distinctCats = Item::whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category');
            $icons = ['☕', '🧃', '🍰', '🥪', '🍪', '🫘', '🥤', '🧊', '🎁', '📦'];
            $i = 0;
            foreach ($distinctCats as $cName) {
                $icon = $icons[$i % count($icons)];
                $cat = \App\Models\Category::create([
                    'name'       => $cName,
                    'icon'       => $icon,
                    'sort_order' => $i,
                    'is_active'  => true,
                ]);
                Item::where('category', $cName)->update(['category_id' => $cat->id]);
                $i++;
            }
        }

        $categories = \App\Models\Category::where('is_active', true)
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);

        // 5. Active Customers via POSCustomerResource
        $customers = Customer::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Default Cash Customer
        $defaultCustomer = $customers->first();

        return [
            'items' => $items,
            'categories' => $categories,
            'customers' => POSCustomerResource::collection($customers)->resolve(),
            'default_customer' => $defaultCustomer ? (new POSCustomerResource($defaultCustomer))->resolve() : [
                'id' => 1,
                'name' => 'عميل نقدي عام',
                'phone' => '',
                'price_tier' => 'retail',
                'current_balance' => 0,
            ],
            'active_store' => $activeStore ? [
                'id' => $activeStore->id,
                'name' => $activeStore->name,
                'type' => $activeStore->type,
            ] : null,
            'active_shift' => $activeShift ? [
                'id' => $activeShift->id,
                'shift_number' => $activeShift->shift_number ?? $activeShift->id,
                'opened_at' => $activeShift->opened_at,
            ] : null,
        ];
    }
}
