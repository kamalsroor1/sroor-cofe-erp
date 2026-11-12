<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\IssueSubscriptionInvoiceAction;
use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Models\BillingInvoice;
use App\Models\CentralUser;
use App\Models\Tenant;
use PHPUnit\Framework\Attributes\Group;

/**
 * BILL-01: an old renewal invoice must not move the shop back after an upgrade, and a difference
 * invoice cannot be confirmed once the term it priced has ended.
 */
#[Group('billing')]
final class RenewalAfterUpgradeTest extends PlanChangeTestCase
{
    private const QUEUE = '/api/v1/super-admin/billing/plan-change-requests';

    private CentralUser $operator;

    /** @var array<string, string> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function verify(int $paymentId)
    {
        return $this->postJson('/api/v1/super-admin/billing/payments/'.$paymentId.'/verify', [], $this->admin);
    }

    private function renewal(Tenant $tenant, int $planId): BillingInvoice
    {
        return app(IssueSubscriptionInvoiceAction::class)->execute(new IssueSubscriptionInvoiceDTO(
            tenantId: (string) $tenant->getKey(),
            type: BillingInvoiceType::Renewal,
            planId: $planId,
            cycle: BillingCycle::Monthly,
            useFounderPrice: false,
            serviceLines: [],
            discount: '0.000',
            notes: null,
            periodStart: null,
            issuedBy: (int) $this->operator->getKey(),
        ), $this->operator);
    }

    public function test_upgrade_voids_the_pending_renewal_of_the_old_plan(): void
    {
        $small = $this->plan(['max_users' => 3]);
        $big = $this->plan(['max_users' => 50, 'price_monthly' => '899.000']);
        $tenant = $this->subscribedTenant($small, 12);
        $old = $this->renewal($tenant, (int) $small->getKey());

        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);
        $invoiceId = (int) $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', ['amount' => '200'], $this->admin)
            ->assertCreated()->json('data.invoice.id');
        $this->verify($this->pay($tenant, $invoiceId, BillingInvoice::query()->findOrFail($invoiceId)->total))->assertOk();

        $this->assertSame('void', $old->fresh()->status->value);
        $this->assertSame($big->getKey(), $tenant->fresh()->plan_id);
        $this->assertSame('completed', $row->fresh()->status->value);

        // A fresh renewal is issued at the NEW plan's price.
        $new = $this->renewal($tenant->fresh(), (int) $big->getKey());
        $this->assertSame('899.000', $new->subtotal);
    }

    public function test_a_renewal_of_another_plan_is_refused_with_409(): void
    {
        $small = $this->plan();
        $big = $this->plan(['price_monthly' => '899.000']);
        $tenant = $this->subscribedTenant($small, 12);
        $stale = $this->renewal($tenant, (int) $small->getKey());
        $paymentId = $this->pay($tenant, (int) $stale->getKey(), $stale->total);

        // The shop moved to the big plan behind the invoice's back.
        Tenant::query()->whereKey($tenant->getKey())->update(['plan_id' => $big->getKey()]);

        $this->verify($paymentId)->assertStatus(409)->assertJsonPath('error_code', 'billing.renewal_plan_mismatch');
        $this->assertSame($big->getKey(), $tenant->fresh()->plan_id);
    }

    public function test_confirming_a_difference_after_the_term_ended_is_409(): void
    {
        $small = $this->plan();
        $big = $this->plan(['price_monthly' => '899.000']);
        $tenant = $this->subscribedTenant($small, 5);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);
        $invoiceId = (int) $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', ['amount' => '120'], $this->admin)
            ->assertCreated()->json('data.invoice.id');
        $invoice = BillingInvoice::query()->findOrFail($invoiceId);
        $this->assertTrue($invoice->due_at->lessThanOrEqualTo($tenant->subscription_ends_at));
        $paymentId = $this->pay($tenant, $invoiceId, $invoice->total);

        Tenant::query()->whereKey($tenant->getKey())->update(['subscription_ends_at' => now()->subDay()]);

        $this->verify($paymentId)->assertStatus(409)->assertJsonPath('error_code', 'billing.difference_needs_active_term');
        $this->assertSame($small->getKey(), $tenant->fresh()->plan_id);
    }

    public function test_an_omitted_amount_uses_the_prorata_suggestion(): void
    {
        $small = $this->plan();
        $big = $this->plan(['price_monthly' => '899.000']);
        $tenant = $this->subscribedTenant($small, 12);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);

        $invoice = BillingInvoice::query()->findOrFail(
            (int) $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)->assertCreated()->json('data.invoice.id'),
        );

        $this->assertSame(1, bccomp($invoice->lines[0]['amount'], '0', 3));
        $this->assertSame(-1, bccomp($invoice->lines[0]['amount'], '450.000', 3));
        $this->assertEquals($tenant->subscription_ends_at->toIso8601String(), $invoice->period_end->toIso8601String());
    }
}
