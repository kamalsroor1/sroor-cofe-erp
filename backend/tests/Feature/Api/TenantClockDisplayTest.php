<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Logs\ExportActivityLogsCsvAction;
use App\Actions\Receipts\GetPublicReceiptAction;
use App\Actions\Shifts\GetDailyJournalPrintDataAction;
use App\DTOs\Logs\ActivityLogStoreScope;
use App\Http\Resources\CashShiftResource;
use App\Models\ActivityLog;
use App\Models\CashShift;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Trello #223: rendered timestamps follow the tenant clock (TenantClock::toTenant), not the
 * storage timezone. Production stores in UTC (APP_TIMEZONE=UTC) while the shop runs on
 * Africa/Cairo (UTC+2 in November), so a sale at 02:10 Cairo must not show as 00:10.
 *
 * Storage is untouched: the rows are written in UTC here, exactly like production.
 */
final class TenantClockDisplayTest extends TenantTestCase
{
    /** 2026-11-26 00:10 UTC = 02:10 Africa/Cairo (EET, no DST). */
    private const STORED_UTC = '2026-11-26 00:10:00';

    private string $previousConfigTimezone;

    private string $previousPhpTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousConfigTimezone = (string) config('app.timezone');
        $this->previousPhpTimezone = date_default_timezone_get();
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');

        Carbon::setTestNow(CarbonImmutable::parse(self::STORED_UTC, 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        config(['app.timezone' => $this->previousConfigTimezone]);
        date_default_timezone_set($this->previousPhpTimezone);

        parent::tearDown();
    }

    public function test_invoice_show_and_index_render_the_time_on_the_tenant_clock(): void
    {
        $tenant = $this->cairoTenant();
        $invoiceId = $this->seedInvoice($tenant);

        $this->getJson('/api/v1/invoices/'.$invoiceId, $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.formatted_created_at', '2026-11-26 02:10');

        $this->getJson('/api/v1/invoices', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.0.created_at', '02:10')
            ->assertJsonPath('data.0.formatted_created_at', '2026-11-26 02:10');
    }

    public function test_thermal_print_and_public_receipt_render_the_tenant_time(): void
    {
        $tenant = $this->cairoTenant();
        $invoiceId = $this->seedInvoice($tenant);

        $this->inTenant($tenant, function () use ($invoiceId): void {
            $invoice = Invoice::query()->with(['customer', 'store', 'user', 'items.item'])->findOrFail($invoiceId);
            $html = view('layouts.print-thermal', ['invoice' => $invoice])->render();

            // The receipt's time cell: "<strong>{{ __('common.time') }}:</strong> H:i".
            preg_match('/'.preg_quote(__('common.time'), '/').':<\/strong>\s*(\d{2}:\d{2})/u', $html, $match);
            $this->assertSame('02:10', $match[1] ?? null);

            $receipt = app(GetPublicReceiptAction::class)->execute($invoiceId);
            $this->assertSame('02:10', $receipt['invoice']['time']);
        });
    }

    public function test_activity_log_list_and_csv_render_the_tenant_time(): void
    {
        $tenant = $this->cairoTenant();

        // 2026-11-25 23:31:41 UTC = 2026-11-26 01:31:41 Cairo (the #223 example).
        $this->inTenant($tenant, function () use ($tenant): void {
            ActivityLog::query()->delete();
            $log = ActivityLog::create([
                'user_id' => $this->tenantAdmin($tenant)->getKey(),
                'store_id' => $this->tenantStore($tenant)->getKey(),
                'module' => 'sales',
                'action' => 'invoice_created',
                'description' => 'tz check',
            ]);
            DB::table('activity_logs')->where('id', $log->getKey())->update(['created_at' => '2026-11-25 23:31:41']);
        });

        $this->getJson('/api/v1/activity-logs', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.0.created_at', '2026-11-26 01:31:41');

        $this->inTenant($tenant, function (): void {
            $response = app(ExportActivityLogsCsvAction::class)->execute([], new ActivityLogStoreScope(null));
            ob_start();
            $response->sendContent();
            $csv = (string) ob_get_clean();

            $this->assertStringContainsString('2026-11-26 01:31:41', $csv);
        });
    }

    public function test_shift_and_daily_journal_print_render_the_tenant_time(): void
    {
        $tenant = $this->cairoTenant();
        $this->seedInvoice($tenant);

        $this->inTenant($tenant, function () use ($tenant): void {
            $shiftId = DB::table('cash_shifts')->insertGetId([
                'user_id' => $this->tenantAdmin($tenant)->getKey(),
                'store_id' => $this->tenantStore($tenant)->getKey(),
                'shift_number' => 'SHIFT-20261126-001',
                'status' => 'closed',
                'opened_at' => '2026-11-26 00:05:00',
                'closed_at' => '2026-11-26 01:30:00',
                'created_at' => '2026-11-26 00:05:00',
                'updated_at' => '2026-11-26 01:30:00',
            ]);

            $row = (new CashShiftResource(CashShift::query()->findOrFail($shiftId)))->resolve();
            $this->assertSame('2026-11-26 02:05:00', $row['opened_at']);
            $this->assertSame('2026-11-26 03:30:00', $row['closed_at']);
            $this->assertSame('2026-11-26 02:05:00', $row['created_at']);

            $html = view(
                'layouts.print-daily-journal-a4',
                app(GetDailyJournalPrintDataAction::class)->execute('2026-11-26', $this->tenantStore($tenant)->getKey()),
            )->render();

            // Invoice at 02:10 Cairo, shift 02:05 -> 03:30 Cairo, printed at 02:10 Cairo (test now).
            $this->assertStringContainsString('02:10 AM', $html);
            $this->assertStringContainsString('02:05 AM', $html);
            $this->assertStringContainsString('03:30 AM', $html);
            $this->assertStringNotContainsString('12:10 AM', $html);
        });
    }

    private function cairoTenant(): Tenant
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('timezone', 'Africa/Cairo'));

        return $tenant;
    }

    private function seedInvoice(Tenant $tenant): int
    {
        return $this->inTenant($tenant, function () use ($tenant): int {
            $customerId = DB::table('customers')->insertGetId([
                'name' => 'TZ Customer',
                'created_at' => self::STORED_UTC,
                'updated_at' => self::STORED_UTC,
            ]);

            return DB::table('invoices')->insertGetId([
                'invoice_number' => 'TZ-223',
                'customer_id' => $customerId,
                'user_id' => $this->tenantAdmin($tenant)->getKey(),
                'store_id' => $this->tenantStore($tenant)->getKey(),
                'invoice_date' => '2026-11-26',
                'payment_type' => 'cash',
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'subtotal' => '100.000',
                'net_total' => '100.000',
                'paid_amount' => '100.000',
                'remaining_amount' => '0.000',
                'total_cost' => '0.000',
                'created_at' => self::STORED_UTC,
                'updated_at' => self::STORED_UTC,
            ]);
        });
    }
}
