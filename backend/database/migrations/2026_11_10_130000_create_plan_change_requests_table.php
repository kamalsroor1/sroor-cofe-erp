<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision (PRD-04 follow-up, central): a plan change or an add-on purchase goes through
 * support. The shop files a request, the console shows it, and the admin issues a manual
 * invoice (the DIFFERENCE for a plan change, the add-on lines for an add-on) which is paid like
 * any other billing invoice. No proration, no self-service switching.
 *
 * - `kind` = plan_change | addon, `status` = pending | invoiced | rejected | cancelled (string(20)
 *   + enum casts, same choice as the rest of billing);
 * - `current_plan_id` is a snapshot of the plan at request time; the plan / add-on columns are
 *   plain FKs that nullOnDelete so catalog clean-ups never destroy the history;
 * - `requested_by_user_id` is a TENANT-side user id (different database): no foreign key;
 * - `billing_invoice_id` is set when the admin invoices the request.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_change_requests')) {
            return;
        }

        Schema::create('plan_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->foreignId('current_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('requested_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('addon_id')->nullable()->constrained('addons')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('billing_cycle', 20);
            $table->string('notes', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('billing_invoice_id')->nullable()->constrained('billing_invoices')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable();
            $table->string('requested_by_name', 150)->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('central_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'kind', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_change_requests');
    }
};
