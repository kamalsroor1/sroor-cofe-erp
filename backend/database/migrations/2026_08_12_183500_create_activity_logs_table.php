<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('module', 50)->index(); // sales, inventory, purchases, shifts, expenses, contacts, auth, system
            $table->string('action', 50)->index(); // created, updated, cancelled, deleted, restored, login, shift_opened, shift_closed, payment
            $table->nullableMorphs('subject');     // subject_type, subject_id
            $table->string('description', 500);   // Arabic human-readable summary
            $table->json('properties')->nullable(); // old/new values, metadata
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['module', 'action']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
