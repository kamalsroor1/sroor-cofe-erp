<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-12 (CENTRAL DB): one shop gets a limit different from its plan. `value` NULL = unlimited for
 * this shop; `expires_at` NULL = never expires (an expired row is ignored at read time, no cron).
 * One row per (tenant, resource).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_limit_overrides')) {
            return;
        }

        Schema::create('tenant_limit_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('resource', 40);
            $table->unsignedInteger('value')->nullable();
            $table->string('reason', 255);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tenant_limit_overrides_tenant_fk')
                ->references('id')->on('tenants')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->unique(['tenant_id', 'resource'], 'tenant_limit_overrides_tenant_resource_unique');
            $table->index('expires_at', 'tenant_limit_overrides_expires_index');
            $table->index('created_by', 'tenant_limit_overrides_created_by_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_limit_overrides');
    }
};
