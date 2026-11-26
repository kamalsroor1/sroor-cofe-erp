<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\IssueSubscriptionInvoiceAction;
use App\Actions\Billing\RecordManualPaymentAction;
use App\Actions\Billing\VerifyManualPaymentAction;
use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\DTOs\Billing\RecordManualPaymentDTO;
use App\DTOs\Billing\VerifyManualPaymentDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

/**
 * Review fix A: every billing flow that locks the tenant row takes it FIRST (tenant -> payment ->
 * invoice(s) -> subscription). Verify used to lock payment -> invoice -> tenant while issuing a
 * difference invoice locks tenant -> open renewals: a verify of a renewal and a concurrent
 * difference issue could each hold one row and wait for the other (deadlock).
 *
 * sqlite drops the FOR UPDATE clause, so the order is asserted on the row reads themselves: the
 * locking `select *` of each table, in the order the central connection ran them.
 */
#[Group('billing')]
final class BillingLockOrderTest extends BillingTestCase
{
    private CentralUser $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = $this->centralSuperAdmin();
    }

    public function test_verify_locks_the_tenant_before_the_payment_and_the_invoice(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'active', 'subscription_ends_at' => now()->addDays(10)]);
        $invoice = $this->pendingInvoice($tenant);
        $payment = $this->pendingPayment($invoice);

        $order = $this->lockedTablesDuring(fn () => app(VerifyManualPaymentAction::class)->execute(new VerifyManualPaymentDTO((int) $payment->getKey(), CarbonImmutable::now()), $this->operator));

        $this->assertSame(['tenants', 'billing_payments', 'billing_invoices'], array_slice($order, 0, 3));
        $this->assertSame(BillingPaymentStatus::Verified, $payment->refresh()->status);
        $this->assertSame('paid', $invoice->refresh()->status->value);
    }

    public function test_recording_a_payment_locks_the_tenant_before_the_invoice(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'active', 'subscription_ends_at' => now()->addDays(10)]);
        $invoice = $this->pendingInvoice($tenant);

        $dto = new RecordManualPaymentDTO(
            invoiceId: (int) $invoice->getKey(),
            method: BillingPaymentMethod::Cash,
            amount: '100.000',
            reference: 'REF-1',
            note: null,
            paidAt: now()->toImmutable(),
        );

        $order = $this->lockedTablesDuring(fn () => app(RecordManualPaymentAction::class)->execute($dto, $this->operator));

        $this->assertSame(['tenants', 'billing_invoices'], array_slice($order, 0, 2));
        $this->assertSame('paid', $invoice->refresh()->status->value);
    }

    public function test_issuing_a_difference_locks_the_tenant_before_the_open_renewals(): void
    {
        $plan = $this->plan();
        $big = $this->plan(['price_monthly' => '900.000']);
        $tenant = $this->tenantOn($plan, ['status' => 'active', 'subscription_ends_at' => now()->addDays(10)]);
        $renewal = $this->pendingInvoice($tenant, ['type' => BillingInvoiceType::Renewal->value]);

        $dto = new IssueSubscriptionInvoiceDTO(
            tenantId: (string) $tenant->getKey(),
            type: BillingInvoiceType::Upgrade,
            planId: $big->id,
            cycle: BillingCycle::Monthly,
            useFounderPrice: false,
            serviceLines: [],
            discount: '0.000',
            notes: null,
            periodStart: null,
            issuedBy: (int) $this->operator->getKey(),
            differenceLine: ['description' => 'Upgrade difference', 'amount' => '200.000'],
            fromRequest: true,
        );

        $order = $this->lockedTablesDuring(fn () => app(IssueSubscriptionInvoiceAction::class)->execute($dto, $this->operator));

        $this->assertSame('tenants', $order[0] ?? null);
        $this->assertContains('billing_invoices', $order);
        $this->assertSame('void', $renewal->refresh()->status->value, 'The open renewal was superseded by the difference.');
    }

    private function pendingPayment(BillingInvoice $invoice): BillingPayment
    {
        return BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->getKey(),
            'tenant_id' => $invoice->tenant_id,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'method' => BillingPaymentMethod::Cash,
            'gateway' => BillingGateway::Manual,
            'status' => BillingPaymentStatus::Pending,
            'paid_at' => now(),
        ]);
    }

    /**
     * The tables whose full rows the central connection read during $work, first read per table,
     * in order (`select *` is how each Action loads the row it locks).
     *
     * @return list<string>
     */
    private function lockedTablesDuring(Closure $work): array
    {
        $central = (string) (new BillingInvoice)->getConnectionName();
        $tables = [];

        DB::listen(function (QueryExecuted $query) use ($central, &$tables): void {
            if ($query->connectionName !== $central) {
                return;
            }
            if (preg_match('/^select \* from "(tenants|billing_payments|billing_invoices)"/', $query->sql, $m) === 1
                && ! in_array($m[1], $tables, true)) {
                $tables[] = $m[1];
            }
        });

        $work();

        return $tables;
    }
}
