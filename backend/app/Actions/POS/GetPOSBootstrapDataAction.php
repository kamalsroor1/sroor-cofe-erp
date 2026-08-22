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

        // 3. Active Items with stock calculation via POSItemResource
        $items = Item::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

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
            'items' => POSItemResource::collection($items)->additional(['store_id' => $storeId])->resolve(),
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
