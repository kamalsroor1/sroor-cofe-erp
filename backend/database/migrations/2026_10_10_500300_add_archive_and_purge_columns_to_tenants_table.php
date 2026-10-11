<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-9 (CENTRAL DB): asynchronous archive with a verified final backup, and the explicit
 * CLI purge (tombstone row).
 *
 * - archive_state          App\Enums\TenantArchiveState (queued / running / failed), null = idle;
 * - archive_requested_at   when the operator asked for the archive;
 * - archive_error_code     why the last archive attempt failed (a code, never a message);
 * - archived_at            when the tenant became `archived` (anchors purge eligibility);
 * - final_backup_id        the tenant_backups row proven before the archive. No FK on purpose:
 *                          the ledger and the tenant row have independent lifetimes;
 * - purge_started_at / purged_at / purged_by  the `tenants:purge` tombstone (the row is
 *                          kept forever: billing, audit and lifecycle history stay, the slug
 *                          is never reused).
 *
 * Real columns (stancl custom columns, Tenant::getCustomColumns()), never inside `data`.
 * Existing archived tenants get archived_at = status_changed_at (their best known archive time).
 */
return new class extends Migration
{
    private const PURGED_INDEX = 'tenants_purged_at_index';

    private const ARCHIVE_STATE_INDEX = 'tenants_archive_state_index';

    private const COLUMNS = [
        'archive_state',
        'archive_requested_at',
        'archive_error_code',
        'archived_at',
        'final_backup_id',
        'purge_started_at',
        'purged_at',
        'purged_by',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'archive_state')) {
                $table->string('archive_state', 20)->nullable();
                $table->index('archive_state', self::ARCHIVE_STATE_INDEX);
            }
            if (! Schema::hasColumn('tenants', 'archive_requested_at')) {
                $table->timestamp('archive_requested_at')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'archive_error_code')) {
                $table->string('archive_error_code', 64)->nullable();
            }
            if (! Schema::hasColumn('tenants', 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'final_backup_id')) {
                $table->unsignedBigInteger('final_backup_id')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'purge_started_at')) {
                $table->timestamp('purge_started_at')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'purged_at')) {
                $table->timestamp('purged_at')->nullable();
                $table->index('purged_at', self::PURGED_INDEX);
            }
            if (! Schema::hasColumn('tenants', 'purged_by')) {
                $table->string('purged_by', 100)->nullable();
            }
        });

        if (Schema::hasColumn('tenants', 'status') && Schema::hasColumn('tenants', 'status_changed_at')) {
            DB::table('tenants')
                ->where('status', 'archived')
                ->whereNull('archived_at')
                ->update(['archived_at' => DB::raw('status_changed_at')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        foreach ([self::PURGED_INDEX, self::ARCHIVE_STATE_INDEX] as $index) {
            if (Schema::hasIndex('tenants', $index)) {
                Schema::table('tenants', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }

        $existing = array_values(array_filter(
            self::COLUMNS,
            static fn (string $column): bool => Schema::hasColumn('tenants', $column),
        ));

        if ($existing !== []) {
            Schema::table('tenants', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
