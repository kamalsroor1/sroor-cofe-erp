<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W2 lane 2B (defect): services used to write `'user_id' => Auth::id() ?? 1`, so a job or
 * command without a logged-in user credited every document to user #1 (or failed the
 * FK when user #1 did not exist). They now write the authenticated user, else the
 * explicit actor the caller passed, else NULL = "system".
 *
 * This makes `user_id` nullable on the document tables that were NOT NULL. Existing rows
 * and the foreign keys (restrict / cascade) are unchanged; audit_logs and expenses were
 * already nullable.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'invoices',
        'payments',
        'purchases',
        'returns',
        'stock_movements',
        'stock_deposits',
        'cash_shifts',
        'treasury_transfers',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'user_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }
    }

    /**
     * Restores NOT NULL only on tables that have no "system" (NULL) rows yet. A table that
     * already holds NULL actors stays nullable: forcing NOT NULL would either fail or need
     * a made-up user id, which is exactly the defect this migration removes.
     */
    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'user_id')) {
                continue;
            }

            if (DB::table($tableName)->whereNull('user_id')->exists()) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
            });
        }
    }
};
