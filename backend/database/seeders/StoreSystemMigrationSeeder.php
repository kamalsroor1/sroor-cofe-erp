<?php

namespace Database\Seeders;

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreSystemMigrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Safely migrates all existing records into the multi-store system without data loss.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Create or retrieve the Main Store / Central Warehouse
            $mainStore = Store::firstOrCreate(
                ['is_main' => true],
                [
                    'name' => 'الفرع والمخزن الرئيسي',
                    'code' => 'MAIN-01',
                    'type' => 'main_warehouse',
                    'phone' => '01000000100',
                    'address' => 'المقر الرئيسي والمخزن المركزي',
                    'is_active' => true,
                    'is_main' => true,
                ]
            );

            // Also create a sample Wholesale Van if none exists for instant demonstration
            Store::firstOrCreate(
                ['code' => 'VAN-01'],
                [
                    'name' => 'عربية توزيع رقم 1 (جملة)',
                    'code' => 'VAN-01',
                    'type' => 'wholesale_van',
                    'phone' => null,
                    'address' => 'سيارة توزيع جملة ومطاحن',
                    'is_active' => true,
                    'is_main' => false,
                ]
            );

            // 2. Initialize StoreStock for every existing Item in the Main Store
            $items = Item::all();
            foreach ($items as $item) {
                StoreStock::firstOrCreate(
                    [
                        'store_id' => $mainStore->id,
                        'item_id' => $item->id,
                    ],
                    [
                        'quantity' => $item->current_stock,
                        'min_stock' => $item->min_stock_level,
                        'custom_selling_price' => null,
                    ]
                );
            }

            // 3. Link all existing Users to the Main Store
            $users = User::all();
            foreach ($users as $user) {
                if (! $user->default_store_id) {
                    $user->update(['default_store_id' => $mainStore->id]);
                }
                $user->stores()->syncWithoutDetaching([$mainStore->id]);
            }

            // 4. Backfill existing records with main store_id if null
            Invoice::whereNull('store_id')->update(['store_id' => $mainStore->id]);
            Purchase::whereNull('store_id')->update(['store_id' => $mainStore->id]);
            Expense::whereNull('store_id')->update(['store_id' => $mainStore->id]);
            ReturnDocument::whereNull('store_id')->update(['store_id' => $mainStore->id]);
            CashShift::whereNull('store_id')->update(['store_id' => $mainStore->id]);
            StockMovement::whereNull('store_id')->update(['store_id' => $mainStore->id]);
        });
    }
}
