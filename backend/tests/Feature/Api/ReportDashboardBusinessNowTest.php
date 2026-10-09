<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\DashboardAnalyticsService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TenantTestCase;

/**
 * SETG-2 ext, lane 2H: report date presets and the dashboard analytics "today" use
 * TenantClock::businessNow(), so before the cutoff they still mean the previous business day.
 *
 * Fixed instant: 2026-10-09 01:30 Africa/Cairo with cutoff 03:00 -> business day 2026-10-08.
 */
final class ReportDashboardBusinessNowTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-09 01:30:00', 'Africa/Cairo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_report_presets_resolve_against_the_business_day(): void
    {
        $tenant = $this->tenantWithCutoff();

        $this->getJson('/api/v1/reports/summary?period=today', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('period.from_date', '2026-10-08')
            ->assertJsonPath('period.to_date', '2026-10-08');

        $this->getJson('/api/v1/reports/summary?period=yesterday', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('period.from_date', '2026-10-07')
            ->assertJsonPath('period.to_date', '2026-10-07');
    }

    public function test_dashboard_analytics_today_is_the_business_day(): void
    {
        $tenant = $this->tenantWithCutoff();

        $this->inTenant($tenant, function () use ($tenant): void {
            $storeId = (int) $this->tenantStore($tenant)->getKey();
            $userId = (int) $this->tenantAdmin($tenant)->getKey();
            $customer = Customer::create(['name' => 'عميل لوحة', 'phone' => '01000007621', 'current_balance' => '0.000', 'is_active' => true]);

            foreach (['2026-10-08' => '40.000', '2026-10-09' => '99.000'] as $date => $total) {
                Invoice::create([
                    'invoice_number' => 'INV-BN-'.$date,
                    'customer_id' => $customer->id,
                    'user_id' => $userId,
                    'store_id' => $storeId,
                    'invoice_date' => $date,
                    'subtotal' => $total,
                    'net_total' => $total,
                    'paid_amount' => $total,
                    'remaining_amount' => '0.000',
                    'payment_type' => 'cash',
                    'payment_method' => 'cash',
                    'status' => 'confirmed',
                ]);
            }

            $analytics = app(DashboardAnalyticsService::class)->getAnalytics($storeId, 7);

            $this->assertSame(1, $analytics['today']['invoices_count']);
            $this->assertSame(0, bccomp((string) $analytics['today']['sales'], '40.000', 3));
        });
    }

    private function tenantWithCutoff(): Tenant
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('business_day_cutoff', '03:00'));

        return $tenant;
    }
}
