<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Models\Customer;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * BE-01 follow-up: documents with store_id NULL are attributed to the main store; documents
 * that already have a store are never touched.
 */
final class BackfillNullStoreIdMigrationTest extends TenantTestCase
{
    private function runUp(): void
    {
        $migration = require database_path('migrations/tenant/2026_11_13_100400_backfill_null_store_id_on_documents.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $migration->up();
    }

    /** @return array{invoices: array<string, int>, purchases: array<string, int>, returns: array<string, int>} */
    private function seedDocuments(int $userId, ?int $ownStoreId): array
    {
        $customer = Customer::create(['name' => 'C', 'phone' => '01100000071', 'current_balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'S', 'phone' => '01100000072', 'current_balance' => '0.000', 'is_active' => true]);
        $now = now();

        $ids = ['invoices' => [], 'purchases' => [], 'returns' => []];
        foreach (['null' => null, 'own' => $ownStoreId, 'deleted' => null] as $key => $storeId) {
            $deletedAt = $key === 'deleted' ? $now : null;
            $ids['invoices'][$key] = (int) DB::table('invoices')->insertGetId([
                'invoice_number' => 'BF-INV-'.$key, 'customer_id' => $customer->id, 'user_id' => $userId,
                'invoice_date' => $now->toDateString(), 'store_id' => $storeId, 'deleted_at' => $deletedAt,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $ids['purchases'][$key] = (int) DB::table('purchases')->insertGetId([
                'purchase_number' => 'BF-PUR-'.$key, 'supplier_id' => $supplier->id, 'user_id' => $userId,
                'purchase_date' => $now->toDateString(), 'store_id' => $storeId, 'deleted_at' => $deletedAt,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $ids['returns'][$key] = (int) DB::table('returns')->insertGetId([
                'return_number' => 'BF-RET-'.$key, 'return_type' => 'sales_return', 'user_id' => $userId,
                'return_date' => $now->toDateString(), 'store_id' => $storeId, 'deleted_at' => $deletedAt,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        return $ids;
    }

    public function test_null_store_documents_go_to_the_main_store_and_others_are_untouched(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->tenantAdmin($tenant);

        $this->inTenant($tenant, function () use ($admin): void {
            $main = Store::query()->where('is_main', true)->firstOrFail();
            $other = Store::create(['name' => 'Other', 'code' => 'BF-OTH', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);
            $ids = $this->seedDocuments((int) $admin->id, $other->id);

            $this->runUp();
            $this->runUp();

            foreach (['invoices', 'purchases', 'returns'] as $table) {
                $this->assertSame($main->id, (int) DB::table($table)->where('id', $ids[$table]['null'])->value('store_id'), "{$table}: NULL -> main store");
                $this->assertSame($main->id, (int) DB::table($table)->where('id', $ids[$table]['deleted'])->value('store_id'), "{$table}: soft-deleted rows too");
                $this->assertSame($other->id, (int) DB::table($table)->where('id', $ids[$table]['own'])->value('store_id'), "{$table}: a row with a store is never touched");
                $this->assertSame(0, DB::table($table)->whereNull('store_id')->count());
            }
        });
    }

    public function test_without_a_main_store_the_first_store_is_used(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->tenantAdmin($tenant);

        $this->inTenant($tenant, function () use ($admin): void {
            Store::query()->update(['is_main' => false]);
            $first = Store::query()->orderBy('id')->firstOrFail();
            Store::create(['name' => 'Second', 'code' => 'BF-SEC', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);
            $ids = $this->seedDocuments((int) $admin->id, null);

            $this->runUp();

            $this->assertSame($first->id, (int) DB::table('invoices')->where('id', $ids['invoices']['null'])->value('store_id'));
            $this->assertSame($first->id, (int) DB::table('returns')->where('id', $ids['returns']['null'])->value('store_id'));
        });
    }
}
