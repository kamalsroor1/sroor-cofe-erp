<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL `activity_log` table (PKG-2 range 0500xx) for spatie/laravel-activitylog v4,
 * used for platform-level audit through App\Models\CentralActivity.
 *
 * Central only: the tenant business log is the separate `activity_logs` table
 * (database/migrations/tenant, App\Services\ActivityLogService).
 *
 * Schema = the package stubs (create + event + batch_uuid) with one change: subject_id is
 * a string, because platform subjects include tenants, whose keys are strings.
 * Guarded with hasTable because the legacy test base runs the central and tenant
 * folders on one sqlite DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = $this->tableName();

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'activity_log_causer_index');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable()->index();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'activity_log_subject_index');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName());
    }

    private function tableName(): string
    {
        return (string) config('activitylog.table_name', 'activity_log');
    }
};
