<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\CentralAuditEvent;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

/**
 * #232 super-admin billing console gaps:
 *  1. GET /super-admin/billing/payments/{id}: one queue item (200 / 401 / 403 / 404);
 *  2. `invoice_plan_change` {from, to} on a payment whose invoice answered a plan-change request,
 *     eager-loaded (no per-row queries);
 *  3. a released founder hold with a typed amount on a tenant with NO running paid term bills the
 *     full cycle at that agreed amount (VAT on top, never re-priced, no founder slot).
 */
#[Group('billing')]
final class BillingConsoleGapsTest extends PlanChangeTestCase
{
    private const PAYMENTS = '/api/v1/super-admin/billing/payments';

    private const QUEUE = '/api/v1/super-admin/billing/plan-change-requests';

    private CentralUser $operator;

    /** @var array<string, string> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-26 12:00:00', (string) config('app.timezone')));
        $this->operator = $this->centralSuperAdmin();
        $this->admin = $this->steppedUpCentralHeaders($this->operator);
    }

    private function payment(BillingInvoice $invoice, string $status = 'pending'): BillingPayment
    {
        $this->endTenancy();

        return BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->getKey(),
            'tenant_id' => $invoice->tenant_id,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'method' => BillingPaymentMethod::Instapay,
            'gateway' => BillingGateway::Manual,
            'status' => $status === 'pending' ? BillingPaymentStatus::Pending : BillingPaymentStatus::from($status),
            'paid_at' => now(),
            'raw_payload' => ['payer_reference' => '01000000999'],
        ]);
    }

    private function issue(PlanChangeRequest $row, array $body = []): BillingInvoice
    {
        $id = (int) $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', $body, $this->admin)
            ->assertCreated()->json('data.invoice.id');

        return BillingInvoice::query()->findOrFail($id);
    }

    // ------------------------------------------------------------------ 1. show

    public function test_show_returns_the_same_shape_as_a_queue_item(): void
    {
        $tenant = $this->createTenant();
        $payment = $this->payment($this->pendingInvoice($tenant));
        $headers = $this->centralHeaders($this->operator);

        $item = $this->getJson(self::PAYMENTS, $headers)->assertOk()->json('data.0');

        $this->getJson(self::PAYMENTS.'/'.$payment->getKey(), $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', $item)
            ->assertJsonPath('data.id', $payment->getKey())
            ->assertJsonPath('data.tenant.id', $tenant->getKey())
            ->assertJsonPath('data.invoice_total', '100.000')
            ->assertJsonPath('data.invoice_plan_change', null)
            ->assertJsonPath('data.receipt_classification', null)
            ->assertJsonStructure(['data' => ['invoice_number', 'invoice_type', 'invoice_temporary_access', 'reviewed_by', 'has_receipt']])
            ->assertJsonMissingPath('data.proof_path');
    }

    public function test_show_is_readable_by_support_and_404_for_an_unknown_id(): void
    {
        $payment = $this->payment($this->pendingInvoice($this->createTenant()));

        $this->getJson(self::PAYMENTS.'/'.$payment->getKey(), $this->centralHeaders($this->centralSupport()))->assertOk();
        $this->getJson(self::PAYMENTS.'/999999', $this->centralHeaders($this->operator))->assertNotFound();
    }

    public function test_show_is_401_for_guests_and_tenant_tokens_and_403_without_a_role(): void
    {
        $tenant = $this->createTenant();
        $payment = $this->payment($this->pendingInvoice($tenant));
        $url = self::PAYMENTS.'/'.$payment->getKey();

        $this->getJson($url, ['Accept' => 'application/json'])->assertUnauthorized();
        $this->getJson($url, ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)])->assertUnauthorized();
        $this->getJson($url, $this->centralHeaders($this->centralOperator(null)))->assertForbidden();
    }

    // ------------------------------------------------------------------ 2. invoice_plan_change

    public function test_a_plan_change_payment_names_the_from_and_to_plans_in_list_and_show(): void
    {
        $small = $this->plan(['name' => 'Starter', 'price_monthly' => '499.000']);
        $big = $this->plan(['name' => 'Growth', 'price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $invoice = $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]));
        $payment = $this->payment($invoice);
        $headers = $this->centralHeaders($this->operator);

        $expected = ['from' => ['id' => $small->getKey(), 'name' => 'Starter'], 'to' => ['id' => $big->getKey(), 'name' => 'Growth']];

        $this->getJson(self::PAYMENTS, $headers)->assertOk()
            ->assertJsonPath('data.0.invoice_type', 'upgrade')
            ->assertJsonPath('data.0.invoice_plan_change', $expected);
        $this->getJson(self::PAYMENTS.'/'.$payment->getKey(), $headers)->assertOk()
            ->assertJsonPath('data.invoice_plan_change', $expected);
    }

    public function test_an_addon_request_invoice_has_no_plan_change(): void
    {
        $plan = $this->plan(['price_monthly' => '499.000']);
        $tenant = $this->subscribedTenant($plan, 10);
        $addon = $this->addonOn($plan);
        $invoice = $this->issue($this->requestRow($tenant, ['kind' => 'addon', 'addon_id' => $addon->getKey(), 'quantity' => 1]));
        $payment = $this->payment($invoice);

        $this->getJson(self::PAYMENTS.'/'.$payment->getKey(), $this->centralHeaders($this->operator))->assertOk()
            ->assertJsonPath('data.invoice_type', 'addon')
            ->assertJsonPath('data.invoice_plan_change', null);
    }

    public function test_a_plan_change_from_no_plan_has_a_null_from(): void
    {
        $big = $this->plan(['name' => 'Growth', 'price_monthly' => '999.000']);
        $tenant = $this->createTenant(['plan_id' => null, 'status' => 'trial', 'trial_ends_at' => now()->addDays(5), 'subscription_ends_at' => null]);
        $invoice = $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey(), 'current_plan_id' => null]));

        $this->getJson(self::PAYMENTS.'/'.$this->payment($invoice)->getKey(), $this->centralHeaders($this->operator))->assertOk()
            ->assertJsonPath('data.invoice_plan_change', ['from' => null, 'to' => ['id' => $big->getKey(), 'name' => 'Growth']]);
    }

    public function test_the_queue_query_count_does_not_grow_with_the_rows(): void
    {
        $headers = $this->centralHeaders($this->operator);
        $small = $this->plan(['price_monthly' => '499.000']);

        $make = function () use ($small): void {
            $tenant = $this->subscribedTenant($small, 10);
            $big = $this->plan(['price_monthly' => '999.000']);
            $this->payment($this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()])));
        };

        $make();
        $one = $this->countQueries(fn () => $this->getJson(self::PAYMENTS, $headers)->assertOk()->assertJsonCount(1, 'data'));

        $make();
        $make();
        $three = $this->countQueries(fn () => $this->getJson(self::PAYMENTS, $headers)->assertOk()->assertJsonCount(3, 'data'));

        $this->assertSame($one, $three);
    }

    private function countQueries(callable $call): int
    {
        $connection = DB::connection((string) (new BillingPayment)->getConnectionName());
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        $call();
        // The token's throttled last_used_at write is auth noise, not a per-row query.
        $count = count(array_filter($connection->getQueryLog(), static fn (array $q): bool => ! str_contains((string) $q['query'], 'personal_access_tokens')));
        $connection->disableQueryLog();

        return $count;
    }

    // ------------------------------------------------------------------ 3. agreed founder price, no paid term

    private function lapsedFounder(): array
    {
        $small = $this->plan(['price_monthly' => '499.000', 'founder_price_monthly' => '299.000']);
        $big = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '699.000']);
        $tenant = $this->subscribedTenant($small, 10);
        Subscription::query()->where('tenant_id', $tenant->getKey())->update(['is_founder' => true, 'founder_price_until' => now()->addMonths(6)]);
        // The paid term lapsed: no running term, the founder schedule itself is still on.
        Tenant::query()->whereKey($tenant->getKey())->update(['subscription_ends_at' => now()->subDays(3), 'status' => 'past_due']);

        $row = $this->requestRow(Tenant::query()->findOrFail($tenant->getKey()), ['requested_plan_id' => $big->getKey()]);

        return [$tenant, $row];
    }

    public function test_a_released_hold_with_a_typed_amount_and_no_paid_term_bills_the_agreed_full_cycle(): void
    {
        $this->setTaxRate('14.000');
        [$tenant, $row] = $this->lapsedFounder();
        $slots = app(FounderPricingService::class)->claimedSlots();

        $invoice = $this->issue($row, ['release_founder_hold' => true, 'amount' => '700.000']);

        $this->assertSame('upgrade', $invoice->type->value);
        $this->assertCount(1, $invoice->lines);
        $line = $invoice->lines[0];
        $this->assertSame('700.000', $line['amount']);
        $this->assertSame('700.000', $line['unit_price']);
        $this->assertSame('999.000', $line['list_price']);
        $this->assertTrue($line['agreed_price']);
        $this->assertTrue($line['founder_excluded']);
        $this->assertFalse($line['founder']);
        $this->assertArrayNotHasKey('difference', $line);
        $this->assertArrayNotHasKey('price_step', $line);
        // 700.000 + 14% (98.000) = 798.000
        $this->assertSame('700.000', $invoice->subtotal);
        $this->assertSame('98.000', $invoice->tax);
        $this->assertSame('798.000', $invoice->total);

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::PlanChangeInvoiced->value)->sole();
        $this->assertSame('700.000', $audit->properties['agreed_price'] ?? null);

        $this->getJson('/api/v1/super-admin/billing-invoices/'.$invoice->getKey(), $this->centralHeaders($this->operator))->assertOk()
            ->assertJsonPath('data.lines.0.agreed_price', true)
            ->assertJsonPath('data.lines.0.price_step', null)
            ->assertJsonPath('data.lines.0.amount', '700.000');

        // Verify: not re-priced, the term starts at the transfer, no founder slot claimed.
        $payment = $this->payment($invoice);
        $this->getJson(self::PAYMENTS.'/'.$payment->getKey().'/verify-preview?transferred_at='.urlencode(now()->toIso8601String()), $this->admin)->assertOk();
        $this->postJson(self::PAYMENTS.'/'.$payment->getKey().'/verify', $this->verifyBody(), $this->admin)->assertOk();

        $fresh = BillingInvoice::query()->findOrFail($invoice->getKey());
        $this->assertSame('paid', $fresh->status->value);
        $this->assertSame('798.000', $fresh->total);
        $this->assertSame('700.000', $fresh->lines[0]['amount']);
        $this->assertSame('0.000', $fresh->overpaid_amount);
        $this->assertSame($slots, app(FounderPricingService::class)->claimedSlots());

        $tenantRow = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertSame((int) $row->requested_plan_id, (int) $tenantRow->plan_id);
        $this->assertTrue($tenantRow->hasPaidTermRunning());
    }

    public function test_without_release_flag_a_typed_amount_on_lapsed_founder_bills_agreed_amount_while_trial_keeps_409(): void
    {
        [, $row] = $this->lapsedFounder();

        // Lapsed founder with a typed amount issues immediately without needing release_founder_hold.
        $invoice = $this->issue($row, ['amount' => '700.000']);
        $this->assertSame('700.000', $invoice->subtotal);
        $this->assertTrue($invoice->lines[0]['agreed_price']);

        // A non-founder trial with a typed amount keeps the old 409.
        $big = $this->plan(['price_monthly' => '999.000']);
        $trial = $this->tenantOn($this->plan(), ['status' => 'trial', 'trial_ends_at' => now()->addDays(5), 'subscription_ends_at' => null]);
        $other = $this->requestRow($trial, ['requested_plan_id' => $big->getKey()]);
        $this->postJson(self::QUEUE.'/'.$other->getKey().'/invoice', ['amount' => '700.000'], $this->admin)
            ->assertStatus(409)->assertJsonPath('error_code', 'billing.difference_needs_active_term');
    }

    public function test_a_lapsed_founder_without_an_amount_and_no_paid_term_uses_founder_price(): void
    {
        [, $row] = $this->lapsedFounder();

        $invoice = $this->issue($row);

        $this->assertSame('699.000', $invoice->lines[0]['amount']);
        $this->assertSame('founder', $invoice->lines[0]['price_step']);
        $this->assertTrue($invoice->lines[0]['founder']);
        $this->assertArrayNotHasKey('agreed_price', $invoice->lines[0]);
    }
}
