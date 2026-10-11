<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-01 (tenant DB): the branch whose drawer received / paid a voucher. Shift and daily
 * journal cash totals are per store; a voucher that is not tied to an invoice or purchase
 * (debt collection, on-account supplier payment) had no store at all, so every store's
 * drawer counted it. Nullable, no backfill: existing rows keep NULL and are attributed
 * through their invoice / purchase store when they have one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments') || Schema::hasColumn('payments', 'store_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('store_id')->nullable()->index('payments_store_id_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'store_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_store_id_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('store_id');
        });
    }
};
