<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SHOP-03 (tenant DB): a customer's credit limit. NULL = no limit (every existing customer keeps
 * selling on credit exactly as before). A plain nullable DECIMAL(12,3) with no default, so the
 * column is added in place; no backfill. Guarded, so running up() or down() twice is a no-op.
 */
return new class extends Migration
{
    private const TABLE = 'customers';

    private const COLUMN = 'credit_limit';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->decimal(self::COLUMN, 12, 3)->nullable()->after('current_balance');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropColumn(self::COLUMN);
        });
    }
};
