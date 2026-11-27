<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\SubscriptionAddonStatus;
use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;

/**
 * #232 UI spec 1.3: GET /api/v1/billing/change-preview. Every scenario asserts that the preview
 * equals the invoice the existing flow then issues at the same frozen moment (one pricer, no
 * second formula): monthly / annual upgrade, upgrade + renewal, renewal of the same plan (with an
 * add-on), trial subscribe; founder hold; 401 / 403 / 422; tenant isolation.
 */
#[Group('billing')]
final class BillingChangePreviewApiTest extends PlanChangeTestCase
{
    private const URL = '/api/v1/billing/change-preview';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-26 12:00:00', (string) config('app.timezone')));
        $this->setTaxRate('14.000');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function preview(Tenant $tenant, array $query): TestResponse
    {
        return $this->getJson(self::URL.'?'.http_build_query($query), $this->tenantHeaders($tenant));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function issue(PlanChangeRequest $row, array $body = []): BillingInvoice
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $id = (int) $this->postJson('/api/v1/super-admin/billing/plan-change-requests/'.$row->getKey().'/invoice', $body, $headers)
            ->assertCreated()->json('data.invoice.id');

        return BillingInvoice::query()->findOrFail($id);
    }

    /**
     * The preview's amounts are the invoice's: each priced line in order, then subtotal, VAT, total.
     *
     * @param  array<string, mixed>  $preview
     */
    private function assertPreviewIsInvoice(array $preview, BillingInvoice $invoice): void
    {
        $priced = array_values(array_filter($preview['lines'], static fn (array $line): bool => $line['kind'] !== 'tax'));
        $this->assertSame(array_column($invoice->lines, 'amount'), array_column($priced, 'amount'));
        $this->assertSame($invoice->subtotal, $preview['subtotal']);
        $this->assertSame($invoice->tax, $preview['tax']);
        $this->assertSame($invoice->tax_rate, $preview['tax_rate']);
        $this->assertSame($invoice->total, $preview['total']);
    }

    private function yearlyTenant(Plan $plan, CarbonImmutable $end): Tenant
    {
        $tenant = $this->tenantOn($plan, ['status' => 'active', 'subscription_ends_at' => $end]);
        Subscription::query()->create([
            'tenant_id' => $tenant->getKey(), 'plan_id' => $plan->getKey(), 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => $plan->price_yearly, 'starts_at' => $end->subYearNoOverflow(), 'ends_at' => $end,
        ]);

        return Tenant::query()->findOrFail($tenant->getKey());
    }

    public function test_a_monthly_upgrade_only_preview_equals_the_issued_invoice(): void
    {
        $small = $this->plan(['price_monthly' => '499.000', 'name' => 'Small']);
        $big = $this->plan(['price_monthly' => '999.000', 'name' => 'Big']);
        $tenant = $this->subscribedTenant($small, 10);

        $preview = $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly', 'renew' => 0])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kind', 'upgrade')
            ->assertJsonPath('data.plan.id', $big->getKey())
            ->assertJsonPath('data.current_plan.id', $small->getKey())
            ->assertJsonPath('data.lines.0.kind', 'proration')
            ->assertJsonPath('data.lines.0.label_key', 'subscription.billing.change.line_proration')
            ->assertJsonPath('data.lines.0.params.days', 10)
            ->assertJsonPath('data.lines.0.params.period_days', 30)
            ->assertJsonPath('data.lines.0.params.new', '999.000')
            ->assertJsonPath('data.lines.0.params.old', '499.000')
            ->assertJsonPath('data.lines.0.amount', '166.667')
            ->assertJsonPath('data.lines.1.kind', 'tax')
            ->assertJsonPath('data.lines.1.params.rate', '14.000')
            ->assertJsonPath('data.lines.1.amount', '23.333')
            ->assertJsonPath('data.total', '190.000')
            ->assertJsonPath('data.currency', 'EGP')
            ->assertJsonPath('data.period_end', '2026-12-06')
            ->assertJsonPath('data.new_period_end', null)
            ->assertJsonPath('data.starts_at_payment', false)
            ->assertJsonPath('data.estimate', true)
            ->assertJsonPath('data.held_for_price_review', false)
            ->assertJsonPath('data.trial_days_policy', 'n/a')
            ->assertJsonPath('data.open_invoice', null)
            ->json('data');

