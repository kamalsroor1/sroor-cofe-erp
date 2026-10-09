<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IDEN-3.2 (central): lifecycle-grade `tenants`.
 *
 * - `status` ENUM(active, trial, suspended, cancelled) -> string(20), so it can hold
 *   past_due / read_only / archived (allowed values = App\Enums\TenantStatus);
 * - lifecycle facts as REAL columns (stancl custom columns, never inside `data`):
 *   status_changed_at, grace_ends_at, trial_extended_at, read_only_since,
 *   suspension_reason (App\Support\Tenancy\TenantSuspensionReason),
 *   status_before_archive (archive is reversible, CTO W1 Q2);
 * - indexes for the daily sweep / reminders and the super-admin status filter.
 *
 * Existing rows are NOT backfilled here (status_changed_at stays null, so no automatic
 * clock runs on them): that is OPS-12 (`tenants:backfill-lifecycle`).
 */
return new class extends Migration
{
    private const NEW_COLUMNS = [
        'status_changed_at',
        'grace_ends_at',
        'trial_extended_at',
        'read_only_since',
        'suspension_reason',
        'status_before_archive',
    ];

    private const LEGACY_STATUSES = ['active', 'trial', 'suspended', 'cancelled'];

    /**
     * down(): new value => closest legacy value, applied BEFORE the column is narrowed
     * back to the old ENUM (MySQL strict mode / the sqlite CHECK would reject it).
     * Anything else outside the legacy list falls back to `suspended` (never grants access).
     */
    private const DOWN_STATUS_MAP = [
        'past_due' => 'active',      // was still working in its grace period
        'read_only' => 'suspended',  // no writes
        'archived' => 'cancelled',   // data kept, account closed
    ];

    private const DOWN_FALLBACK_STATUS = 'suspended';

    private const INDEX_STATUS_CHANGED = 'tenants_status_status_changed_at_index';

    private const INDEX_TRIAL_ENDS = 'tenants_trial_ends_at_index';

    private const INDEX_SUBSCRIPTION_ENDS = 'tenants_subscription_ends_at_index';

    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('status', 20)->default('trial')->change();
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'status_changed_at')) {
                $table->timestamp('status_changed_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('tenants', 'grace_ends_at')) {
                $table->timestamp('grace_ends_at')->nullable()->after('subscription_ends_at');
            }
            if (! Schema::hasColumn('tenants', 'trial_extended_at')) {
                $table->timestamp('trial_extended_at')->nullable()->after('trial_ends_at');
            }
            if (! Schema::hasColumn('tenants', 'read_only_since')) {
                $table->timestamp('read_only_since')->nullable()->after('status_changed_at');
            }
            if (! Schema::hasColumn('tenants', 'suspension_reason')) {
                $table->string('suspension_reason', 30)->nullable()->after('read_only_since');
            }
            if (! Schema::hasColumn('tenants', 'status_before_archive')) {
                $table->string('status_before_archive', 20)->nullable()->after('suspension_reason');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasIndex('tenants', self::INDEX_STATUS_CHANGED)) {
                $table->index(['status', 'status_changed_at'], self::INDEX_STATUS_CHANGED);
            }
            if (! Schema::hasIndex('tenants', self::INDEX_TRIAL_ENDS)) {
                $table->index('trial_ends_at', self::INDEX_TRIAL_ENDS);
            }
            if (! Schema::hasIndex('tenants', self::INDEX_SUBSCRIPTION_ENDS)) {
                $table->index('subscription_ends_at', self::INDEX_SUBSCRIPTION_ENDS);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        foreach (self::DOWN_STATUS_MAP as $new => $legacy) {
            DB::table('tenants')->where('status', $new)->update(['status' => $legacy]);
        }
        DB::table('tenants')
            ->where(static function ($query): void {
                $query->whereNull('status')->orWhereNotIn('status', self::LEGACY_STATUSES);
            })
            ->update(['status' => self::DOWN_FALLBACK_STATUS]);

        Schema::table('tenants', function (Blueprint $table) {
            foreach ([self::INDEX_STATUS_CHANGED, self::INDEX_TRIAL_ENDS, self::INDEX_SUBSCRIPTION_ENDS] as $index) {
                if (Schema::hasIndex('tenants', $index)) {
                    $table->dropIndex($index);
                }
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            $drop = array_values(array_filter(
                self::NEW_COLUMNS,
                static fn (string $column): bool => Schema::hasColumn('tenants', $column),
            ));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', self::LEGACY_STATUSES)->default('trial')->change();
        });
    }
};
