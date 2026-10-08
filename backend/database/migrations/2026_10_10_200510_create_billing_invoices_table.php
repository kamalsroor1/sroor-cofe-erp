<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.6 (central): SaaS subscription invoices, completely separate from the POS
 * `invoices` table that lives in every tenant DB.
 *
 * - `number` = gap-free number from BillingSequenceService (prefix from config, never the brand);
 * - `type` / `status` / `billing_cycle` = string(20) + App\Enums\Billing casts (same choice as
 *   subscriptions, ENTI-1.3); a new invoice is `pending` (awaiting payment);
 * - `lines` = JSON snapshot of the plan / add-on / service lines at issue time (ENTI-3.2);
 * - money DECIMAL(12,3); `tax_rate` is a percentage (14.000 = 14%);
 * - `period_start` / `period_end` = the paid term; (tenant_id, type, period_start) is indexed
 *   for the duplicate-cycle check of ENTI-3.10 (enforced in the action, not as a unique key,
 *   so a voided invoice can be re-issued);
 * - `tenant_id` has NO foreign key on purpose: invoices are the accounting record and must
 *   survive an archived / deleted tenant row (same rule as tenant_lifecycle_events, IDEN-3.2).
 *   A deleted subscription only nulls `subscription_id`; the snapshot keeps the details.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_invoices')) {
            return;
        }

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('tenant_id');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');
            $table->string('billing_cycle', 20)->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->json('lines');
            $table->decimal('subtotal', 12, 3)->default(0);
            $table->decimal('discount', 12, 3)->default(0);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('tax', 12, 3)->default(0);
            $table->decimal('total', 12, 3)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('central_users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('subscription_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type', 'period_start']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
