<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-2 (central): queued tenant provisioning state on `tenants`.
 *
 * - provisioning_status   App\Enums\TenantProvisioningStatus, default `ready` so every
 *                         tenant that existed before OPS-2 keeps being served;
 * - provisioning_attempts job attempts so far (all retries included);
 * - provisioning_error_code App\Support\Tenancy\ProvisioningErrorCode of the last failure
 *                         (a code only, never the raw exception);
 * - provisioning_started_at / provisioned_at.
 *
 * Real columns (stancl custom columns, Tenant::getCustomColumns()), never inside `data`.
 * Indexed on the status for the super-admin "stuck / failed provisioning" filter.
 */
return new class extends Migration
{
    private const INDEX = 'tenants_provisioning_status_index';

    private const COLUMNS = [
        'provisioning_status',
        'provisioning_attempts',
        'provisioning_error_code',
        'provisioning_started_at',
        'provisioned_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'provisioning_status')) {
                $table->string('provisioning_status', 20)->default('ready')->after('status');
                $table->index('provisioning_status', self::INDEX);
            }
            if (! Schema::hasColumn('tenants', 'provisioning_attempts')) {
                $table->unsignedSmallInteger('provisioning_attempts')->default(0)->after('provisioning_status');
            }
            if (! Schema::hasColumn('tenants', 'provisioning_error_code')) {
                $table->string('provisioning_error_code', 64)->nullable()->after('provisioning_attempts');
            }
            if (! Schema::hasColumn('tenants', 'provisioning_started_at')) {
                $table->timestamp('provisioning_started_at')->nullable()->after('provisioning_error_code');
            }
            if (! Schema::hasColumn('tenants', 'provisioned_at')) {
                $table->timestamp('provisioned_at')->nullable()->after('provisioning_started_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        if (Schema::hasIndex('tenants', self::INDEX)) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
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
