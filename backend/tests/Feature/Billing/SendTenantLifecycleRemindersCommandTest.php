<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Tenants\NotifyTenantOfLifecycleReminderAction;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantLifecycleReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * SELL-02: `tenants:send-lifecycle-reminders` sends each trial / subscription expiry reminder
 * once (central record + in-app notification in THAT tenant's DB + one operator Telegram
 * summary), ignores blocked tenants, survives a Telegram failure and writes nothing on --dry-run.
 */
final class SendTenantLifecycleRemindersCommandTest extends BillingTestCase
{
    private const COMMAND = 'tenants:send-lifecycle-reminders';

    private const TELEGRAM = 'https://api.telegram.org/*';

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed instant in the app timezone (Africa/Cairo): calendar-day maths is deterministic.
        Carbon::setTestNow(Carbon::parse('2026-11-12 09:00:00', (string) config('app.timezone')));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 09:00:00', (string) config('app.timezone')));
        config([
            'services.telegram.bot_token' => 'dummy-test-token',
            'services.telegram.chat_id' => '-100000',
            'signup.notify.telegram_chat_id' => null,
        ]);
        $this->plan = $this->plan(['name' => 'باقة الاختبار']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_trial_ending_in_three_days_gets_exactly_one_reminder_and_a_second_run_sends_nothing(): void
    {
        Http::fake([self::TELEGRAM => Http::response(['ok' => true])]);
        $tenant = $this->trialTenant(now()->addDays(3), ['name' => 'محمصة الاختبار', 'phone' => '01000000999']);

        $this->artisan(self::COMMAND)->assertSuccessful();

        $row = TenantLifecycleReminder::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame('trial.d3', $row->kind);
        $this->assertSame('2026-11-15', $row->target_date->toDateString());
        $this->assertNotNull($row->tenant_notified_at);
        $this->assertNotNull($row->operator_notified_at);
        $this->assertSame(1, $row->recipients);

        $notifications = $this->notificationsOf($tenant);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotifyTenantOfLifecycleReminderAction::TYPE, $notifications[0]->type);
        $this->assertSame($this->tenantAdmin($tenant)->id, (int) $notifications[0]->notifiable_id);
        $data = json_decode((string) $notifications[0]->data, true);
        $this->assertSame(['trial', 'd3', 3, '2026-11-15', '/billing'], [$data['kind'], $data['stage'], $data['days_left'], $data['ends_on'], $data['action_path']]);

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains((string) $request['text'], 'محمصة الاختبار')
            && str_contains((string) $request['text'], 'باقة الاختبار')
            && str_contains((string) $request['text'], '01000000999')
            && str_contains((string) $request['text'], '2026-11-15'));

