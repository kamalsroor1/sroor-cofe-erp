<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\ActivateSubscriptionAction;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralAuditEvent;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingSequence;
use App\Models\CentralAuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use Carbon\CarbonInterface;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SELL-06: `billing:issue-trial-invoices` issues a trial's first plan invoice 7 days before the
 * trial ends, exactly once per tenant and period, never for suspended / archived tenants or
 * free plans, and writes nothing on --dry-run.
 */
final class IssueTrialEndInvoicesCommandTest extends BillingTestCase
{
    private const COMMAND = 'billing:issue-trial-invoices';

    private Plan $basic;

    private Plan $free;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlansAndFeaturesSeeder::class);
        $this->basic = Plan::query()->where('slug', 'basic')->sole();
        $this->free = Plan::query()->where('slug', 'free')->sole();
        Carbon::setTestNow(Carbon::now()->startOfSecond());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_issues_one_plan_invoice_seven_days_before_the_trial_end_and_is_idempotent(): void
    {
        $trialEnd = now()->addDays(7);
        $tenant = $this->trialTenant($this->basic, $trialEnd);

        $this->artisan(self::COMMAND)->assertSuccessful();

        $invoice = BillingInvoice::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame('plan', $invoice->type->value);
        $this->assertSame('pending', $invoice->status->value);
        $this->assertSame('monthly', $invoice->billing_cycle?->value);
        $this->assertSame($trialEnd->toIso8601String(), $invoice->period_start?->toIso8601String());
        // PRICE-2026-11: a founder slot is free, so the trial converts at the year-1 founder price.
        $this->assertSame('349.000', $invoice->subtotal);
        $this->assertSame('349.000', $invoice->total);
        $this->assertSame((int) $this->basic->id, (int) $invoice->lines[0]['plan_id']);
        $this->assertSame('founder', $invoice->lines[0]['price_step']);
        $this->assertTrue($invoice->lines[0]['founder']);
        $this->assertSame(0, app(FounderPricingService::class)->claimedSlots(), 'Issuing never takes a slot.');
        $this->assertNull($invoice->issued_by);
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingInvoiceIssued->value)->count());

        // Re-runs (same day, next days) never add a second invoice for the period.
        $this->artisan(self::COMMAND)->assertSuccessful();
        $this->travel(1)->days();
        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertSame(1, BillingInvoice::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_it_waits_until_seven_days_before_the_trial_end(): void
    {
        $inFive = $this->trialTenant($this->basic, now()->addDays(9));
        $justOutside = $this->trialTenant($this->basic, now()->addDays(7)->addMinute());
        $inOne = $this->trialTenant($this->basic, now()->addDay());

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertSame(0, BillingInvoice::query()->where('tenant_id', $inFive->id)->count());
        $this->assertSame(0, BillingInvoice::query()->where('tenant_id', $justOutside->id)->count());
        $this->assertSame(1, BillingInvoice::query()->where('tenant_id', $inOne->id)->count());

        $this->travel(2)->days();
        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertSame(1, BillingInvoice::query()->where('tenant_id', $inFive->id)->count());
        $this->assertSame(1, BillingInvoice::query()->where('tenant_id', $inOne->id)->count());
    }

    public function test_it_skips_suspended_archived_purged_free_and_planless_tenants(): void
    {
        $soon = now()->addDays(2);
        $suspended = $this->trialTenant($this->basic, $soon, ['status' => 'suspended', 'suspension_reason' => 'non_payment']);
        $archived = $this->trialTenant($this->basic, $soon, ['status' => 'archived']);
        $purged = $this->trialTenant($this->basic, $soon, ['purged_at' => now()]);
        $free = $this->trialTenant($this->free, $soon);
        $planless = $this->trialTenant(null, $soon);

        $this->artisan(self::COMMAND)
            ->expectsOutputToContain((string) __('console.trial_invoices.reasons.free_plan'))
            ->expectsOutputToContain((string) __('console.trial_invoices.reasons.no_plan'))
            ->assertSuccessful();

        foreach ([$suspended, $archived, $purged, $free, $planless] as $tenant) {
            $this->assertSame(0, BillingInvoice::query()->where('tenant_id', $tenant->id)->count(), (string) $tenant->id);
        }
    }

    public function test_dry_run_writes_nothing(): void
    {
        $tenant = $this->trialTenant($this->basic, now()->addDays(2));
        // billing_sequences already holds the founder slot counter row (a migration seeds it).
        $sequences = DB::table('billing_sequences')->orderBy('id')->get()->toArray();

        $this->artisan(self::COMMAND, ['--dry-run' => true])
            ->expectsOutputToContain($tenant->id)
            ->assertSuccessful();

        $this->assertSame(0, BillingInvoice::query()->count());
        $this->assertEquals($sequences, DB::table('billing_sequences')->orderBy('id')->get()->toArray());
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingInvoiceIssued->value)->count());
    }

    public function test_a_voided_or_manually_issued_invoice_is_not_duplicated(): void
    {
        $trialEnd = now()->addDays(2);
        $voided = $this->trialTenant($this->basic, $trialEnd);
        $this->pendingInvoice($voided, ['type' => 'plan', 'status' => 'void', 'period_start' => $trialEnd]);
        $manual = $this->trialTenant($this->basic, $trialEnd);
        $this->pendingInvoice($manual, ['type' => 'renewal', 'period_start' => now()->startOfDay()]);

        $this->artisan(self::COMMAND)
            ->expectsOutputToContain((string) __('console.trial_invoices.reasons.already_issued'))
            ->expectsOutputToContain((string) __('console.trial_invoices.reasons.open_invoice'))
            ->assertSuccessful();

        $this->assertSame(1, BillingInvoice::query()->where('tenant_id', $voided->id)->count());
        $this->assertSame(1, BillingInvoice::query()->where('tenant_id', $manual->id)->count());
    }

    public function test_it_writes_to_the_central_database_even_when_started_inside_a_tenant(): void
    {
        $shop = $this->createTenant();
        $trial = $this->trialTenant($this->basic, now()->addDays(2));
        tenancy()->initialize($shop);

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertFalse(tenancy()->initialized);
        $this->assertSame(1, BillingInvoice::query()->where('tenant_id', $trial->id)->count());
        $this->assertSame((string) config('tenancy.database.central_connection'), (new BillingInvoice)->getConnectionName());
    }

    public function test_it_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e): bool => str_contains((string) $e->command, self::COMMAND));

        $this->assertNotNull($event, self::COMMAND.' must be scheduled.');
        $this->assertSame('10 1 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    /**
     * A central-only trial tenant (no database: the command never opens one) with a trialing subscription.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function test_without_a_free_founder_slot_the_trial_invoice_is_at_list_price(): void
    {
        $this->takeAllFounderSlots();
        $tenant = $this->trialTenant($this->basic, now()->addDays(3));

        $this->artisan(self::COMMAND)->assertSuccessful();

        $invoice = BillingInvoice::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame('499.000', $invoice->subtotal);
        $this->assertSame('list', $invoice->lines[0]['price_step']);
        $this->assertFalse($invoice->lines[0]['founder']);
    }

    public function test_a_plan_without_a_founder_price_for_the_cycle_is_invoiced_at_list_price(): void
    {
        $this->basic->forceFill(['founder_price_monthly' => null])->save();
        $tenant = $this->trialTenant($this->basic, now()->addDays(3));

        $this->artisan(self::COMMAND)->assertSuccessful();

        $invoice = BillingInvoice::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame('499.000', $invoice->subtotal);
        $this->assertSame('list', $invoice->lines[0]['price_step']);
    }

    public function test_a_founder_invoice_paid_after_the_slots_ran_out_is_honoured_without_making_a_founder(): void
    {
        $tenant = $this->trialTenant($this->basic, now()->addDays(3));
        $this->artisan(self::COMMAND)->assertSuccessful();
        $invoice = BillingInvoice::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame('349.000', $invoice->total);

        // The last slot goes to someone else before this shop pays.
        $this->takeAllFounderSlots();

        $payment = BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->id, 'tenant_id' => $tenant->id, 'amount' => $invoice->total,
            'method' => BillingPaymentMethod::Instapay, 'status' => BillingPaymentStatus::Verified,
            'verified_at' => now(), 'paid_at' => now(),
        ]);
        $subscription = DB::connection($this->centralConnectionName())->transaction(
            fn (): Subscription => app(ActivateSubscriptionAction::class)->execute($invoice, $payment),
        );

        $this->assertSame('349.000', $subscription->amount, 'The issued price is honoured for the paid term.');
        $this->assertFalse($subscription->refresh()->is_founder, 'No slot left: the shop is not a founder.');
        $this->assertSame(app(FounderPricingService::class)->totalSlots(), app(FounderPricingService::class)->claimedSlots(), 'The cap is never exceeded.');
        $this->assertSame('499.000', app(FounderPricingService::class)->planPrice($subscription), 'Later cycles are at list price.');
    }

    private function takeAllFounderSlots(): void
    {
        BillingSequence::query()->updateOrCreate(
            ['key' => FounderPricingService::SLOT_SEQUENCE, 'period' => ''],
            ['last_value' => app(FounderPricingService::class)->totalSlots()],
        );
    }

    private function trialTenant(?Plan $plan, CarbonInterface $trialEndsAt, array $attributes = []): Tenant
    {
        $id = 'trial-'.Str::lower(Str::random(10));

        /** @var Tenant $tenant */
        $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create(array_merge([
            'id' => $id,
            'name' => 'محل '.$id,
            'slug' => $id,
            'email' => $id.'@trial.test',
            'plan_id' => $plan?->id,
            'status' => 'trial',
            'status_changed_at' => now(),
            'trial_ends_at' => $trialEndsAt,
            'subscription_ends_at' => null,
        ], $attributes)));

        if ($plan instanceof Plan) {
            Subscription::query()->create([
                'tenant_id' => $id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
                'status' => SubscriptionStatus::Trialing, 'amount' => $plan->price_monthly, 'currency' => 'EGP',
                'starts_at' => now(), 'ends_at' => $trialEndsAt,
            ]);
        }

        return $tenant;
    }
}
