<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL (#232 spec E / UI §4.2): the super-admin panel's notification items (the bell).
 *
 * platform_notifications: one row per event for the whole operator team (a shared inbox), e.g.
 * `billing_receipt_uploaded`, `billing_temporary_access_reminder`. `data` holds the facts the bell
 * renders (tenant name, amount, method, access kind, window end); `dedupe_key` (unique) makes a
 * retried writer idempotent. Ids of tenants / payments / invoices are plain columns without FKs:
 * the panel history outlives those rows.
 *
 * platform_notification_reads: per-operator read marks (unique notification + central user), so
 * each operator has their own unread count.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_notifications')) {
            Schema::create('platform_notifications', function (Blueprint $table): void {
                $table->id();
                $table->string('type', 60);
                $table->string('tenant_id')->nullable();
                $table->unsignedBigInteger('billing_invoice_id')->nullable();
                $table->unsignedBigInteger('billing_payment_id')->nullable();
                $table->json('data')->nullable();
                $table->string('dedupe_key', 120)->nullable()->unique();
                $table->timestamps();

                $table->index('created_at', 'platform_notifications_created_at_index');
                $table->index('tenant_id', 'platform_notifications_tenant_id_index');
            });
        }

        if (! Schema::hasTable('platform_notification_reads')) {
            Schema::create('platform_notification_reads', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('platform_notification_id')->constrained('platform_notifications')->cascadeOnDelete();
                $table->foreignId('central_user_id')->constrained('central_users')->cascadeOnDelete();
                $table->timestamp('read_at');

                $table->unique(['platform_notification_id', 'central_user_id'], 'platform_notification_reads_unique');
                $table->index('central_user_id', 'platform_notification_reads_user_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_notification_reads');
        Schema::dropIfExists('platform_notifications');
    }
};