        // Same day again: nothing new anywhere.
        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertSame(1, TenantLifecycleReminder::query()->where('tenant_id', $tenant->id)->count());
        $this->assertCount(1, $this->notificationsOf($tenant));
        Http::assertSentCount(1);
    }

    public function test_billing_users_are_notified_and_cashiers_are_not(): void
    {
        Http::fake([self::TELEGRAM => Http::response(['ok' => true])]);
        $tenant = $this->trialTenant(now()->addDay());
        $cashier = $this->createTenantUser($tenant, role: 'cashier');
        $biller = $this->createTenantUser($tenant, permissions: ['billing.manage']);

        $this->artisan(self::COMMAND)->assertSuccessful();

        $ids = array_map(static fn (object $row): int => (int) $row->notifiable_id, $this->notificationsOf($tenant));
        sort($ids);
        $this->assertSame([$this->tenantAdmin($tenant)->id, $biller->id], $ids);
        $this->assertNotContains($cashier->id, $ids);
        $this->assertSame('trial.d1', TenantLifecycleReminder::query()->where('tenant_id', $tenant->id)->value('kind'));
    }

    public function test_tenants_outside_the_reminder_days_get_nothing_and_no_telegram_is_sent(): void
    {
        Http::fake([self::TELEGRAM => Http::response(['ok' => true])]);
        $inFive = $this->trialTenant(now()->addDays(5));
        $healthy = $this->createTenant(['subscription_ends_at' => now()->addDays(20)]);

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertSame(0, TenantLifecycleReminder::query()->count());
        $this->assertCount(0, $this->notificationsOf($inFive));
        $this->assertCount(0, $this->notificationsOf($healthy));
        Http::assertNothingSent();
    }

    public function test_trial_ended_yesterday_gets_the_grace_reminder_and_today_gets_the_end_day_reminder(): void
    {
        Http::fake([self::TELEGRAM => Http::response(['ok' => true])]);
        $grace = $this->trialTenant(now()->subDay()->setTime(15, 0));
        $today = $this->trialTenant(now()->setTime(20, 0));

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertSame('trial.grace', TenantLifecycleReminder::query()->where('tenant_id', $grace->id)->value('kind'));
        $this->assertSame('trial.today', TenantLifecycleReminder::query()->where('tenant_id', $today->id)->value('kind'));
        $this->assertCount(1, $this->notificationsOf($grace));
        $this->assertCount(1, $this->notificationsOf($today));
        // Both shops in ONE operator message.
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains((string) $request['text'], $grace->id)
            && str_contains((string) $request['text'], $today->id));
    }

    public function test_paid_subscription_ending_in_seven_days_is_reminded(): void
    {
        Http::fake([self::TELEGRAM => Http::response(['ok' => true])]);
        $tenant = $this->createTenant([
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'trial_ends_at' => null,
            'subscription_ends_at' => now()->addDays(7),
        ]);

        $this->artisan(self::COMMAND)->assertSuccessful();

        $row = TenantLifecycleReminder::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame('subscription.d7', $row->kind);
        $this->assertSame('2026-11-19', $row->target_date->toDateString());
        $this->assertCount(1, $this->notificationsOf($tenant));
    }

    public function test_suspended_and_archived_tenants_are_ignored(): void
    {
        Http::fake([self::TELEGRAM => Http::response(['ok' => true])]);
        $suspended = $this->trialTenant(now()->addDays(3), ['status' => 'suspended', 'suspension_reason' => 'non_payment']);
        $archived = $this->trialTenant(now()->addDays(3), ['status' => 'archived']);

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertSame(0, TenantLifecycleReminder::query()->count());
        $this->assertCount(0, $this->notificationsOf($suspended));
        $this->assertCount(0, $this->notificationsOf($archived));
        Http::assertNothingSent();
    }

    public function test_the_notification_exists_only_in_the_due_tenants_database(): void
    {
        Http::fake([self::TELEGRAM => Http::response(['ok' => true])]);
        $due = $this->trialTenant(now()->addDays(3));
        $other = $this->trialTenant(now()->addDays(9));

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertCount(1, $this->notificationsOf($due));
        $this->assertCount(0, $this->notificationsOf($other));
        $this->assertSame([$due->id], TenantLifecycleReminder::query()->pluck('tenant_id')->all());
        $this->assertFalse(tenancy()->initialized, 'The command must leave the central context restored.');
    }

    public function test_a_telegram_failure_keeps_the_tenant_notifications_and_is_retried_by_the_next_run(): void
    {
        Http::fake([self::TELEGRAM => Http::sequence()->push(['ok' => false], 500)->push(['ok' => true])]);
        $tenant = $this->trialTenant(now()->addDays(3));

        $this->artisan(self::COMMAND)
            ->expectsOutputToContain((string) __('console.lifecycle_reminders.operator.failed'))
            ->assertSuccessful();

        $row = TenantLifecycleReminder::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertNotNull($row->tenant_notified_at);
        $this->assertNull($row->operator_notified_at, 'A failed Telegram send must not be marked as sent.');
        $this->assertCount(1, $this->notificationsOf($tenant));

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertNotNull($row->fresh()?->operator_notified_at);
        $this->assertCount(1, $this->notificationsOf($tenant), 'The retry must not notify the shop twice.');
        Http::assertSentCount(2);
    }

    public function test_a_telegram_exception_does_not_break_the_run(): void
    {
        Http::fake(fn () => throw new \RuntimeException('connection refused to the bot url'));
        $tenant = $this->trialTenant(now()->addDays(3));

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertNotNull(TenantLifecycleReminder::query()->where('tenant_id', $tenant->id)->value('tenant_notified_at'));
        $this->assertCount(1, $this->notificationsOf($tenant));
    }

    public function test_a_lost_mark_does_not_notify_the_shop_twice(): void
    {
        Http::fake([self::TELEGRAM => Http::response(['ok' => true])]);
        $tenant = $this->trialTenant(now()->addDays(3));

        $this->artisan(self::COMMAND)->assertSuccessful();
        // Simulates a crash between the tenant write and the central stamp.
        TenantLifecycleReminder::query()->where('tenant_id', $tenant->id)->update(['tenant_notified_at' => null]);

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertCount(1, $this->notificationsOf($tenant));
        $this->assertNotNull(TenantLifecycleReminder::query()->where('tenant_id', $tenant->id)->value('tenant_notified_at'));
    }

    public function test_dry_run_writes_and_sends_nothing(): void
    {
        Http::fake([self::TELEGRAM => Http::response(['ok' => true])]);
        $tenant = $this->trialTenant(now()->addDays(3));

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--dry-run' => true]));
        $output = Artisan::output();

        // The tenant's row says "would send", and the run says nothing was sent or recorded.
        $row = collect(explode("\n", $output))->first(fn (string $line): bool => str_contains($line, $tenant->id));
        $this->assertNotNull($row, $output);
        $this->assertStringContainsString((string) __('console.lifecycle_reminders.statuses.would_send'), $row);
        $this->assertStringContainsString((string) __('console.lifecycle_reminders.operator.dry_run'), $output);

        $this->assertSame(0, TenantLifecycleReminder::query()->count());
        $this->assertCount(0, $this->notificationsOf($tenant));
        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_to_telegram_when_it_is_not_configured(): void
    {
        Http::fake();
        config(['services.telegram.bot_token' => '']);
        $tenant = $this->trialTenant(now()->addDays(3));

        $this->artisan(self::COMMAND)->assertSuccessful();

        Http::assertNothingSent();
        $this->assertCount(1, $this->notificationsOf($tenant));
        $this->assertNull(TenantLifecycleReminder::query()->where('tenant_id', $tenant->id)->value('operator_notified_at'));
    }

    public function test_a_tenant_whose_database_cannot_be_opened_does_not_stop_the_others(): void
    {
        Http::fake([self::TELEGRAM => Http::response(['ok' => true])]);
        // A has a central row but no database: tenancy()->initialize() throws for it.
        $broken = $this->centralOnlyTenant('a-broken', now()->addDays(3));
        $healthy = $this->trialTenant(now()->addDays(3));

        $this->artisan(self::COMMAND)->assertFailed();

        $this->assertFalse(tenancy()->initialized, 'A failed initialise must leave the central context.');
        $this->assertNull(TenantLifecycleReminder::query()->where('tenant_id', $broken->id)->value('tenant_notified_at'));
        $this->assertNotNull(TenantLifecycleReminder::query()->where('tenant_id', $healthy->id)->value('tenant_notified_at'));
        $this->assertCount(1, $this->notificationsOf($healthy));
        Http::assertSentCount(1);
    }

    public function test_a_failed_central_stamp_is_retried_and_does_not_stop_the_loop(): void
    {
        Http::fake([self::TELEGRAM => Http::response(['ok' => true])]);
        $first = $this->trialTenant(now()->addDays(3));
        $second = $this->trialTenant(now()->addDays(3));
        $failing = TenantLifecycleReminder::query()->getConnection();
        // sqlite: refuse the tenant_notified_at stamp of the FIRST tenant only.
        $failing->statement("CREATE TRIGGER sell02_refuse_stamp BEFORE UPDATE OF tenant_notified_at ON tenant_lifecycle_reminders
            WHEN NEW.tenant_id = '{$first->id}' AND NEW.tenant_notified_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'refused'); END");

        $this->artisan(self::COMMAND)->assertFailed();

        $this->assertNull(TenantLifecycleReminder::query()->where('tenant_id', $first->id)->value('tenant_notified_at'));
        $this->assertNotNull(TenantLifecycleReminder::query()->where('tenant_id', $second->id)->value('tenant_notified_at'));
        $this->assertCount(1, $this->notificationsOf($first));
        $this->assertCount(1, $this->notificationsOf($second));
        // The digest still went out, for both.
        Http::assertSentCount(1);
        $this->assertSame(2, TenantLifecycleReminder::query()->whereNotNull('operator_notified_at')->count());

        $failing->statement('DROP TRIGGER sell02_refuse_stamp');
        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertNotNull(TenantLifecycleReminder::query()->where('tenant_id', $first->id)->value('tenant_notified_at'));
        $this->assertCount(1, $this->notificationsOf($first), 'The retry must not notify the shop twice.');
        Http::assertSentCount(1);
    }

    public function test_a_long_digest_is_split_and_each_part_stamps_only_the_shops_it_lists(): void
    {
        // 2nd part refused, then accepted on the retry run.
        Http::fake([self::TELEGRAM => Http::sequence()
            ->push(['ok' => true])
            ->push(['ok' => false], 500)
            ->whenEmpty(Http::response(['ok' => true]))]);
        $ids = [];
        for ($i = 0; $i < 30; $i++) {
            // Central-only shops (the in-app write fails for them; the operator digest still lists them).
            $ids[] = $this->centralOnlyTenant(sprintf('long-%02d', $i), now()->addDays(3), str_repeat('محل طويل الاسم ', 10))->id;
        }

        $this->artisan(self::COMMAND)->assertFailed();

        $sent = [];
        Http::assertSent(function (HttpRequest $request) use (&$sent): bool {
            $sent[] = (string) $request['text'];

            return true;
        });
        $this->assertGreaterThanOrEqual(2, count($sent), 'The digest must be split.');
        foreach ($sent as $text) {
            $this->assertLessThanOrEqual(3800, mb_strlen($text));
        }

        $listedInFirst = array_values(array_filter($ids, static fn (string $id): bool => str_contains($sent[0], "({$id})")));
        $this->assertNotEmpty($listedInFirst);
        $stamped = TenantLifecycleReminder::query()->whereNotNull('operator_notified_at')->pluck('tenant_id')->sort()->values()->all();
        $this->assertSame($listedInFirst, $stamped, 'Only the shops in the accepted part are stamped.');

        // Next run: the rest goes out, nobody is listed twice.
        // Still "failed": these central-only shops have no database for the in-app notification.
        $this->artisan(self::COMMAND)->assertFailed();
        $this->assertSame(30, TenantLifecycleReminder::query()->whereNotNull('operator_notified_at')->count());
        $all = [];
        Http::assertSent(function (HttpRequest $request) use (&$all): bool {
            $all[] = (string) $request['text'];

            return true;
        });
        foreach ($ids as $id) {
            $accepted = array_filter($all, static fn (string $text, int $i): bool => $i !== 1 && str_contains($text, "({$id})"), ARRAY_FILTER_USE_BOTH);
            $this->assertCount(1, $accepted, "Shop {$id} must be in exactly one accepted message.");
        }
    }

    public function test_it_is_scheduled_daily_in_the_morning_on_one_server(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e): bool => str_contains((string) $e->command, self::COMMAND));

        $this->assertNotNull($event, self::COMMAND.' must be scheduled.');
        $this->assertSame('0 9 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    public function test_reminder_model_is_pinned_to_the_central_connection(): void
    {
        $tenant = $this->createTenant();
        $central = (string) config('tenancy.database.central_connection');

        $this->assertSame($central, (new TenantLifecycleReminder)->getConnectionName());
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => TenantLifecycleReminder::query()->count()));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function trialTenant(Carbon $trialEndsAt, array $attributes = []): Tenant
    {
        return $this->createTenant(array_merge([
            'plan_id' => $this->plan->id,
            'status' => 'trial',
            'status_changed_at' => now()->subDays(10),
            'trial_ends_at' => $trialEndsAt,
            'subscription_ends_at' => null,
        ], $attributes));
    }

    /** A central `tenants` row with NO database (initialise fails for it), ids sort by $suffix. */
    private function centralOnlyTenant(string $suffix, Carbon $trialEndsAt, ?string $name = null): Tenant
    {
        $id = 'sell02-'.$suffix;

        /** @var Tenant $tenant */
        $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
            'id' => $id,
            'name' => $name ?? 'محل '.$id,
            'slug' => $id,
            'email' => $id.'@trial.test',
            'plan_id' => $this->plan->id,
            'status' => 'trial',
            'status_changed_at' => now()->subDays(10),
            'trial_ends_at' => $trialEndsAt,
            'subscription_ends_at' => null,
        ]));

        return $tenant;
    }

    /**
     * @return list<object>
     */
    private function notificationsOf(Tenant $tenant): array
    {
        return $this->inTenant($tenant, fn (): array => DB::table('notifications')->orderBy('notifiable_id')->get()->all());
    }
}
