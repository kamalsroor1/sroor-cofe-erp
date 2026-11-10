<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD-06: opening balances of customers / suppliers as real statement entries.
     * `amount` is signed: positive = the customer owes us / we owe the supplier.
     */
    public function up(): void
    {
        if (Schema::hasTable('opening_balances')) {
            return;
        }

        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->string('party_type', 20);
            $table->unsignedBigInteger('party_id');
            $table->decimal('amount', 12, 3);
            $table->date('balance_date');
            $table->string('notes', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['party_type', 'party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balances');
    }
};
