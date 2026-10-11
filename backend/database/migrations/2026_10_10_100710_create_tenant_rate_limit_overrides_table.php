<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IDEN-4.6 ext (CENTRAL DB, CTO W1 Q1): temporary raise of one tenant's rate limits by a
 * platform operator. Every row expires (`expires_at`); a newer raise revokes the previous
 * one (`revoked_at`). Null limit columns keep the configured default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_rate_limit_overrides')) {
            return;
        }

        Schema::create('tenant_rate_limit_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedInteger('tenant_login_per_ip_per_minute')->nullable();
            $table->unsignedInteger('tenant_login_per_login_per_minute')->nullable();
            $table->unsignedInteger('tenant_resolve_per_minute')->nullable();
            $table->string('reason', 500);
            $table->unsignedBigInteger('central_user_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tenant_rate_limit_overrides_tenant_fk')
                ->references('id')->on('tenants')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->index(['tenant_id', 'expires_at'], 'tenant_rate_limit_overrides_tenant_expires_index');
            $table->index('expires_at', 'tenant_rate_limit_overrides_expires_index');
            $table->index('central_user_id', 'tenant_rate_limit_overrides_central_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_rate_limit_overrides');
    }
};
