<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IDEN-3.2 (central): append-only history of tenant status changes.
 *
 * `tenant_id` deliberately has NO foreign key (and so no cascade): the history must
 * outlive the tenant row (archive / purge, OPS-9). One row per transition, written in
 * the same central transaction as the `tenants` update (IDEN-3.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_lifecycle_events')) {
            return;
        }

        Schema::create('tenant_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('actor', 20);
            $table->unsignedBigInteger('central_user_id')->nullable();
            $table->string('reason', 30)->nullable();
            $table->string('note', 500)->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at'], 'tenant_lifecycle_events_tenant_created_index');
            $table->index(['to_status', 'created_at'], 'tenant_lifecycle_events_to_status_created_index');
            $table->index('central_user_id', 'tenant_lifecycle_events_central_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_lifecycle_events');
    }
};
