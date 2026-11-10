<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD-05 (central): assisted-signup requests from the public landing page. A platform
 * operator reviews each one and approves it (provisioning a tenant) or rejects it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signup_requests')) {
            return;
        }

        Schema::create('signup_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('business_name', 150);
            $table->string('contact_name', 120);
            $table->string('phone', 20);
            $table->string('email', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('business_type', 20);
            $table->string('plan_code', 50)->nullable();
            $table->string('preferred_subdomain', 30)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('rejection_reason', 500)->nullable();
            // central_users.id; no FK so deleting an operator never touches the history.
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('phone');
            $table->index('reviewed_by');
            $table->index('tenant_id');
            $table->index('plan_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_requests');
    }
};