        $this->assertSame(__('subscription.billing.change.line_proration', ['days' => '10', 'period_days' => '30']), $preview['lines'][0]['label']);
        $this->assertPreviewIsInvoice($preview, $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()])));
    }

    public function test_an_annual_upgrade_preview_equals_the_issued_invoice(): void
    {
        $small = $this->plan(['price_yearly' => '4990.000']);
        $big = $this->plan(['price_yearly' => '9990.000']);
        $tenant = $this->yearlyTenant($small, CarbonImmutable::parse('2027-03-06 12:00:00', (string) config('app.timezone')));

        $preview = $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'yearly'])
            ->assertOk()
            ->assertJsonPath('data.lines.0.params.days', 100)
            ->assertJsonPath('data.lines.0.params.period_days', 365)
            ->assertJsonPath('data.subtotal', '1369.863')
            ->assertJsonPath('data.tax', '191.781')
            ->assertJsonPath('data.total', '1561.644')
            ->assertJsonPath('data.period_end', '2027-03-06')
            ->json('data');

        $this->assertPreviewIsInvoice($preview, $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey(), 'billing_cycle' => 'yearly'])));
    }

    public function test_an_upgrade_plus_renewal_preview_equals_the_issued_invoice(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000', 'name' => 'Big']);
        $tenant = $this->subscribedTenant($small, 10);

        $preview = $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly', 'renew' => 1])
            ->assertOk()
            ->assertJsonPath('data.kind', 'upgrade_renewal')
            ->assertJsonPath('data.lines.0.amount', '166.667')
            ->assertJsonPath('data.lines.1.kind', 'renewal')
            ->assertJsonPath('data.lines.1.label_key', 'subscription.billing.change.line_renewal_month')
            ->assertJsonPath('data.lines.1.params.plan', 'Big')
            ->assertJsonPath('data.lines.1.amount', '999.000')
            ->assertJsonPath('data.subtotal', '1165.667')
            ->assertJsonPath('data.tax', '163.193')
            ->assertJsonPath('data.total', '1328.860')
            ->assertJsonPath('data.period_end', '2026-12-06')
            ->assertJsonPath('data.new_period_end', '2027-01-06')
            ->json('data');

        $this->assertPreviewIsInvoice($preview, $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]), ['with_renewal' => true]));
    }

    public function test_a_renewal_of_the_same_plan_with_an_addon_equals_the_issued_renewal_invoice(): void
    {
        $plan = $this->plan(['price_monthly' => '449.000', 'price_yearly' => '4490.000', 'name' => 'Pro']);
        $tenant = $this->subscribedTenant($plan, 10);
        $addon = $this->addonOn($plan);
        $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->sole();
        SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->getKey(), 'addon_id' => $addon->getKey(), 'quantity' => 2, 'unit_price' => '100.000',
            'billing_cycle' => 'monthly', 'status' => SubscriptionAddonStatus::Active, 'starts_at' => now()->subDays(20), 'ends_at' => $tenant->subscription_ends_at,
        ]);

        $preview = $this->preview($tenant, ['action' => 'renew', 'cycle' => 'yearly', 'plan_id' => 123, 'renew' => 1])
            ->assertOk()
            ->assertJsonPath('data.kind', 'renewal')
            ->assertJsonPath('data.plan.id', $plan->getKey())
            ->assertJsonPath('data.lines.0.kind', 'renewal')
            ->assertJsonPath('data.lines.0.label_key', 'subscription.billing.change.line_renewal_year')
            ->assertJsonPath('data.lines.0.amount', '4490.000')
            ->assertJsonPath('data.lines.1.kind', 'addon')
            ->assertJsonPath('data.lines.1.params.months', 12)
            ->assertJsonPath('data.lines.1.params.quantity', 2)
            ->assertJsonPath('data.period_end', '2026-12-06')
            ->assertJsonPath('data.new_period_end', '2027-12-06')
            ->assertJsonPath('data.estimate', false)
            ->json('data');

        $id = (int) $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'yearly'], $this->tenantHeaders($tenant))->assertCreated()->json('data.id');
        $this->assertPreviewIsInvoice($preview, BillingInvoice::query()->findOrFail($id));
    }

    public function test_a_monthly_renewal_preview_has_the_exact_amounts(): void
    {
        $plan = $this->plan(['price_monthly' => '449.000']);
        $tenant = $this->subscribedTenant($plan, 10);

        $preview = $this->preview($tenant, ['action' => 'renew', 'cycle' => 'monthly'])
            ->assertOk()
            // 449 + 14% (62.860) = 511.860
            ->assertJsonPath('data.subtotal', '449.000')
            ->assertJsonPath('data.tax', '62.860')
            ->assertJsonPath('data.total', '511.860')
            ->assertJsonPath('data.new_period_end', '2027-01-06')
            ->json('data');

        $id = (int) $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $this->tenantHeaders($tenant))->assertCreated()->json('data.id');
        $this->assertPreviewIsInvoice($preview, BillingInvoice::query()->findOrFail($id));
    }

    public function test_a_trial_shop_sees_a_full_plan_line_starting_at_payment_equal_to_the_issued_invoice(): void
    {
        $trialPlan = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '699.000', 'name' => 'Big']);
        $tenant = $this->tenantOn($trialPlan, ['status' => 'trial', 'trial_ends_at' => now()->addDays(9), 'subscription_ends_at' => null]);

        $preview = $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly', 'renew' => 1])
            ->assertOk()
            ->assertJsonPath('data.kind', 'subscribe')
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.0.kind', 'plan')
            ->assertJsonPath('data.lines.0.label_key', 'subscription.billing.change.line_plan')
            ->assertJsonPath('data.lines.0.amount', '999.000')
            ->assertJsonPath('data.total', '1138.860')
            ->assertJsonPath('data.period_end', null)
            ->assertJsonPath('data.new_period_end', '2026-12-26')
            ->assertJsonPath('data.starts_at_payment', true)
            ->assertJsonPath('data.trial_days_policy', 'pending')
            ->json('data');

        $this->assertPreviewIsInvoice($preview, $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()])));
    }

    public function test_a_founder_upgrade_preview_is_priced_automatically_with_founder_prices(): void
    {
        $small = $this->plan(['price_monthly' => '499.000', 'founder_price_monthly' => '299.000']);
        $big = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '699.000']);
        $tenant = $this->subscribedTenant($small, 10);
        Subscription::query()->where('tenant_id', $tenant->getKey())->update(['is_founder' => true, 'founder_price_until' => now()->addMonths(6)]);

        // (699 - 299) x 10 / 30 = 133.333; + 14% VAT (18.667) = 152.000
        $preview = $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly'])
            ->assertOk()
            ->assertJsonPath('data.held_for_price_review', false)
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.0.kind', 'proration')
            ->assertJsonPath('data.lines.0.founder', true)
            ->assertJsonPath('data.lines.0.params.old', '299.000')
            ->assertJsonPath('data.lines.0.params.new', '699.000')
            ->assertJsonPath('data.lines.0.amount', '133.333')
            ->assertJsonPath('data.subtotal', '133.333')
            ->assertJsonPath('data.tax', '18.667')
            ->assertJsonPath('data.total', '152.000')
            ->json('data');

        $this->assertPreviewIsInvoice($preview, $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()])));

        // Renewal also uses the founder schedule (299, + 14% = 340.860).
        $this->preview($tenant, ['action' => 'renew', 'cycle' => 'monthly'])
            ->assertOk()
            ->assertJsonPath('data.held_for_price_review', false)
            ->assertJsonPath('data.lines.0.founder', true)
            ->assertJsonPath('data.total', '340.860');
    }

    public function test_a_founder_downgrade_same_price_or_free_preview_returns_422_upgrade_not_higher(): void
    {
        $pro = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '699.000']);
        $basic = $this->plan(['price_monthly' => '499.000', 'founder_price_monthly' => '299.000']);
        $samePrice = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '699.000']);
        $free = $this->plan(['price_monthly' => '0.000', 'price_yearly' => '0.000', 'is_free' => true]);
        $tenant = $this->subscribedTenant($pro, 10);
        Subscription::query()->where('tenant_id', $tenant->getKey())->update(['is_founder' => true, 'founder_price_until' => now()->addMonths(6)]);

        foreach ([$basic->getKey(), $samePrice->getKey(), $free->getKey()] as $targetId) {
            $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => $targetId, 'cycle' => 'monthly'])
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'billing.upgrade_not_higher')
                ->assertJsonPath('message', __('billing.errors.upgrade_not_higher'));
        }

        $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => $pro->getKey(), 'cycle' => 'monthly'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.request_same_plan');
    }

    public function test_an_open_plan_invoice_is_reported(): void
    {
        $plan = $this->plan();
        $tenant = $this->subscribedTenant($plan, 10);
        $open = $this->pendingInvoice($tenant, ['type' => 'renewal', 'billing_cycle' => 'monthly', 'total' => '449.000']);

        $this->preview($tenant, ['action' => 'renew', 'cycle' => 'monthly'])
            ->assertOk()
            ->assertJsonPath('data.open_invoice', ['id' => $open->getKey(), 'number' => $open->number, 'total' => '449.000']);
    }

    public function test_invalid_input_is_422(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $cheaper = $this->plan(['price_monthly' => '199.000']);
        $samePrice = $this->plan(['price_monthly' => '499.000']);
        $free = $this->plan(['price_monthly' => '0.000', 'price_yearly' => '0.000', 'is_free' => true]);
        $hidden = $this->plan(['price_monthly' => '999.000', 'is_public' => false]);

        $this->preview($tenant, [])->assertStatus(422)->assertJsonValidationErrors(['action', 'cycle']);
        $this->preview($tenant, ['action' => 'downgrade', 'cycle' => 'monthly'])->assertStatus(422)->assertJsonValidationErrors(['action']);
        $this->preview($tenant, ['action' => 'upgrade', 'cycle' => 'monthly'])->assertStatus(422)->assertJsonValidationErrors(['plan_id']);
        $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => 'x', 'cycle' => 'monthly'])->assertStatus(422)->assertJsonValidationErrors(['plan_id']);
        $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => $small->getKey(), 'cycle' => 'biennial'])->assertStatus(422)->assertJsonValidationErrors(['cycle']);
        $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => $small->getKey(), 'cycle' => 'monthly', 'renew' => 'maybe'])->assertStatus(422)->assertJsonValidationErrors(['renew']);

        $codes = [
            [999999, 'billing.request_plan_unavailable'],
            [$hidden->getKey(), 'billing.request_plan_unavailable'],
            [$small->getKey(), 'billing.request_same_plan'],
            [$cheaper->getKey(), 'billing.upgrade_not_higher'],
            [$samePrice->getKey(), 'billing.upgrade_not_higher'],
            [$free->getKey(), 'billing.upgrade_not_higher'],
        ];
        foreach ($codes as [$planId, $code]) {
            $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => $planId, 'cycle' => 'monthly'])
                ->assertStatus(422)->assertJsonPath('error_code', $code);
        }

        $this->preview($tenant, ['action' => 'upgrade', 'plan_id' => $cheaper->getKey(), 'cycle' => 'monthly'])
            ->assertJsonPath('message', __('billing.errors.upgrade_not_higher'));
    }

    public function test_a_renewal_on_a_free_trial_plan_is_422(): void
    {
        $trialPlan = $this->plan(['price_monthly' => '0.000', 'price_yearly' => '0.000', 'is_free' => true]);
        $tenant = $this->tenantOn($trialPlan, ['status' => 'trial', 'trial_ends_at' => now()->addDays(5), 'subscription_ends_at' => null]);

        $this->preview($tenant, ['action' => 'renew', 'cycle' => 'monthly'])
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.renewal_not_for_trial');
    }

    public function test_it_is_401_for_guests_and_403_without_the_permission(): void
    {
        $tenant = $this->subscribedTenant($this->plan(), 10);
        $user = $this->createTenantUser($tenant, null, ['items.view']);
        $query = '?action=renew&cycle=monthly';

        $this->getJson(self::URL.$query, $this->tenantGuestHeaders($tenant))->assertUnauthorized();
        $this->getJson(self::URL.$query, $this->tenantHeaders($tenant, $user))->assertForbidden();
    }

    public function test_each_shop_is_priced_on_its_own_term_and_nothing_is_written(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $a = $this->subscribedTenant($small, 10);
        $b = $this->subscribedTenant($small, 20);
        $query = ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly'];

        $this->preview($a, $query)->assertOk()->assertJsonPath('data.lines.0.params.days', 10);
        // B: Oct 16 -> Dec 16 is clamped per cycle: Nov 16 -> Dec 16 = 30 days, 20 left -> 333.333.
        $this->preview($b, $query)->assertOk()->assertJsonPath('data.lines.0.params.days', 20)->assertJsonPath('data.subtotal', '333.333');

        $this->assertSame(0, BillingInvoice::query()->count());
        $this->assertSame(0, PlanChangeRequest::query()->count());
    }
}
