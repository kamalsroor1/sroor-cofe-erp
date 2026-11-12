<?php

declare(strict_types=1);

use App\Support\Database\OnlineMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-10: a sales return can refund cash from the drawer or only reduce the customer's balance.
 *
 * - returns.refund_method: 'cash' | 'balance'. NULL for purchase returns and for legacy rows,
 *   which are read as 'balance' (they never booked a cash movement).
 * - payments.return_id: the return a refund voucher (negative cash payment) belongs to, so
 *   deleting the return can reverse exactly its own voucher.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('returns') && ! Schema::hasColumn('returns', 'refund_method')) {
            Schema::table('returns', function (Blueprint $table): void {
                $table->string('refund_method', 20)->nullable();
            });
        }

        // MIG-01: online column + FK (no table copy; the column is all NULL). Re-runnable.
        OnlineMigration::addNullableForeignKey('payments', 'return_id', 'returns', 'set null');
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'return_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('return_id');
            });
        }

        if (Schema::hasTable('returns') && Schema::hasColumn('returns', 'refund_method')) {
            Schema::table('returns', function (Blueprint $table): void {
                $table->dropColumn('refund_method');
            });
        }
    }
};
