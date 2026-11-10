<?php

declare(strict_types=1);

use App\Support\WeightUnits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POS-06: items created after the POSB-2 backfill defaulted to is_weighted = false even with a weight unit
 * (the default unit is كجم), so the POS rejected their fractional sales. Re-run the same unit matching for
 * rows still unflagged. Idempotent: it only ever sets false -> true, soft-deleted rows included.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('items') || ! Schema::hasColumn('items', 'is_weighted')) {
            return;
        }

        $ids = [];
        DB::table('items')
            ->select(['id', 'unit'])
            ->where('is_weighted', false)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$ids): void {
                foreach ($rows as $row) {
                    if (WeightUnits::matches($row->unit)) {
                        $ids[] = (int) $row->id;
                    }
                }
            });

        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table('items')->whereIn('id', $chunk)->update(['is_weighted' => true]);
        }
    }

    public function down(): void
    {
        // Data backfill: nothing to undo.
    }
};
