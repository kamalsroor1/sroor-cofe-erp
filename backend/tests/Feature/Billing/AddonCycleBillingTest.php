<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\IssueSubscriptionInvoiceAction;
use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Models\Addon;
use App\Models\BillingInvoice;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;

/**
 * BILL-02 review: an add-on is charged for exactly the period it is granted.
 *  - a MONTHLY add-on on a YEARLY renewal is billed 12 months and runs to the new term end;
 *  - a YEARLY add-on on a MONTHLY subscription is billed one year, once a year;
 *  - a MONTHLY add-on bought mid-term is billed every (started) month up to the term end.
 */
#[Group('billing')]
final class AddonCycleBillingTest extends PlanChangeTestCase
{
    private const QUEUE = '/api/v1/super-admin/billing/plan-change-requests';

    private CentralUser $operator;

    /** @var array<string, string> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        $this->operator = $this->centralSuperAdmin();
        $this->admin = $this->steppedUpCentralHeaders($this->operator);
    }

    private function pay(Tenant $tenant, int $invoiceId, string $amount): int
    {
        return (int) $this->post('/api/v1/billing/invoices/'.$invoiceId.'/payments', [
            'receipt' => $this->receipt(),
            'method' => 'vodafone_cash',
            'amount' => $amount,
            'payer_reference' => '01000000888',
            'paid_at' => now()->toDateString(),
        ], $this->multipart($this->tenantHeaders($tenant)))->assertCreated()->json('data.id');
    }

    private function verify(int $paymentId): void
    {
        $this->postJson('/api/v1/super-admin/billing/payments/'.$paymentId.'/verify', [], $this->admin)->assertOk();
    }

    private function renewal(Tenant $tenant, Plan $plan, BillingCycle $cycle): BillingInvoice
    {
        return app(IssueSubscriptionInvoiceAction::class)->execute(new IssueSubscriptionInvoiceDTO(
            tenantId: (string) $tenant->getKey(),
            type: BillingInvoiceType::Renewal,
            planId: (int) $plan->getKey(),
            cycle: $cycle,
            useFounderPrice: false,
            serviceLines: [],
            discount: '0.000',
            notes: null,
            periodStart: null,
            issuedBy: (int) $this->operator->getKey(),
        ), $this->operator);
    }

    private function yearlyTenant(Plan $plan, CarbonImmutable $end): Tenant
    {
        $tenant = $this->subscribedTenant($plan);
        $this->endTenancy();
        $tenant->forceFill(['subscription_ends_at' => $end])->save();
        Subscription::query()->where('tenant_id', $tenant->getKey())
            ->update(['billing_cycle' => 'yearly', 'amount' => $plan->price_yearly, 'ends_at' => $end]);

        return Tenant::query()->findOrFail($tenant->getKey());
    }

    private function addonLine(Tenant $tenant, Addon $addon, string $cycle, int $quantity, \DateTimeInterface $endsAt, string $unit): SubscriptionAddon
    {
        $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

        return SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->getKey(),
            'addon_id' => $addon->getKey(),
            'quantity' => $quantity,
            'unit_price' => $unit,
            'billing_cycle' => $cycle,
            'status' => SubscriptionAddonStatus::Active,
            'starts_at' => now()->subMonth(),
            'ends_at' => $endsAt,
        ]);
    }

    public function test_a_monthly_addon_on_a_yearly_renewal_is_billed_twelve_months_and_runs_to_the_new_end(): void
    {
        $plan = $this->plan();
        $tenant = $this->yearlyTenant($plan, CarbonImmutable::now()->addDays(10));
        $addon = $this->addonOn($plan, unit: '100.000', yearly: '1000.000');
        $line = $this->addonLine($tenant, $addon, 'monthly', 2, $tenant->subscription_ends_at, '100.000');

        $invoice = $this->renewal($tenant, $plan, BillingCycle::Yearly);

        $addonRow = collect($invoice->lines)->firstWhere('type', 'addon');
        $this->assertSame(12, $addonRow['months']);
        $this->assertSame('100.000', $addonRow['cycle_unit_price']);
        $this->assertSame('1200.000', $addonRow['unit_price']);
        $this->assertSame('2400.000', $addonRow['amount']);
        $this->assertSame('6890.000', $invoice->subtotal, 'plan 4490 + 2 x 100 x 12');

        $this->verify($this->pay($tenant, (int) $invoice->getKey(), $invoice->total));

        $newEnd = $tenant->fresh()->subscription_ends_at;
        $this->assertSame($newEnd->toIso8601String(), $line->fresh()->ends_at->toIso8601String(), 'the add-on runs the whole paid year');
        $this->assertSame('100.000', $line->fresh()->unit_price, 'the line keeps its monthly unit price');
    }

    public function test_a_yearly_addon_on_a_monthly_subscription_is_billed_a_year_once_a_year(): void
    {
        $plan = $this->plan();
        $tenant = $this->subscribedTenant($plan, 10);
        $addon = $this->addonOn($plan, unit: '100.000', yearly: '1000.000');
        $addonEnd = CarbonImmutable::instance($tenant->subscription_ends_at)->addDays(5);
        $line = $this->addonLine($tenant, $addon, 'yearly', 1, $addonEnd, '1000.000');

        $invoice = $this->renewal($tenant, $plan, BillingCycle::Monthly);

        $addonRow = collect($invoice->lines)->firstWhere('type', 'addon');
        $this->assertSame('yearly', $addonRow['billing_cycle']);
        $this->assertSame(1, $addonRow['months']);
        $this->assertSame('1000.000', $addonRow['amount']);
        $this->assertSame('1449.000', $invoice->subtotal);

        $this->verify($this->pay($tenant, (int) $invoice->getKey(), $invoice->total));
        $this->assertSame($addonEnd->addYearNoOverflow()->toIso8601String(), $line->fresh()->ends_at->toIso8601String());

        // The next monthly renewal does not bill the add-on again: it is paid for a year.
        $next = $this->renewal($tenant->fresh(), $plan, BillingCycle::Monthly);
        $this->assertSame(['plan'], array_column($next->lines, 'type'));
        $this->assertSame('449.000', $next->subtotal);
    }

    public function test_a_monthly_addon_bought_mid_term_is_billed_up_to_the_term_end(): void
    {
        $plan = $this->plan();
        $end = CarbonImmutable::now()->addMonthsNoOverflow(6);
        $tenant = $this->yearlyTenant($plan, $end);
        $addon = $this->addonOn($plan, unit: '100.000', yearly: '1000.000');
        $row = $this->requestRow($tenant, ['kind' => 'addon', 'addon_id' => $addon->getKey(), 'quantity' => 2, 'billing_cycle' => 'monthly']);

        $invoiceId = (int) $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)
            ->assertCreated()->json('data.invoice.id');
        $invoice = BillingInvoice::query()->findOrFail($invoiceId);

        $this->assertSame(6, $invoice->lines[0]['months']);
        $this->assertSame('600.000', $invoice->lines[0]['unit_price']);
        $this->assertSame('1200.000', $invoice->lines[0]['amount'], '2 units x 100 x 6 months');

        $this->verify($this->pay($tenant, $invoiceId, $invoice->total));

        $line = SubscriptionAddon::query()->firstOrFail();
        $this->assertSame($end->toIso8601String(), $line->ends_at->toIso8601String());
        $this->assertSame('100.000', $line->unit_price);
    }

    public function test_a_monthly_addon_is_never_granted_past_the_months_it_paid(): void
    {
        $plan = $this->plan();
        $tenant = $this->yearlyTenant($plan, CarbonImmutable::now()->addDays(20));
        $addon = $this->addonOn($plan, unit: '100.000', yearly: '1000.000');
        $row = $this->requestRow($tenant, ['kind' => 'addon', 'addon_id' => $addon->getKey(), 'quantity' => 1, 'billing_cycle' => 'monthly']);
        $invoiceId = (int) $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)
            ->assertCreated()->json('data.invoice.id');
        $this->assertSame(1, BillingInvoice::query()->findOrFail($invoiceId)->lines[0]['months']);

        // The term is extended by a year before the add-on invoice is paid.
        $this->endTenancy();
        $longer = CarbonImmutable::now()->addYearNoOverflow();
        $tenant->forceFill(['subscription_ends_at' => $longer])->save();

        $this->verify($this->pay($tenant, $invoiceId, BillingInvoice::query()->findOrFail($invoiceId)->total));

        $this->assertSame(
            CarbonImmutable::now()->addMonthNoOverflow()->toIso8601String(),
            SubscriptionAddon::query()->firstOrFail()->ends_at->toIso8601String(),
            'one paid month, not the rest of the year',
        );
    }
}
