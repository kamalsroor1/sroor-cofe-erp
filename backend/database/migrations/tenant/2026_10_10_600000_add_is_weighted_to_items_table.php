<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * POSB-2 (tenant DB): explicit `items.is_weighted` flag. Until now the POS guessed weighing
 * from the unit name (POSCartItem/POSItemCard: unit === 'كجم' | 'جم' | contains 'كيلو'),
 * and the default unit is 'كجم'. The one-off backfill flags every item (soft-deleted ones
 * included) whose unit is a weight unit, and logs the affected ids per tenant so the owner
 * can review them. New rows default to false: the flag is set explicitly from now on.
 */
return new class extends Migration
{
    /** Exact (trimmed, lower-cased) unit names treated as weight units by the backfill. */
    private const WEIGHT_UNITS = [
        'كجم', 'كج', 'كغ', 'كغم', 'كيلو', 'كيلوجرام', 'كيلو جرام', 'كيلوغرام', 'كيلو غرام',
        'جم', 'جرام', 'غرام', 'غم',
        'kg', 'kgs', 'kilo', 'kilos', 'kilogram', 'kilograms',
        'g', 'gm', 'gr', 'grm', 'gram', 'grams',
    ];

    /** Unit names containing this fragment are weight units too (legacy POS rule). */
    private const WEIGHT_UNIT_FRAGMENT = 'كيلو';

    /** Max ids written into the backfill log line (the count is always exact). */
    private const LOGGED_IDS_LIMIT = 500;

    public function up(): void
    {
        if (! Schema::hasTable('items') || Schema::hasColumn('items', 'is_weighted')) {
            return;
        }

        Schema::table('items', function (Blueprint $table): void {
            $table->boolean('is_weighted')->default(false)->index('items_is_weighted_index');
        });

        $this->backfill();
    }

    public function down(): void
    {
        if (! Schema::hasTable('items') || ! Schema::hasColumn('items', 'is_weighted')) {
            return;
        }

        Schema::table('items', function (Blueprint $table): void {
            $table->dropIndex('items_is_weighted_index');
        });

        Schema::table('items', function (Blueprint $table): void {
            $table->dropColumn('is_weighted');
        });
    }

    private function backfill(): void
    {
        $affected = [];

        DB::table('items')
            ->select(['id', 'unit'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$affected): void {
                foreach ($rows as $row) {
                    if ($this->isWeightUnit((string) ($row->unit ?? ''))) {
                        $affected[] = (int) $row->id;
                    }
                }
            });

        foreach (array_chunk($affected, 500) as $ids) {
            DB::table('items')->whereIn('id', $ids)->update(['is_weighted' => true]);
        }

        Log::info('POSB-2 items.is_weighted backfill', [
            'tenant' => function_exists('tenant') ? tenant()?->getTenantKey() : null,
            'affected_count' => count($affected),
            'affected_item_ids' => array_slice($affected, 0, self::LOGGED_IDS_LIMIT),
        ]);
    }

    private function isWeightUnit(string $unit): bool
    {
        $normalized = mb_strtolower(trim($unit));

        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, self::WEIGHT_UNITS, true)
            || str_contains($normalized, self::WEIGHT_UNIT_FRAGMENT);
    }
};
