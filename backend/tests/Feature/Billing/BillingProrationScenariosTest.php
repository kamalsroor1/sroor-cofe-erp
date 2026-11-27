<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Lock-down tests for QA Trello cards #750 and #751-#757 (BIL-271..BIL-277):
 *  - All 7 calendar/leap/clamped-month/yearly proration scenarios on Starter -> Growth;
 *  - Fully automated founder upgrade preview & self-service invoice on monthly & yearly cycles
 *    with held_for_price_review = false and real DECIMAL(12,3) totals (#750).
 */
#[Group('billing')]
final class BillingProrationScenariosTest extends PlanChangeTestCase
{
    private const PREVIEW_URL = '/api/v1/billing/change-preview';

    private const CHANGE_INVOICE_URL = '/api/v1/billing/change-invoice';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setTaxRate('14.000');
    }

    /**
     * @return array<string, array{
     *     cycle: string,
     *     start: string,
     *     end: string,
     *     now: string,
     *     remaining: int,
     *     periodDays: int,
     *     oldPrice: string,
     *     newPrice: string,
     *     subtotal: string,
     *     tax: string,
     *     total: string,
     *     seedPaidPlanInvoice: bool,
     * }>
     */
    public static function prorationScenarios(): array
    {
        return [
            'BIL-271 (#751) monthly 30-day Nov 1 -> Dec 1, 15 remaining' => [
                'cycle' => 'monthly',
                'start' => '2026-11-01 12:00:00',
                'end' => '2026-12-01 12:00:00',
                'now' => '2026-11-16 12:00:00',
                'remaining' => 15,
                'periodDays' => 30,
                'oldPrice' => '499.000',
                'newPrice' => '999.000',
                'subtotal' => '250.000',
                'tax' => '35.000',
                'total' => '285.000',
                'seedPaidPlanInvoice' => true,
            ],
            'BIL-272 (#752) normal Feb 2027 (28 days), 14 remaining' => [
                'cycle' => 'monthly',
                'start' => '2027-02-01 12:00:00',
                'end' => '2027-03-01 12:00:00',
                'now' => '2027-02-15 12:00:00',
                'remaining' => 14,
                'periodDays' => 28,
                'oldPrice' => '499.000',
                'newPrice' => '999.000',
                'subtotal' => '250.000',
                'tax' => '35.000',
                'total' => '285.000',
                'seedPaidPlanInvoice' => true,
            ],
            'BIL-273 (#753) leap Feb 2028 (29 days), 15 remaining, half-up rounding' => [
                'cycle' => 'monthly',
                'start' => '2028-02-01 12:00:00',
                'end' => '2028-03-01 12:00:00',
                'now' => '2028-02-15 12:00:00',
                'remaining' => 15,
                'periodDays' => 29,
                'oldPrice' => '499.000',
                'newPrice' => '999.000',
                'subtotal' => '258.621',
                'tax' => '36.207',
                'total' => '294.828',
                'seedPaidPlanInvoice' => true,
            ],
            'BIL-274 (#754) 31-day Jan 1 -> Feb 1, upgrade Jan 20, 12 remaining' => [
                'cycle' => 'monthly',
                'start' => '2027-01-01 12:00:00',
                'end' => '2027-02-01 12:00:00',
                'now' => '2027-01-20 12:00:00',
                'remaining' => 12,
                'periodDays' => 31,
                'oldPrice' => '499.000',
                'newPrice' => '999.000',
                'subtotal' => '193.548',
                'tax' => '27.097',
                'total' => '220.645',
                'seedPaidPlanInvoice' => true,
            ],
            'BIL-275 (#755) clamped month Jan 31 -> Feb 28 (28 days, paid invoice)' => [
                'cycle' => 'monthly',
                'start' => '2027-01-31 12:00:00',
                'end' => '2027-02-28 12:00:00',
                'now' => '2027-02-14 12:00:00',
                'remaining' => 14,
                'periodDays' => 28,
                'oldPrice' => '499.000',
                'newPrice' => '999.000',
                'subtotal' => '250.000',
                'tax' => '35.000',
                'total' => '285.000',
                'seedPaidPlanInvoice' => true,
            ],
            'BIL-275b (#755) clamped month Jan 31 -> Feb 28 (28 days, subscription fallback)' => [
                'cycle' => 'monthly',
                'start' => '2027-01-31 12:00:00',
                'end' => '2027-02-28 12:00:00',
                'now' => '2027-02-14 12:00:00',
                'remaining' => 14,
                'periodDays' => 28,
                'oldPrice' => '499.000',
                'newPrice' => '999.000',
                'subtotal' => '250.000',
                'tax' => '35.000',
                'total' => '285.000',
                'seedPaidPlanInvoice' => false,
            ],
            'BIL-276 (#756) yearly Mar 1 2026 -> Mar 1 2027 (365 days), 181 remaining' => [
                'cycle' => 'yearly',
                'start' => '2026-03-01 12:00:00',
                'end' => '2027-03-01 12:00:00',
                'now' => '2026-09-01 12:00:00',
                'remaining' => 181,
                'periodDays' => 365,
                'oldPrice' => '4990.000',
                'newPrice' => '9990.000',
                'subtotal' => '2479.452',
                'tax' => '347.123',
                'total' => '2826.575',
                'seedPaidPlanInvoice' => true,
            ],
            'BIL-277 (#757) leap yearly Jun 1 2027 -> Jun 1 2028 (366 days), 92 remaining' => [
                'cycle' => 'yearly',
                'start' => '2027-06-01 12:00:00',
                'end' => '2028-06-01 12:00:00',
                'now' => '2028-03-01 12:00:00',
                'remaining' => 92,
                'periodDays' => 366,
                'oldPrice' => '4990.000',
                'newPrice' => '9990.000',
                'subtotal' => '1256.831',
                'tax' => '175.956',
                'total' => '1432.787',
                'seedPaidPlanInvoice' => true,
            ],
        ];
    }

    #[DataProvider('prorationScenarios')]
    public function test_proration_scenario_matches_exact_decimal_math_across_preview_and_invoice(
        string $cycle,
        string $start,
        string $end,
        string $now,
        int $remaining,
        int $periodDays,
        string $oldPrice,
        string $newPrice,
        string $subtotal,
        string $tax,
        string $total,
        bool $seedPaidPlanInvoice,
    ): void {
        $tz = (string) config('app.timezone');
        $termStart = CarbonImmutable::parse($start, $tz);
        $termEnd = CarbonImmutable::parse($end, $tz);
        $this->travelTo(CarbonImmutable::parse($now, $tz));

        $starter = $this->plan(['name' => 'Starter', 'price_monthly' => '499.000', 'price_yearly' => '4990.000']);
        $growth = $this->plan(['name' => 'Growth', 'price_monthly' => '999.000', 'price_yearly' => '9990.000']);

        $tenant = $this->seedSubscribedTenant($starter, $cycle, $termStart, $termEnd, $seedPaidPlanInvoice);

        $preview = $this->getJson(
            self::PREVIEW_URL.'?'.http_build_query([
                'action' => 'upgrade',
                'plan_id' => $growth->getKey(),
                'cycle' => $cycle,
                'renew' => 0,
            ]),
            $this->tenantHeaders($tenant),
        )
            ->assertOk()
            ->assertJsonPath('data.kind', 'upgrade')
            ->assertJsonPath('data.held_for_price_review', false)
            ->assertJsonPath('data.lines.0.kind', 'proration')
            ->assertJsonPath('data.lines.0.params.days', $remaining)
            ->assertJsonPath('data.lines.0.params.period_days', $periodDays)
            ->assertJsonPath('data.lines.0.params.old', $oldPrice)
            ->assertJsonPath('data.lines.0.params.new', $newPrice)
            ->assertJsonPath('data.lines.0.amount', $subtotal)
            ->assertJsonPath('data.subtotal', $subtotal)
            ->assertJsonPath('data.tax_rate', '14.000')
            ->assertJsonPath('data.tax', $tax)
            ->assertJsonPath('data.total', $total)
            ->assertJsonPath('data.period_end', $termEnd->toDateString())
            ->assertJsonPath('data.new_period_end', null)
            ->json('data');

        $invoiceId = (int) $this->postJson(
            self::CHANGE_INVOICE_URL,
            ['action' => 'upgrade', 'plan_id' => $growth->getKey(), 'cycle' => $cycle, 'renew' => false],
            $this->tenantHeaders($tenant),
        )
            ->assertCreated()
            ->assertJsonPath('data.held_for_price_review', false)
            ->json('data.invoice.id');

        $this->endTenancy();
        $invoice = BillingInvoice::query()->findOrFail($invoiceId);

        $this->assertSame($preview['subtotal'], $invoice->subtotal);
        $this->assertSame($preview['tax'], $invoice->tax);
        $this->assertSame($preview['total'], $invoice->total);
        $this->assertSame($subtotal, $invoice->lines[0]['amount']);
        $this->assertTrue((bool) $invoice->lines[0]['prorated']);
        $this->assertSame($remaining, $invoice->lines[0]['remaining_days']);
        $this->assertSame($periodDays, $invoice->lines[0]['period_days']);
    }

    public function test_founder_shop_upgrade_preview_and_invoice_are_automated_on_monthly_and_yearly_cycles(): void
    {
        $tz = (string) config('app.timezone');
        $starter = $this->plan([
            'name' => 'Starter',
            'price_monthly' => '499.000',
            'price_yearly' => '4990.000',
            'founder_price_monthly' => '299.000',
            'founder_price_yearly' => '2990.000',
        ]);
        $growth = $this->plan([
            'name' => 'Growth',
            'price_monthly' => '999.000',
            'price_yearly' => '9990.000',
            'founder_price_monthly' => '599.000',
            'founder_price_yearly' => '5990.000',
        ]);

        // 1. Monthly founder shop (qa-basic equivalent: is_founder = true)
        $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00:00', $tz));
        $monthlyFounder = $this->seedSubscribedTenant(
            $starter,
            'monthly',
            CarbonImmutable::parse('2026-11-01 12:00:00', $tz),
            CarbonImmutable::parse('2026-12-01 12:00:00', $tz),
            true,
            founder: true,
        );

        // (599 - 299) * 15 / 30 = 150.000; + 14% VAT (21.000) = 171.000
        $monthlyPreview = $this->getJson(
            self::PREVIEW_URL.'?'.http_build_query([
                'action' => 'upgrade',
                'plan_id' => $growth->getKey(),
                'cycle' => 'monthly',
                'renew' => 0,
            ]),
            $this->tenantHeaders($monthlyFounder),
        )
            ->assertOk()
            ->assertJsonPath('data.held_for_price_review', false)
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.0.kind', 'proration')
            ->assertJsonPath('data.lines.0.founder', true)
            ->assertJsonPath('data.lines.0.params.old', '299.000')
            ->assertJsonPath('data.lines.0.params.new', '599.000')
            ->assertJsonPath('data.lines.0.amount', '150.000')
            ->assertJsonPath('data.subtotal', '150.000')
            ->assertJsonPath('data.tax', '21.000')
            ->assertJsonPath('data.total', '171.000')
            ->json('data');

        // cycle=yearly & renew=1 on monthly founder subscription: proration 150.000 + founder yearly renewal 5990.000 = 6140.000; + 14% VAT (859.600) = 6999.600
        $this->getJson(
            self::PREVIEW_URL.'?'.http_build_query([
                'action' => 'upgrade',
                'plan_id' => $growth->getKey(),
                'cycle' => 'yearly',
                'renew' => 1,
            ]),
            $this->tenantHeaders($monthlyFounder),
        )
            ->assertOk()
            ->assertJsonPath('data.held_for_price_review', false)
            ->assertJsonCount(3, 'data.lines')
            ->assertJsonPath('data.lines.0.founder', true)
            ->assertJsonPath('data.lines.0.amount', '150.000')
            ->assertJsonPath('data.lines.1.kind', 'renewal')
            ->assertJsonPath('data.lines.1.founder', true)
            ->assertJsonPath('data.lines.1.amount', '5990.000')
            ->assertJsonPath('data.subtotal', '6140.000')
            ->assertJsonPath('data.tax', '859.600')
            ->assertJsonPath('data.total', '6999.600');

        $monthlyInvoiceId = (int) $this->postJson(
            self::CHANGE_INVOICE_URL,
            ['action' => 'upgrade', 'plan_id' => $growth->getKey(), 'cycle' => 'monthly', 'renew' => false],
            $this->tenantHeaders($monthlyFounder),
        )
            ->assertCreated()
            ->assertJsonPath('data.held_for_price_review', false)
            ->json('data.invoice.id');

        $this->endTenancy();
        $monthlyInvoice = BillingInvoice::query()->findOrFail($monthlyInvoiceId);
        $this->assertSame($monthlyPreview['subtotal'], $monthlyInvoice->subtotal);
        $this->assertSame($monthlyPreview['tax'], $monthlyInvoice->tax);
        $this->assertSame($monthlyPreview['total'], $monthlyInvoice->total);
        $this->assertTrue((bool) $monthlyInvoice->lines[0]['founder']);

        // 2. Yearly founder shop: (5990 - 2990) * 181 / 365 = 1487.671; + 14% VAT (208.274) = 1695.945
        $this->travelTo(CarbonImmutable::parse('2026-09-01 12:00:00', $tz));
        $yearlyFounder = $this->seedSubscribedTenant(
            $starter,
            'yearly',
            CarbonImmutable::parse('2026-03-01 12:00:00', $tz),
            CarbonImmutable::parse('2027-03-01 12:00:00', $tz),
            true,
            founder: true,
        );

        $yearlyPreview = $this->getJson(
            self::PREVIEW_URL.'?'.http_build_query([
                'action' => 'upgrade',
                'plan_id' => $growth->getKey(),
                'cycle' => 'yearly',
                'renew' => 0,
            ]),
            $this->tenantHeaders($yearlyFounder),
        )
            ->assertOk()
            ->assertJsonPath('data.held_for_price_review', false)
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.0.kind', 'proration')
            ->assertJsonPath('data.lines.0.founder', true)
            ->assertJsonPath('data.lines.0.params.old', '2990.000')
            ->assertJsonPath('data.lines.0.params.new', '5990.000')
            ->assertJsonPath('data.lines.0.params.days', 181)
            ->assertJsonPath('data.lines.0.params.period_days', 365)
            ->assertJsonPath('data.lines.0.amount', '1487.671')
            ->assertJsonPath('data.subtotal', '1487.671')
            ->assertJsonPath('data.tax', '208.274')
            ->assertJsonPath('data.total', '1695.945')
            ->json('data');

        $yearlyInvoiceId = (int) $this->postJson(
            self::CHANGE_INVOICE_URL,
            ['action' => 'upgrade', 'plan_id' => $growth->getKey(), 'cycle' => 'yearly', 'renew' => false],
            $this->tenantHeaders($yearlyFounder),
        )
            ->assertCreated()
            ->assertJsonPath('data.held_for_price_review', false)
            ->json('data.invoice.id');

        $this->endTenancy();
        $yearlyInvoice = BillingInvoice::query()->findOrFail($yearlyInvoiceId);
        $this->assertSame($yearlyPreview['subtotal'], $yearlyInvoice->subtotal);
        $this->assertSame($yearlyPreview['tax'], $yearlyInvoice->tax);
        $this->assertSame($yearlyPreview['total'], $yearlyInvoice->total);
        $this->assertTrue((bool) $yearlyInvoice->lines[0]['founder']);
    }

    private function seedSubscribedTenant(
        Plan $plan,
        string $cycle,
        CarbonImmutable $start,
        CarbonImmutable $end,
        bool $seedPaidPlanInvoice,
        bool $founder = false,
    ): Tenant {
        $tenant = $this->tenantOn($plan, [
            'status' => 'active',
            'subscription_ends_at' => $end,
        ]);

        $amount = $cycle === 'yearly'
            ? ($founder ? (string) $plan->founder_price_yearly : (string) $plan->price_yearly)
            : ($founder ? (string) $plan->founder_price_monthly : (string) $plan->price_monthly);

        Subscription::query()->create([
            'tenant_id' => $tenant->getKey(),
            'plan_id' => $plan->getKey(),
            'billing_cycle' => $cycle,
            'status' => 'active',
            'amount' => $amount,
            'starts_at' => $start,
            'ends_at' => $end,
            'is_founder' => $founder,
            'founder_price_until' => $founder ? $start->addYear() : null,
        ]);

        if ($seedPaidPlanInvoice) {
            BillingInvoice::query()->create([
                'number' => 'SUB-SEED-'.Str::upper(Str::random(8)),
                'tenant_id' => $tenant->getKey(),
                'type' => BillingInvoiceType::Plan,
                'status' => BillingInvoiceStatus::Paid,
                'lines' => [[
                    'type' => 'plan',
                    'description' => $plan->name,
                    'billing_cycle' => $cycle,
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'amount' => $amount,
                    'founder' => $founder,
                ]],
                'subtotal' => $amount,
                'discount' => '0.000',
                'tax_rate' => '0.000',
                'tax' => '0.000',
                'total' => $amount,
                'period_start' => $start,
                'period_end' => $end,
                'issued_at' => $start,
                'due_at' => $start->addDays(7),
                'paid_at' => $start,
            ]);
        }

        return Tenant::query()->findOrFail($tenant->getKey());
    }
}
