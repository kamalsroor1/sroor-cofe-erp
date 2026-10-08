<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.6 (central): gap-free counters for SaaS billing documents.
 *
 * One row per (key, period), e.g. ('billing_invoice', '2026'). The row is read with
 * lockForUpdate() and incremented by App\Services\Billing\BillingSequenceService inside
 * the caller's transaction, so a rolled-back invoice gives its number back.
 * `period` is NOT NULL ('' = no period): a NULL would bypass the unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_sequences')) {
            return;
        }

        Schema::create('billing_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->string('period', 10)->default('');
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_sequences');
    }
};
