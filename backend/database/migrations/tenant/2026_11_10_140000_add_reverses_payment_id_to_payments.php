<?php

declare(strict_types=1);

use App\Support\Database\OnlineMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-03: cancelling an invoice books a negative PAY-REF voucher for each payment it had.
 * payments.reverses_payment_id points the reversal at the voucher it undoes, so a payment is
 * reversed at most once and reports can tell reversed sales from legacy un-reversed ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MIG-01: online column + FK (no table copy; the column is all NULL). Re-runnable.
        OnlineMigration::addNullableForeignKey('payments', 'reverses_payment_id', 'payments', 'set null');
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'reverses_payment_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('reverses_payment_id');
            });
        }
    }
};
