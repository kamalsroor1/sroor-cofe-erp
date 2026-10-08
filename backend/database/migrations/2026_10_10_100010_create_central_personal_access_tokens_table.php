<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IDEN-1.1 (CENTRAL DB): Sanctum-shaped token table for CentralUser only, so an
 * operator token can never be resolved by the tenant `auth:sanctum` stack (which
 * reads `personal_access_tokens`) and a tenant token can never be resolved here.
 *
 * `expires_at` is NOT NULL on purpose: a central token without an expiry must fail
 * at insert time (CentralUser::createToken() always supplies one).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('central_personal_access_tokens')) {
            return;
        }

        Schema::create('central_personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_personal_access_tokens');
    }
};
