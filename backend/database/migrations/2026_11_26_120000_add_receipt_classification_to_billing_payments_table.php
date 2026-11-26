<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL (#232 spec D): what the TransferReceiptClassifier concluded about the uploaded proof
 * (`transfer_receipt_image` | `not_receipt`) and why (a short reason code). It only gates the
 * 48-hour temporary access; amount, recipient and time are still checked by hand at Verify.
 * Nullable: payments recorded by an operator and rows from before this column have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('billing_payments', 'receipt_classification')) {
                $table->string('receipt_classification', 32)->nullable()->after('proof_path');
            }
            if (! Schema::hasColumn('billing_payments', 'receipt_classification_reason')) {
                $table->string('receipt_classification_reason', 40)->nullable()->after('receipt_classification');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['receipt_classification', 'receipt_classification_reason'],
            static fn (string $column): bool => Schema::hasColumn('billing_payments', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('billing_payments', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
