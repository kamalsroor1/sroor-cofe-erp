<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IDEN-1.1 (CENTRAL DB): platform operators (super-admin / support) as a standalone
 * identity, separate from the legacy central `users` table and from every tenant's
 * `users`. Login is by email only (CTO Q-B7). The two-factor columns are the ones
 * laravel/fortify writes (IDEN-1.12); Fortify encrypts the secret and codes itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('central_users')) {
            return;
        }

        Schema::create('central_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true)->index();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_users');
    }
};
