<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BE-01 follow-up (tenant DB): invoices, purchases and returns created before store scoping have
 * store_id NULL, so a store-scoped query never sees them. They are attributed to the main store
 * (stores.is_main = true, else the first store). Soft-deleted documents are included.
 *
 * Rows that already have a store are never touched; a tenant without any store is left alone;
 * running it twice changes nothing (the second run finds no NULLs). Stock, balances and
 * treasury are not changed: only the attribution of the document.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = ['invoices', 'purchases', 'returns'];

    public function up(): void
    {
        if (! Schema::hasTable('stores')) {
            return;
        }

        $storeId = $this->targetStoreId();
        if ($storeId === null) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'store_id')) {
                continue;
            }

            DB::table($table)->whereNull('store_id')->update(['store_id' => $storeId]);
        }
    }

    /**
     * Data attribution cannot be undone: the original NULLs are not recorded, and moving
     * documents back out of their store would hide them again.
     */
    public function down(): void {}

    private function targetStoreId(): ?int
    {
        $live = static fn () => DB::table('stores')
            ->when(Schema::hasColumn('stores', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'));

        $id = $live()->where('is_main', true)->orderBy('id')->value('id')
            ?? $live()->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }
};
