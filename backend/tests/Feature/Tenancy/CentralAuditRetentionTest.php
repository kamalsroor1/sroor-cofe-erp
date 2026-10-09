<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\CentralAuditEvent;
use App\Exceptions\CentralAuditLogImmutableException;
use App\Models\CentralAuditLog;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Tests\TenantTestCase;

/**
 * IDEN-1.15 (CTO W1 Q2): platform audit retention is 2 years.
 *  - `central-audit:prune` deletes ONLY rows strictly older than the retention, from
 *    central_audit_logs and activity_log, never anything younger;
 *  - it deletes through the dedicated `audit_pruner` connection (the only DB user allowed
 *    to DELETE there in production), never through the app connection;
 *  - --days can extend retention but never shorten it;
 *  - it is scheduled daily.
 *
 * The pruner connection shares the central PDO here so the rows of this test's
 * RefreshDatabase transaction are visible to it; production grants are checked by the
 * OPS-1 runbook (SHOW GRANTS), not by PHPUnit.
 */
#[Group('mysql')]
final class CentralAuditRetentionTest extends TenantTestCase
{
    private const NOW = '2026-10-09 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);

        $central = $this->centralConnectionName();
        config(['database.connections.audit_pruner' => config("database.connections.{$central}")]);
        DB::purge('audit_pruner');
        DB::connection('audit_pruner')->setPdo(DB::connection($central)->getPdo());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_retention_is_two_years_on_a_dedicated_connection(): void
    {
        $this->assertSame(730, config('activitylog.central_audit.retention_days'));
        $this->assertSame('audit_pruner', config('activitylog.central_audit.pruner_connection'));
        $this->assertSame(730, config('activitylog.delete_records_older_than_days'));
        $this->assertIsArray(config('database.connections.audit_pruner'));
    }

    public function test_only_rows_older_than_two_years_are_deleted(): void
    {
        $now = Carbon::parse(self::NOW);
        $old = $this->auditRow('too-old', $now->copy()->subDays(730)->subSecond());
        $older = $this->auditRow('much-older', $now->copy()->subYears(5));
        $boundary = $this->auditRow('exactly-two-years', $now->copy()->subDays(730));
        $recent = $this->auditRow('recent', $now->copy()->subDays(729));
        $today = $this->auditRow('today', $now);

        $oldActivity = $this->activityRow('activity-too-old', $now->copy()->subDays(731));
        $recentActivity = $this->activityRow('activity-recent', $now->copy()->subDays(10));

        $this->artisan('central-audit:prune')->assertExitCode(0);

        $this->assertFalse($this->auditExists($old));
        $this->assertFalse($this->auditExists($older));
        $this->assertTrue($this->auditExists($boundary), 'A row exactly at the retention limit is kept (strictly older only).');
        $this->assertTrue($this->auditExists($recent));
        $this->assertTrue($this->auditExists($today));

        $this->assertFalse($this->activityExists($oldActivity));
        $this->assertTrue($this->activityExists($recentActivity));
    }

    public function test_it_deletes_through_the_audit_pruner_connection_only(): void
    {
        $this->auditRow('too-old', Carbon::parse(self::NOW)->subYears(3));

        $deletes = [];
        DB::listen(function (QueryExecuted $query) use (&$deletes): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'delete')) {
                $deletes[] = $query->connectionName;
            }
        });

        $this->artisan('central-audit:prune')->assertExitCode(0);

        $this->assertNotEmpty($deletes);
        $this->assertSame(['audit_pruner'], array_values(array_unique($deletes)), 'Every DELETE runs on the audit_pruner connection.');
    }

    public function test_days_option_can_extend_but_never_shorten_retention(): void
    {
        $now = Carbon::parse(self::NOW);
        $oneYear = $this->auditRow('one-year', $now->copy()->subYear());
        $threeYears = $this->auditRow('three-years', $now->copy()->subYears(3));
        $sixYears = $this->auditRow('six-years', $now->copy()->subYears(6));

        $this->artisan('central-audit:prune', ['--days' => 30])->assertExitCode(0);
        $this->assertTrue($this->auditExists($oneYear), '--days below the configured retention is ignored.');
        $this->assertFalse($this->auditExists($sixYears));
        $this->assertFalse($this->auditExists($threeYears));

        $fourYears = $this->auditRow('four-years', $now->copy()->subYears(4));
        $this->artisan('central-audit:prune', ['--days' => 2000])->assertExitCode(0);
        $this->assertTrue($this->auditExists($fourYears), '--days above the retention keeps more.');
    }

    public function test_it_prunes_in_chunks_until_nothing_old_is_left(): void
    {
        config(['activitylog.central_audit.prune_chunk' => 2]);
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->auditRow("old-{$i}", Carbon::parse(self::NOW)->subYears(3)->addMinutes($i));
        }
        $kept = $this->auditRow('kept', Carbon::parse(self::NOW)->subDay());

        $this->artisan('central-audit:prune')->assertExitCode(0);

        foreach ($ids as $id) {
            $this->assertFalse($this->auditExists($id));
        }
        $this->assertTrue($this->auditExists($kept));
    }

    public function test_the_app_model_still_refuses_to_delete_audit_rows(): void
    {
        $id = $this->auditRow('protected', Carbon::parse(self::NOW)->subYears(3));

        $this->expectException(CentralAuditLogImmutableException::class);

        CentralAuditLog::query()->whereKey($id)->delete();
    }

    public function test_the_prune_is_scheduled_daily(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (Event $event): bool => str_contains((string) $event->command, 'central-audit:prune'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('15 3 * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    private function auditRow(string $description, Carbon $at): int
    {
        return (int) DB::connection($this->centralConnectionName())->table('central_audit_logs')->insertGetId([
            'event' => CentralAuditEvent::LoginSucceeded->value,
            'description' => $description,
            'created_at' => $at,
        ]);
    }

    private function activityRow(string $description, Carbon $at): int
    {
        return (int) DB::connection($this->centralConnectionName())->table((string) config('activitylog.table_name', 'activity_log'))->insertGetId([
            'log_name' => 'platform',
            'description' => $description,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function auditExists(int $id): bool
    {
        return DB::connection($this->centralConnectionName())->table('central_audit_logs')->where('id', $id)->exists();
    }

    private function activityExists(int $id): bool
    {
        return DB::connection($this->centralConnectionName())->table((new Activity)->getTable())->where('id', $id)->exists();
    }
}
