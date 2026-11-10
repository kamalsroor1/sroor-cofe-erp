<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-09: race-free document numbers. One counter row per number scope (for example
 * "INV-MAIN-20261110" or "PAY-INV-20261110"); App\Services\DocumentNumberService locks the
 * row (SELECT ... FOR UPDATE) and increments it inside the caller's transaction, so two
 * concurrent documents can never be handed the same number.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_sequences')) {
            return;
        }

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 80)->unique();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
