<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.10 (central): the lock sentinel of the credits ledger, one row per
 * (tenant_id, addon_key).
 *
 * CreditBalanceService takes SELECT … FOR UPDATE on this row before it reads Σ delta and
 * appends a movement, so two concurrent debits of the same balance serialise and can never
 * take it below zero. The lock is on the sentinel, not on ledger rows: locking existing
 * ledger rows would not stop a phantom insert. The row carries no balance and is never
 * updated; it is created (INSERT IGNORE) on the first movement.
 *
 * Kept out of the ledger itself so the ledger only contains real movements.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_credit_accounts')) {
            return;
        }

        Schema::create('tenant_credit_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('addon_key', 100);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'addon_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_credit_accounts');
    }
};
