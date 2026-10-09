<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Support\TenantClock;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * SETG-2 ext (CTO Settings Q6): `business_day_cutoff`. The business day of D is
 * [D + cutoff, D+1 + cutoff) on the tenant clock, so with cutoff 03:00 a sale at 01:30
 * belongs to the previous day — in the stamped invoice_date, the invoice number, the
 * report, the dashboard, the daily journal and the shift number. Cutoff 00:00 (default)
 * changes nothing.
 *
 * Fixed instant: 2026-10-09 01:30 Africa/Cairo (the tenant default timezone).
 */
final class BusinessDayCutoffTest extends TenantTestCase
{
    private const NOW_CAIRO = '2026-10-09 01:30:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse(self::NOW_CAIRO, 'Africa/Cairo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_clock_puts_an_instant_before_the_cutoff_on_the_previous_business_day(): void
    {
        $tenant = $this->tenantWithCutoff('03:00');

        $this->inTenant($tenant, function (): void {
            $clock = app(TenantClock::class);

            $this->assertSame('03:00', $clock->businessDayCutoff());
            $this->assertSame('2026-10-08', $clock->businessDate());
            $this->assertSame('2026-10-08', $clock->today());
            $this->assertSame('2026-10-08', $clock->businessNow()->toDateString());
            // Real wall time is untouched (timestamps, display).
            $this->assertSame('2026-10-09 01:30', $clock->now()->format('Y-m-d H:i'));

            $this->assertSame('2026-10-08', $clock->businessDate(CarbonImmutable::parse('2026-10-09 02:59:59', 'Africa/Cairo')));
            $this->assertSame('2026-10-09', $clock->businessDate(CarbonImmutable::parse('2026-10-09 03:00:00', 'Africa/Cairo')));
            $this->assertSame('2026-10-09', $clock->businessDate(CarbonImmutable::parse('2026-10-09 23:59:59', 'Africa/Cairo')));
        });
    }

    public function test_business_day_range_is_half_open_from_cutoff_to_cutoff_in_storage_time(): void
    {
        $tenant = $this->tenantWithCutoff('03:00');

        $this->inTenant($tenant, function (): void {
            $clock = app(TenantClock::class);
            $storageTz = (string) config('app.timezone');

            [$start, $end] = $clock->businessDayRange('2026-10-08');

            $this->assertSame($storageTz, $start->getTimezone()->getName());
            $this->assertSame($storageTz, $end->getTimezone()->getName());
            $this->assertTrue($start->equalTo(CarbonImmutable::parse('2026-10-08 03:00:00', 'Africa/Cairo')));
            $this->assertTrue($end->equalTo(CarbonImmutable::parse('2026-10-09 03:00:00', 'Africa/Cairo')));

            [$inclusiveStart, $inclusiveEnd] = $clock->dayBounds('2026-10-08');
            $this->assertTrue($inclusiveStart->equalTo($start));
            $this->assertTrue($inclusiveEnd->equalTo($end->subMicrosecond()));
        });
    }

    public function test_a_0130_pos_sale_lands_on_the_previous_day_everywhere(): void
    {
        $tenant = $this->tenantWithCutoff('03:00');
        $fixture = $this->seedSellableItem($tenant);

        $invoice = $this->checkout($tenant, $fixture)->assertStatus(201);
        $this->assertSame('2026-10-08', substr((string) $invoice->json('data.invoice_date'), 0, 10));
        $this->assertStringContainsString('-20261008-', (string) $invoice->json('data.invoice_number'));

        // Report: the sale is on the 8th, not the 9th.
        $this->getJson('/api/v1/reports/summary?from_date=2026-10-08&to_date=2026-10-08', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('summary.invoices_count', 1)
            ->assertJsonPath('summary.total_sales', 110);
        $this->getJson('/api/v1/reports/summary?from_date=2026-10-09&to_date=2026-10-09', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('summary.invoices_count', 0);

        // Dashboard: "today" is still the business day of the 8th at 01:30.
        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('metrics.today_invoices_count', 1)
            ->assertJsonPath('metrics.today_sales', 110);

        // Daily journal defaults to the business day.
        $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.date', '2026-10-08')
            ->assertJsonCount(1, 'data.invoices');

        // Shift day: a shift opened at 01:30 is numbered on the 8th.
        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '0'], $this->tenantHeaders($tenant))
            ->assertStatus(201)
            ->assertJsonPath('data.shift_number', 'SHIFT-20261008-001');

        // The SPA bootstrap carries the business date.
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.system.business_day_cutoff', '03:00')
            ->assertJsonPath('data.system.business_date', '2026-10-08');
    }

