<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SELL-02 (tenant): Laravel's standard database-notifications table, so in-app notifications
 * (first user: the subscription reminders of `tenants:send-lifecycle-reminders`) are stored in
 * the shop's own database, per user (App\Models\User is Notifiable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_index');
        });
    }

    /**
     * Deliberately a no-op: up() skips when a `notifications` table already exists (e.g. created
     * by another feature or by hand), so this migration cannot know it owns the table, and a
     * rollback must never drop a shop's notifications it did not create. Drop it by hand only
     * after confirming nothing else uses it.
     */
    public function down(): void
    {
        // Intentionally empty (see above).
    }
};
