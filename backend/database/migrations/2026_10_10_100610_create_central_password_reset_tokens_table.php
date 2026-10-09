<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IDEN-1.12 (CENTRAL DB): token store of the `central_users` password broker
 * (config/auth.php → passwords.central_users). Kept apart from the legacy
 * `password_reset_tokens` so an operator reset and a tenant/legacy reset never share rows.
 * Laravel stores only a hash of the token; emails are lowercase (CentralUser mutator).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('central_password_reset_tokens')) {
            return;
        }

        Schema::create('central_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_password_reset_tokens');
    }
};
