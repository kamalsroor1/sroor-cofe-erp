<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\SendShiftDiscrepancyAlertJob;
use App\Models\CashShift;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TenantTestCase;

/**
 * PERF-01: closing a shift with a cash difference never waits for Telegram. The alert is a
 * queued job dispatched after the close commits, so the close succeeds even when Telegram is
 * down, and a rolled-back close never sends an alert.
 */
final class ShiftCloseTelegramApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '12345',
        ]);

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
    }

    public function test_a_close_with_a_difference_queues_the_alert_instead_of_calling_telegram(): void
    {
        Queue::fake();
        Http::fake();
        $shiftId = $this->openShift('SHF-PERF-01', '200.000');

        $this->closeShift($shiftId, '250.000')->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.cash_difference', '50.000');

        Queue::assertPushed(SendShiftDiscrepancyAlertJob::class, fn (SendShiftDiscrepancyAlertJob $job): bool => $job->shiftId === $shiftId);
        Http::assertNothingSent();
    }

    public function test_a_matched_close_queues_no_alert(): void
    {
        Queue::fake();
        $shiftId = $this->openShift('SHF-PERF-02', '200.000');

        $this->closeShift($shiftId, '200.000')->assertOk()->assertJsonPath('data.cash_difference', '0.000');

        Queue::assertNotPushed(SendShiftDiscrepancyAlertJob::class);
    }

    public function test_the_close_succeeds_when_telegram_times_out(): void
    {
        // sync queue (phpunit.xml): the job runs right after the commit, in this request, and
        // its Telegram call throws a transport timeout.
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
        $shiftId = $this->openShift('SHF-PERF-03', '200.000');

        $this->closeShift($shiftId, '150.000')->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.cash_difference', '-50.000');

        $this->inTenant($this->tenant, function () use ($shiftId): void {
            $shift = CashShift::query()->findOrFail($shiftId);
            $this->assertSame('closed', $shift->status);
            $this->assertSame('150.000', (string) $shift->actual_cash_balance);
        });
    }

    public function test_the_job_sends_the_discrepancy_alert_and_swallows_a_telegram_error(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $shiftId = $this->openShift('SHF-PERF-04', '100.000');
        $this->closeShift($shiftId, '90.000')->assertOk();

        Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'SHF-PERF-04'));

        Http::fake(fn () => throw new ConnectionException('down'));
        $this->inTenant($this->tenant, fn () => (new SendShiftDiscrepancyAlertJob($shiftId))->handle(app(TelegramService::class)));
        $this->addToAssertionCount(1); // no exception escaped the job
    }

    public function test_the_alert_is_dispatched_after_commit(): void
    {
        // A rolled-back close must never alert: the job carries afterCommit, so the queue only
        // receives it once the close transaction commits (Laravel drops it on rollback). The
        // rollback itself is not replayed here: the testing transaction manager assumes a
        // RefreshDatabase transaction on every connection, which the tenant connection lacks.
        Queue::fake();
        $shiftId = $this->openShift('SHF-PERF-05', '200.000');

        $this->closeShift($shiftId, '260.000')->assertOk();

        Queue::assertPushed(SendShiftDiscrepancyAlertJob::class, fn (SendShiftDiscrepancyAlertJob $job): bool => $job->afterCommit === true);
    }

    public function test_on_the_database_queue_the_alert_lands_in_the_central_jobs_table_with_its_tenant(): void
    {
        // The real `database` queue (what production workers read), not the sync test queue.
        config(['queue.default' => 'database']);
        $central = (string) config('tenancy.database.central_connection');
        $this->assertSame($central, config('queue.connections.database.connection'), 'the DB queue must default to the central connection');

        $shiftId = $this->openShift('SHF-PERF-07', '200.000');
        $centralBefore = DB::connection($central)->table('jobs')->count();

        $this->closeShift($shiftId, '230.000')->assertOk();

        $jobs = DB::connection($central)->table('jobs')->orderByDesc('id')->get();
        $this->assertSame($centralBefore + 1, $jobs->count(), 'queued on the central connection');
        $payload = (array) json_decode((string) $jobs->first()->payload, true);
        $this->assertStringContainsString('SendShiftDiscrepancyAlertJob', (string) ($payload['displayName'] ?? ''));
        $this->assertSame((string) $this->tenant->getTenantKey(), (string) ($payload['tenant_id'] ?? ''), 'the worker restores this tenant');
        $this->assertSame('default', $jobs->first()->queue);

        // Nothing in the tenant's own jobs table (no worker reads it).
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => DB::table('jobs')->count()));
    }

    public function test_closing_still_requires_authentication_and_permission(): void
    {
        $shiftId = $this->openShift('SHF-PERF-06', '200.000');

        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '200.000'], $this->tenantGuestHeaders($this->tenant))
            ->assertStatus(401);

        $viewer = $this->createTenantUser($this->tenant, null, ['reports.view']);
        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '200.000'], $this->tenantHeaders($this->tenant, $viewer))
            ->assertStatus(403);
    }

    private function openShift(string $number, string $opening): int
    {
        return $this->inTenant($this->tenant, fn (): int => (int) CashShift::query()->create([
            'user_id' => (int) User::query()->orderBy('id')->value('id'),
            'store_id' => $this->storeId,
            'shift_number' => $number,
            'status' => 'open',
            'opened_at' => now()->subHours(3),
            'opening_cash_balance' => $opening,
        ])->id);
    }

    private function closeShift(int $shiftId, string $actual): TestResponse
    {
        return $this->postJson('/api/v1/shifts/close', [
            'shift_id' => $shiftId,
            'actual_cash_balance' => $actual,
        ], $this->tenantHeaders($this->tenant, null, $this->storeId));
    }
}
