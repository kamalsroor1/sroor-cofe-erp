<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SELL-02 (central): one row per subscription reminder sent by `tenants:send-lifecycle-reminders`.
 *
 * UNIQUE (tenant_id, kind, target_date) makes every reminder once-only, even when the command
 * runs twice the same day or two schedulers race: `kind` is e.g. `trial.d3` / `subscription.grace`,
 * `target_date` the calendar date (app timezone) the trial / paid period ends, so a new period
 * (extension, renewal) gets its own reminders.
 *
 * `tenant_notified_at` / `operator_notified_at` are set only after the tenant notification was
 * written / the operator Telegram message was accepted, so a failed delivery is retried by the
 * next run of the same day. `tenant_id` has no foreign key (like tenant_lifecycle_events): the
 * history outlives the tenant row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_lifecycle_reminders')) {
            return;
        }

        Schema::create('tenant_lifecycle_reminders', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('kind', 40);
            $table->date('target_date');
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamp('tenant_notified_at')->nullable();
            $table->timestamp('operator_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'kind', 'target_date'], 'tenant_lifecycle_reminders_unique');
            $table->index('target_date', 'tenant_lifecycle_reminders_target_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_lifecycle_reminders');
    }
};
