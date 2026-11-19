<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOB-05 (central): public "delete my account and data" requests (Play Store requirement).
 * Stored centrally because the requester may not be able to sign in; a platform operator
 * verifies the identity and carries the deletion out manually, then marks the row handled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_deletion_requests')) {
            return;
        }

        Schema::create('account_deletion_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('contact_name', 120);
            $table->string('phone', 20);
            $table->string('email', 255)->nullable();
            $table->string('shop_code', 63)->nullable();
            $table->string('reason', 1000)->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('status', 20)->default('pending');
            // central_users.id; no FK so deleting an operator never touches the history.
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->string('handled_note', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('phone');
            $table->index('shop_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