    public function test_after_the_cutoff_the_new_business_day_starts(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-09 03:00:00', 'Africa/Cairo'));
        $tenant = $this->tenantWithCutoff('03:00');
        $fixture = $this->seedSellableItem($tenant);

        $invoice = $this->checkout($tenant, $fixture)->assertStatus(201);
        $this->assertSame('2026-10-09', substr((string) $invoice->json('data.invoice_date'), 0, 10));

        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '0'], $this->tenantHeaders($tenant))
            ->assertStatus(201)
            ->assertJsonPath('data.shift_number', 'SHIFT-20261009-001');
    }

    public function test_cutoff_0000_keeps_the_calendar_day(): void
    {
        $explicit = $this->tenantWithCutoff('00:00');
        $default = $this->createTenant();

        foreach ([$explicit, $default] as $tenant) {
            $fixture = $this->seedSellableItem($tenant);

            $invoice = $this->checkout($tenant, $fixture)->assertStatus(201);
            $this->assertSame('2026-10-09', substr((string) $invoice->json('data.invoice_date'), 0, 10));
            $this->assertStringContainsString('-20261009-', (string) $invoice->json('data.invoice_number'));

            $this->getJson('/api/v1/dashboard', $this->tenantHeaders($tenant))
                ->assertStatus(200)
                ->assertJsonPath('metrics.today_invoices_count', 1)
                ->assertJsonPath('metrics.today_sales', 110);

            $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '0'], $this->tenantHeaders($tenant))
                ->assertStatus(201)
                ->assertJsonPath('data.shift_number', 'SHIFT-20261009-001');

            $this->inTenant($tenant, function (): void {
                $this->assertSame('00:00', app(TenantClock::class)->businessDayCutoff());
                $this->assertSame('2026-10-09', app(TenantClock::class)->businessDate());
            });
        }
    }

    public function test_cutoff_of_one_tenant_does_not_shift_another(): void
    {
        $late = $this->tenantWithCutoff('03:00');
        $plain = $this->createTenant();

        $this->inTenant($late, fn () => $this->assertSame('2026-10-08', app(TenantClock::class)->businessDate()));
        $this->inTenant($plain, fn () => $this->assertSame('2026-10-09', app(TenantClock::class)->businessDate()));
    }

    public function test_admin_can_save_the_cutoff(): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', ['company_name' => 'محل', 'business_day_cutoff' => '04:30'], $this->tenantHeaders($tenant))
            ->assertStatus(200);

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('settings.business_day_cutoff', '04:30');
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidCutoffs(): array
    {
        return [
            'single-digit hour' => ['3:00'],
            'hour 24' => ['24:00'],
            'minute 60' => ['03:60'],
            'with seconds' => ['03:00:00'],
            'text' => ['late'],
            'empty' => [''],
            'array' => [['03:00']],
        ];
    }

    #[DataProvider('invalidCutoffs')]
    public function test_invalid_cutoff_is_rejected_with_422(mixed $value): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', ['company_name' => 'محل', 'business_day_cutoff' => $value], $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_day_cutoff']);

        $this->inTenant($tenant, function (): void {
            $this->assertFalse(Setting::query()->where('key', 'business_day_cutoff')->exists());
        });
    }

    public function test_a_corrupted_stored_cutoff_falls_back_to_midnight(): void
    {
        $tenant = $this->tenantWithCutoff('25:99');

        $this->inTenant($tenant, function (): void {
            $this->assertSame('00:00', app(TenantClock::class)->businessDayCutoff());
            $this->assertSame('2026-10-09', app(TenantClock::class)->businessDate());
        });
    }

    public function test_saving_the_cutoff_requires_settings_permission(): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', ['company_name' => 'محل', 'business_day_cutoff' => '03:00'], [
            'Accept' => 'application/json',
            'X-Tenant' => (string) $tenant->getTenantKey(),
        ])->assertStatus(401);

        $cashier = $this->createTenantUser($tenant);
        $this->postJson('/api/v1/settings', ['company_name' => 'محل', 'business_day_cutoff' => '03:00'], $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(403);
    }

    private function tenantWithCutoff(string $cutoff): Tenant
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($cutoff): void {
            Setting::set('business_day_cutoff', $cutoff);
        });

        return $tenant;
    }

    /**
     * @return array{item_id: int, customer_id: int}
     */
    private function seedSellableItem(Tenant $tenant): array
    {
        return $this->inTenant($tenant, function () use ($tenant): array {
            $item = Item::create([
                'name' => 'بن تجربة اليوم التجاري',
                'code' => 'BDC-1',
                'category' => 'coffee_beans',
                'unit' => 'كجم',
                'cost_price' => '40.000',
                'selling_price' => '55.000',
                'price_retail' => '55.000',
                'price_wholesale' => '50.000',
                'current_stock' => '10.000',
                'is_active' => true,
            ]);
            StoreStock::create([
                'store_id' => $this->tenantStore($tenant)->getKey(),
                'item_id' => $item->id,
                'quantity' => '10.000',
            ]);
            $customer = Customer::create([
                'name' => 'عميل اليوم التجاري',
                'phone' => '01000007411',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            return ['item_id' => (int) $item->id, 'customer_id' => (int) $customer->id];
        });
    }

    /**
     * POS checkout without invoice_date — the server stamps the business date.
     *
     * @param  array{item_id: int, customer_id: int}  $fixture
     */
    private function checkout(Tenant $tenant, array $fixture): TestResponse
    {
        return $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $fixture['customer_id'],
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [
                ['item_id' => $fixture['item_id'], 'quantity' => '2.000', 'unit_price' => '55.000'],
            ],
        ], $this->tenantHeaders($tenant));
    }
}
