<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.2 (central): billing-grade `plans`.
 *
 * - prices DECIMAL(10,2) -> DECIMAL(12,3) (golden rule 1);
 * - every limit becomes NULLABLE, NULL = unlimited; the legacy "magic" values
 *   (99, 999, 9999 … all-nines, or negative) are converted to NULL;
 * - new: max_warehouses, max_vans (max_stores now means retail branches only),
 *   trial_days, is_public, founder_price_monthly/yearly, name_key.
 *
 * Values for the approved price list / limits are NOT set here: that is the one-off
 * data migration of ENTI-1.8. New limit columns default to 0 (deny) until then.
 */
return new class extends Migration
{
    /** Existing limit columns => the NOT NULL default they had before this migration. */
    private const LEGACY_LIMITS = [
        'max_users' => 1,
        'max_stores' => 1,
        'max_items' => 50,
        'max_invoices_per_month' => 100,
        'max_storage_mb' => 500,
    ];

    /** Value written back by down() for a NULL (unlimited) limit, so NOT NULL can be restored. */
    private const DOWN_SENTINELS = [
        'max_users' => 999,
        'max_stores' => 99,
        'max_items' => 99999,
        'max_invoices_per_month' => 999999,
        'max_storage_mb' => 999999,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price_monthly', 12, 3)->default(0)->change();
            $table->decimal('price_yearly', 12, 3)->default(0)->change();

            foreach (self::LEGACY_LIMITS as $column => $default) {
                $table->integer($column)->nullable()->default($default)->change();
            }
        });

        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'max_warehouses')) {
                $table->integer('max_warehouses')->nullable()->default(0);
            }
            if (! Schema::hasColumn('plans', 'max_vans')) {
                $table->integer('max_vans')->nullable()->default(0);
            }
            if (! Schema::hasColumn('plans', 'trial_days')) {
                $table->unsignedSmallInteger('trial_days')->default(0);
            }
            if (! Schema::hasColumn('plans', 'is_public')) {
                $table->boolean('is_public')->default(true);
            }
            if (! Schema::hasColumn('plans', 'founder_price_monthly')) {
                $table->decimal('founder_price_monthly', 12, 3)->nullable();
            }
            // TODO(CTO): founder pricing was approved as monthly prices (299/599/999 for 12 months).
            // Whether a founder may also pay yearly at a founder price is undecided; the column stays
            // NULL (= no yearly founder price) until decided, and ENTI-1.7 must treat NULL that way.
            if (! Schema::hasColumn('plans', 'founder_price_yearly')) {
                $table->decimal('founder_price_yearly', 12, 3)->nullable();
            }
            if (! Schema::hasColumn('plans', 'name_key')) {
                $table->string('name_key', 100)->nullable();
            }
        });

        $sentinels = $this->allNinesSentinels();

        foreach (array_keys(self::LEGACY_LIMITS) as $column) {
            DB::table('plans')
                ->where(function ($query) use ($column, $sentinels) {
                    $query->whereIn($column, $sentinels)->orWhere($column, '<', 0);
                })
                ->update([$column => null]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        foreach (self::DOWN_SENTINELS as $column => $sentinel) {
            DB::table('plans')->whereNull($column)->update([$column => $sentinel]);
        }

        Schema::table('plans', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['max_warehouses', 'max_vans', 'trial_days', 'is_public', 'founder_price_monthly', 'founder_price_yearly', 'name_key'],
                static fn (string $column): bool => Schema::hasColumn('plans', $column),
            ));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        // Narrowing back to DECIMAL(10,2) rounds a third decimal (accepted on rollback).
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price_monthly', 10, 2)->default(0)->change();
            $table->decimal('price_yearly', 10, 2)->default(0)->change();

            foreach (self::LEGACY_LIMITS as $column => $default) {
                $table->integer($column)->nullable(false)->default($default)->change();
            }
        });
    }

    /**
     * 99, 999, … 999999999: the "unlimited" stand-ins used by the legacy seeder
     * (enterprise: 999 users, 99 stores, 99999 items, 999999 invoices).
     * TODO(CTO): a super-admin who deliberately typed e.g. 99 stores is also read as
     * unlimited; acceptable because ENTI-1.8 rewrites the approved limits anyway.
     *
     * @return list<int>
     */
    private function allNinesSentinels(): array
    {
        $values = [];
        for ($digits = 2; $digits <= 9; $digits++) {
            $values[] = (int) str_repeat('9', $digits);
        }

        return $values;
    }
};
