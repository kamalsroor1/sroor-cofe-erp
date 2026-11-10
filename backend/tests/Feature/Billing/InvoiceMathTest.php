<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\IssueSubscriptionInvoiceAction;
use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use PHPUnit\Framework\Attributes\Group;

/**
 * Subscription invoice math: bcmath strings, VAT once on the net, half-up at scale 3 (SETG-13).
 */
#[Group('billing')]
final class InvoiceMathTest extends BillingTestCase
{
    private function issue(string $tenantId, array $overrides = []): BillingInvoice
    {
        $this->endTenancy();

        $args = array_merge([
            'type' => BillingInvoiceType::Plan,
            'planId' => null,
            'cycle' => BillingCycle::Monthly,
            'useFounderPrice' => false,
            'serviceLines' => [],
            'discount' => '0.000',
            'notes' => null,
            'periodStart' => null,
            'issuedBy' => null,
        ], $overrides);

        return app(IssueSubscriptionInvoiceAction::class)->execute(new IssueSubscriptionInvoiceDTO(
            $tenantId,
            $args['type'],
            $args['planId'],
            $args['cycle'],
            $args['useFounderPrice'],
            $args['serviceLines'],
            $args['discount'],
            $args['notes'],
            $args['periodStart'],
            $args['issuedBy'],
        ));
    }

    public function test_zero_tax_rate_by_default(): void
    {
        $plan = $this->plan(['price_monthly' => '449.000']);
        $tenant = $this->tenantOn($plan);

        $invoice = $this->issue($tenant->getKey(), ['planId' => $plan->id]);

        $this->assertSame('449.000', $invoice->subtotal);
        $this->assertSame('0.000', $invoice->tax_rate);
        $this->assertSame('0.000', $invoice->tax);
        $this->assertSame('449.000', $invoice->total);
        $this->assertMatchesRegularExpression('/^SUB-\d{4}-00001$/', $invoice->number);
    }

    public function test_vat_14_is_computed_once_on_the_net_and_rounded_half_up(): void
    {
        $this->setTaxRate('14');
        // 10.125 x 14% = 1.4175 -> half-up 1.418 (truncation would give 1.417).
        $plan = $this->plan(['price_monthly' => '10.125']);
        $tenant = $this->tenantOn($plan);

        $invoice = $this->issue($tenant->getKey(), ['planId' => $plan->id]);

        $this->assertSame('14.000', $invoice->tax_rate);
        $this->assertSame('10.125', $invoice->subtotal);
        $this->assertSame('1.418', $invoice->tax);
        $this->assertSame('11.543', $invoice->total);
    }

    public function test_vat_applies_to_the_net_after_discount_not_per_line(): void
    {
        $this->setTaxRate('14');
        $plan = $this->plan(['price_monthly' => '100.005']);
        $tenant = $this->tenantOn($plan);

        // Lines: plan 100.005 + service 50.000 = 150.005; discount 20.000 -> net 130.005.
        // 130.005 x 14% = 18.2007 -> 18.201 ; one rounding on the net, not one per line.
        $invoice = $this->issue($tenant->getKey(), [
            'planId' => $plan->id,
            'serviceLines' => [['description' => 'Training', 'amount' => '50.000']],
            'discount' => '20.000',
        ]);

        $this->assertSame('150.005', $invoice->subtotal);
        $this->assertSame('20.000', $invoice->discount);
        $this->assertSame('18.201', $invoice->tax);
        $this->assertSame('148.206', $invoice->total);
        $this->assertCount(2, $invoice->lines);
    }

    public function test_an_exact_tax_value_is_kept_at_three_decimals(): void
    {
        $this->setTaxRate('0.500');
        // 1.000 x 0.5% = 0.005 exactly.
        $plan = $this->plan(['price_monthly' => '1.000']);
        $tenant = $this->tenantOn($plan);

        $invoice = $this->issue($tenant->getKey(), ['planId' => $plan->id]);

        $this->assertSame('0.005', $invoice->tax);
        $this->assertSame('1.005', $invoice->total);
    }

    public function test_a_service_only_invoice_is_valid_and_has_no_cycle(): void
    {
        $tenant = $this->createTenant();

        $invoice = $this->issue($tenant->getKey(), [
            'type' => BillingInvoiceType::Service,
            'cycle' => null,
            'serviceLines' => [['description' => 'On-site setup', 'amount' => '250.000']],
        ]);

        $this->assertSame('250.000', $invoice->total);
        $this->assertNull($invoice->billing_cycle);
        $this->assertNull($invoice->period_end);
        $this->assertSame('pending', $invoice->status->value);
    }

    public function test_a_discount_that_wipes_the_invoice_is_refused(): void
    {
        $plan = $this->plan(['price_monthly' => '100.000']);
        $tenant = $this->tenantOn($plan);

        $this->expectException(BillingException::class);

        $this->issue($tenant->getKey(), ['planId' => $plan->id, 'discount' => '100.000']);
    }

    public function test_numbers_are_consecutive_and_the_number_is_not_lost_on_a_refused_invoice(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan);

        $first = $this->issue($tenant->getKey(), ['planId' => $plan->id]);

        try {
            $this->issue($tenant->getKey(), ['planId' => $plan->id]);
            $this->fail('A duplicate must be refused.');
        } catch (BillingException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $second = $this->issue($tenant->getKey(), ['type' => BillingInvoiceType::Service, 'cycle' => null, 'serviceLines' => [['description' => 'X', 'amount' => '1.000']]]);

        $this->assertStringEndsWith('-00001', $first->number);
        $this->assertStringEndsWith('-00002', $second->number);
    }
}
