<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Spatie\Activitylog\Models\Activity;

/**
 * spatie/laravel-activitylog entries for PLATFORM-level audit, stored in the CENTRAL
 * `activity_log` table (config/activitylog.php → activity_model).
 *
 * The package's own model follows config('activitylog.database_connection') or the
 * default connection, which is the tenant DB while a tenant is initialized; pinning the
 * connection here keeps every activity() call on the central database.
 *
 * Not to be confused with App\Models\ActivityLog (tenant `activity_logs`, written by
 * App\Services\ActivityLogService for tenant business audit).
 */
class CentralActivity extends Activity
{
    use UsesCentralConnection;
}
