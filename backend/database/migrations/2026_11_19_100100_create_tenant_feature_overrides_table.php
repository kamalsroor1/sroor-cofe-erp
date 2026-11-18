<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-12 (CENTRAL DB): explicit per-shop feature override. `mode` is `grant` (feature on although
 * the plan lacks it) or `revoke` (feature off although plan / add-on give it). `feature_key` is the
 * canonical catalog key. `expires_at` NULL = never expires. One row per (tenant, feature).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_feature_overrides')) {
            return;
        }

        Schema::create('tenant_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('feature_key', 100);
            $table->string('mode', 10);
            $table->string('reason', 255);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tenant_feature_overrides_tenant_fk')
                ->references('id')->on('tenants')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->unique(['tenant_id', 'feature_key'], 'tenant_feature_overrides_tenant_feature_unique');
            $table->index('expires_at', 'tenant_feature_overrides_expires_index');
            $table->index('created_by', 'tenant_feature_overrides_created_by_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_overrides');
    }
};
