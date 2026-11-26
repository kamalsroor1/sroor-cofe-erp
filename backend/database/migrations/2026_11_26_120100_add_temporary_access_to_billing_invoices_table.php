<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL (#232 spec D): the ONE 48-hour temporary-access window an invoice can ever get.
 *
 *  - temporary_access_started_at / _ends_at: opened when a proof classified as a transfer receipt
 *    image was uploaded (ends = start + 48 h). Never reset by a re-upload;
 *  - temporary_access_payment_id: the payment whose receipt opened it (no FK: billing_payments
 *    already references billing_invoices, and the history must outlive a row);
 *  - temporary_access_settled_at / _outcome (`verified` | `rejected` | `voided`): the review
 *    decision that closed it. Unsettled (even after it expired) = the tenant gets no other window;
 *  - temporary_access_reminder_sent_at: the 6-hours-before reminder went out (once per window).
 *
 * Indexes: (tenant_id, temporary_access_settled_at) for "does this tenant hold an unsettled
 * window", temporary_access_ends_at for the reminder scan.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'temporary_access_started_at',
        'temporary_access_ends_at',
        'temporary_access_payment_id',
        'temporary_access_settled_at',
        'temporary_access_outcome',
        'temporary_access_reminder_sent_at',
    ];

    public function up(): void
    {
        if (Schema::hasColumn('billing_invoices', 'temporary_access_started_at')) {
            return;
        }

        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->timestamp('temporary_access_started_at')->nullable();
            $table->timestamp('temporary_access_ends_at')->nullable();
            $table->unsignedBigInteger('temporary_access_payment_id')->nullable();
            $table->timestamp('temporary_access_settled_at')->nullable();
            $table->string('temporary_access_outcome', 20)->nullable();
            $table->timestamp('temporary_access_reminder_sent_at')->nullable();

            $table->index(['tenant_id', 'temporary_access_settled_at'], 'billing_invoices_temp_access_tenant_index');
            $table->index('temporary_access_ends_at', 'billing_invoices_temp_access_ends_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('billing_invoices', 'temporary_access_started_at')) {
            return;
        }

        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->dropIndex('billing_invoices_temp_access_tenant_index');
            $table->dropIndex('billing_invoices_temp_access_ends_index');
            $table->dropColumn(self::COLUMNS);
        });
    }
};
