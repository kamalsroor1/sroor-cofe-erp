<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.6 (central): payments against SaaS invoices (manual receipts now, Paymob/Fawry later).
 *
 * - `method` / `gateway` / `status` = string + App\Enums\Billing casts; a new payment is
 *   `pending` (awaiting super-admin review, ENTI-3.4) through the `manual` gateway;
 * - `gateway_reference` = UNIQUE idempotency key (NULLs allowed: many manual receipts have
 *   none); ActivateSubscriptionAction relies on it so a payment never activates twice;
 * - `proof_path` = receipt location on the private central disk (server-generated name,
 *   ENTI-3.3 / PKG-2); `raw_payload` = gateway webhook body, written as received;
 * - `submitted_by` = tenant user id (lives in the tenant DB, so no FK);
 *   `verified_by` = central_users id;
 * - an invoice that has payments cannot be deleted (restrict); `tenant_id` has no FK so the
 *   accounting record survives the tenant row (see billing_invoices).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_payments')) {
            return;
        }

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_invoice_id')->constrained('billing_invoices')->restrictOnDelete();
            $table->string('tenant_id');
            $table->decimal('amount', 12, 3);
            $table->string('currency', 3)->default('EGP');
            $table->string('method', 30);
            $table->string('gateway', 20)->default('manual');
            $table->string('gateway_reference', 191)->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->string('proof_path')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('central_users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('billing_invoice_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};
