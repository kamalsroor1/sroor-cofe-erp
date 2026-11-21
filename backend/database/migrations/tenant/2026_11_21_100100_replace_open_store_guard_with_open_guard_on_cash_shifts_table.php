<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SHIFT-01 (tenant DB): the database guard behind "one open shift" now follows the branch's shift
 * mode.
 *
 * `open_guard` (VARCHAR, UNIQUE) replaces SHOP-06's `open_store_guard` (integer store id). While a
 * shift is open it holds a key built by OpenShiftResolver::guardKey(): `store:{id}` for a shared
 * shift (one open shift per branch, as before) or `store:{id}:user:{id}` for a per-cashier shift
 * (one open shift per cashier and branch). It is NULL once the shift is closed or deleted, and NULLs
 * never collide, so any number of closed shifts coexist. A plain UNIQUE index on a nullable column
 * behaves the same on MySQL, MariaDB and sqlite (no partial / functional index needed).
 *
 * `shift_mode` records the mode the shift was opened in ('shared' for every existing shift: they
 * were all opened under the one-per-branch rule). A per-cashier shift's legacy-window attribution is
 * limited to its opener's rows (ShiftAttribution::scopeToShift).
 *
 * Backfill: an open shift holding `open_store_guard` = N gets `store:N`; the rest stay NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_shifts')) {
            return;
        }

        if (! Schema::hasColumn('cash_shifts', 'shift_mode')) {
            Schema::table('cash_shifts', function (Blueprint $table): void {
                $table->string('shift_mode', 16)->default('shared');
            });
        }

        if (Schema::hasColumn('cash_shifts', 'open_guard')) {
            return;
        }

        Schema::table('cash_shifts', function (Blueprint $table): void {
            $table->string('open_guard', 64)->nullable();
        });

        if (Schema::hasColumn('cash_shifts', 'open_store_guard')) {
            DB::table('cash_shifts')
                ->whereNotNull('open_store_guard')
                ->orderBy('id')
                ->get(['id', 'open_store_guard'])
                ->each(static function (object $row): void {
                    DB::table('cash_shifts')->where('id', $row->id)->update(['open_guard' => 'store:'.(int) $row->open_store_guard]);
                });
        } else {
            // The SHOP-06 column never existed here: the newest open shift of each branch takes the key.
            DB::table('cash_shifts')
                ->where('status', 'open')
                ->whereNull('deleted_at')
                ->selectRaw('MAX(id) as id, store_id')
                ->groupBy('store_id')
                ->get()
                ->each(static function (object $row): void {
                    DB::table('cash_shifts')->where('id', $row->id)->update(['open_guard' => 'store:'.(int) ($row->store_id ?? 0)]);
                });
        }

        Schema::table('cash_shifts', function (Blueprint $table): void {
            $table->unique('open_guard', 'cash_shifts_open_guard_unique');
        });

        if (Schema::hasColumn('cash_shifts', 'open_store_guard')) {
            Schema::table('cash_shifts', function (Blueprint $table): void {
                $table->dropUnique('cash_shifts_open_store_guard_unique');
            });

            Schema::table('cash_shifts', function (Blueprint $table): void {
                $table->dropColumn('open_store_guard');
            });
        }
    }

    /**
     * Back to one open shift per branch: the newest open shift of each branch takes
     * `open_store_guard` again (an older per-cashier shift of the same branch keeps NULL and can
     * still be closed normally), then the new columns go.
     */
    public function down(): void
    {
        if (! Schema::hasTable('cash_shifts')) {
            return;
        }

        if (Schema::hasColumn('cash_shifts', 'open_guard')) {
            if (! Schema::hasColumn('cash_shifts', 'open_store_guard')) {
                Schema::table('cash_shifts', function (Blueprint $table): void {
                    $table->unsignedBigInteger('open_store_guard')->nullable()->after('status');
                });

                DB::table('cash_shifts')
                    ->where('status', 'open')
                    ->whereNull('deleted_at')
                    ->selectRaw('MAX(id) as id, store_id')
                    ->groupBy('store_id')
                    ->get()
                    ->each(static function (object $row): void {
                        DB::table('cash_shifts')->where('id', $row->id)->update(['open_store_guard' => (int) ($row->store_id ?? 0)]);
                    });

                Schema::table('cash_shifts', function (Blueprint $table): void {
                    $table->unique('open_store_guard', 'cash_shifts_open_store_guard_unique');
                });
            }

            Schema::table('cash_shifts', function (Blueprint $table): void {
                $table->dropUnique('cash_shifts_open_guard_unique');
            });

            Schema::table('cash_shifts', function (Blueprint $table): void {
                $table->dropColumn('open_guard');
            });
        }

        if (Schema::hasColumn('cash_shifts', 'shift_mode')) {
            Schema::table('cash_shifts', function (Blueprint $table): void {
                $table->dropColumn('shift_mode');
            });
        }
    }
};
