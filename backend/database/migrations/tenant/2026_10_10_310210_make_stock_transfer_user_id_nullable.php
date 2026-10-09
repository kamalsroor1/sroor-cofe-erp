<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W2 lane 2H (follow-up of 2026_10_10_310200_make_document_user_id_nullable): the stock
 * transfer service used to write `'user_id' => Auth::id() ?? 1`, crediting transfers made
 * by a job/command to user #1 (or failing the FK when user #1 did not exist). It now
 * writes the authenticated user, else the explicit actor the caller passed, else NULL =
 * "system".
 *
 * This makes stock_transfers.user_id nullable. Existing rows and the foreign key
 * (fk_transfers_user, restrict) are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_transfers') || ! Schema::hasColumn('stock_transfers', 'user_id')) {
            return;
        }

        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    /**
     * Restores NOT NULL only when no "system" (NULL) transfers exist yet. With NULL actors
     * the column stays nullable: forcing NOT NULL would fail or need a made-up user id,
     * which is exactly the defect this migration removes.
     */
    public function down(): void
    {
        if (! Schema::hasTable('stock_transfers') || ! Schema::hasColumn('stock_transfers', 'user_id')) {
            return;
        }

        if (DB::table('stock_transfers')->whereNull('user_id')->exists()) {
            return;
        }

        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
