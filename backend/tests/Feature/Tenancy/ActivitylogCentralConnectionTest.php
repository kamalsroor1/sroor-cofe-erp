<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\ActivityLog;
use App\Models\CentralActivity;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Tests\TenantTestCase;

/**
 * W1 hardening note 12a: spatie/laravel-activitylog is PLATFORM audit, pinned to the
 * central connection, while the tenant business log (ActivityLogService → tenant
 * `activity_logs`) is untouched.
 */
final class ActivitylogCentralConnectionTest extends TenantTestCase
{
    public function test_activity_model_is_the_central_pinned_model(): void
    {
        $this->assertSame(CentralActivity::class, config('activitylog.activity_model'));
        $this->assertSame(CentralActivity::class, ActivitylogServiceProvider::determineActivityModel());
        $this->assertSame(config('tenancy.database.central_connection'), config('activitylog.database_connection'));
        $this->assertSame($this->centralConnectionName(), (new CentralActivity)->getConnectionName());
    }

    public function test_activity_logged_inside_a_tenant_lands_in_the_central_database(): void
    {
        $tenant = $this->createTenant();
        $central = $this->centralConnectionName();

        $this->assertTrue(Schema::connection($central)->hasTable('activity_log'));

        $activityId = $this->inTenant($tenant, function () use ($tenant): int {
            // The default connection is the tenant DB here, and it has no activity_log table.
            $this->assertFalse(Schema::hasTable('activity_log'));

            $activity = activity('platform')
                ->withProperties(['tenant_id' => $tenant->getTenantKey()])
                ->log('w1-12a probe');

            $this->assertInstanceOf(CentralActivity::class, $activity);
            $this->assertSame($this->centralConnectionName(), $activity->getConnectionName());

            return (int) $activity->getKey();
        });

        $row = DB::connection($central)->table('activity_log')->where('id', $activityId)->first();
        $this->assertNotNull($row);
        $this->assertSame('platform', $row->log_name);
        $this->assertSame('w1-12a probe', $row->description);
    }

    public function test_tenant_activity_log_service_still_writes_to_the_tenant_database(): void
    {
        $tenant = $this->createTenant();
        $central = $this->centralConnectionName();

        $this->inTenant($tenant, function (): void {
            $log = app(ActivityLogService::class)->logSystem('w1_12a_probe', 'tenant business log probe');

            $this->assertInstanceOf(ActivityLog::class, $log);
            $this->assertTrue(ActivityLog::query()->whereKey($log->getKey())->exists());
            $this->assertNotSame($this->centralConnectionName(), $log->getConnection()->getName());
        });

        // Neither the platform log nor the legacy central `activity_logs` table received it.
        $this->assertSame(0, DB::connection($central)->table('activity_log')->where('description', 'tenant business log probe')->count());
        $this->assertSame(0, DB::connection($central)->table('activity_logs')->where('description', 'tenant business log probe')->count());
    }
}
