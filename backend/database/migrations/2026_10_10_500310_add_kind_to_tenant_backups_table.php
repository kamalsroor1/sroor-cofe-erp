<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-9 (CENTRAL DB): why each backup archive was taken (App\Enums\TenantBackupKind).
 *
 * Every existing row is a nightly / deploy backup, hence the `scheduled` default. The
 * archive guard and the purge command look up "the newest verified final / pre_purge
 * backup of this tenant", hence the (tenant_id, kind, verified_at) index. Retention never
 * deletes `final` / `pre_purge` archives (CTO Q6).
 */
return new class extends Migration
{
    private const INDEX = 'tenant_backups_tenant_kind_verified_index';

    public function up(): void
    {
        if (! Schema::hasTable('tenant_backups')) {
            return;
        }

        if (! Schema::hasColumn('tenant_backups', 'kind')) {
            Schema::table('tenant_backups', function (Blueprint $table) {
                $table->string('kind', 20)->default('scheduled');
                $table->index(['tenant_id', 'kind', 'verified_at'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_backups')) {
            return;
        }

        if (Schema::hasIndex('tenant_backups', self::INDEX)) {
            Schema::table('tenant_backups', function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }

        if (Schema::hasColumn('tenant_backups', 'kind')) {
            Schema::table('tenant_backups', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
    }
};
