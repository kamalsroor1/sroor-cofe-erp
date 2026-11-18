<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Actions\Tenants\HandleDormantFreeShopsAction;
use App\Actions\Tenants\PurgeTenantAction;
use App\Actions\Tenants\UnarchiveTenantAction;
use App\Enums\TenantBackupKind;
use App\Enums\TenantStatus;
use App\Mail\DormantShopArchivedMail;
use App\Mail\DormantShopReminderMail;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Models\TenantBackup;
use App\Models\TenantLifecycleEvent;
use App\Models\TenantLifecycleReminder;
use App\Models\User;
use App\Services\Tenancy\TenantActivityTracker;
use Carbon\CarbonImmutable;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * FREE-15: dormant FREE shops. Activity stamping (throttled), the 60-day reminder (once per
 * dormancy, free plans only), the 90-day archive (only after a reminder at least 7 days old, data
 * kept, never purged), unarchive restarting the clock, idempotency, queued mails and locales.
 */
final class DormantFreeShopsTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const COMMAND = 'tenants:handle-dormant-free';

    private Plan $free;

    private Plan $paid;

    private string $tmp = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        Carbon::setTestNow(Carbon::parse('2026-11-19 05:00:00'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 05:00:00'));
        Mail::fake();
        Cache::flush();

        $this->free = $this->plan('free', true);
        $this->paid = $this->plan('paid', false);
        // The real backup engine (OPS-5) onto a fake disk: the archive needs a verified FINAL backup.
        Storage::fake('google');
        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-dormant-'.bin2hex(random_bytes(5));
        config([
            'backup.backup.password' => 'fixture-dormant-password-not-a-secret-0000',
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.notify_mail' => null,
            'backup.tenants.temporary_directory' => $this->tmp,
            'backup.tenants.verify_after_upload' => true,
        ]);

        // FREE-15: the purge is never reachable from this feature.
        $this->app->bind(PurgeTenantAction::class, static fn () => throw new LogicException('The purge must never run.'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------------ activity stamping

    public function test_authenticated_api_use_stamps_the_activity_at_most_once_per_throttle_window(): void
    {
        $tenant = $this->shop($this->free, null);
        $headers = $this->tenantHeaders($tenant);

        $this->getJson('/api/v1/auth/me', $headers)->assertOk();
        $first = $this->activity($tenant);
        $this->assertNotNull($first);
        $this->assertSame(now()->toDateTimeString(), $first->toDateTimeString());

        // Inside the window: no second write (proved by planting an old value the request must not touch).
        Tenant::query()->whereKey($tenant->getKey())->update(['last_activity_at' => '2026-01-01 00:00:00']);
        $this->getJson('/api/v1/auth/me', $headers)->assertOk();
        $this->assertSame('2026-01-01 00:00:00', $this->activity($tenant)?->toDateTimeString());

        // After the window: stamped again.
        $this->travel(16)->minutes();
        $this->getJson('/api/v1/auth/me', $headers)->assertOk();
        $this->assertSame(now()->toDateTimeString(), $this->activity($tenant)?->toDateTimeString());
    }

    public function test_a_login_always_stamps_the_activity_even_inside_the_window(): void
    {
        $tenant = $this->shop($this->free, null);
        $user = $this->createTenantUser($tenant, attributes: ['password' => bcrypt('secret-pass-1')]);

        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($tenant))->assertOk();
        Tenant::query()->whereKey($tenant->getKey())->update(['last_activity_at' => '2026-01-01 00:00:00']);

        $this->postJson('/api/v1/auth/login', ['login' => $user->phone, 'password' => 'secret-pass-1'], ['X-Tenant' => (string) $tenant->getKey()])->assertOk();

        $this->assertSame(now()->toDateTimeString(), $this->activity($tenant)?->toDateTimeString());
    }

    public function test_an_unauthenticated_request_stamps_nothing(): void
    {
        $tenant = $this->shop($this->free, null);

        $this->getJson('/api/v1/auth/me', $this->tenantGuestHeaders($tenant))->assertUnauthorized();

        $this->assertNull($this->activity($tenant));
    }

    // ------------------------------------------------------------ reminder at 60 days

    public function test_a_free_shop_dormant_for_60_days_gets_one_reminder_and_a_rerun_sends_nothing(): void
    {
        $tenant = $this->shop($this->free, now()->subDays(60));

        $this->artisan(self::COMMAND)->assertSuccessful();

        Mail::assertQueued(DormantShopReminderMail::class, 1);
        Mail::assertQueued(DormantShopReminderMail::class, fn (DormantShopReminderMail $mail): bool => $mail->hasTo((string) $tenant->email)
            && $mail->inactiveDays === 60
            && str_contains($mail->loginUrl, '/login')
            && str_contains($mail->loginUrl, $this->tenantDomain($tenant)));
        $row = TenantLifecycleReminder::query()->where('tenant_id', $tenant->getKey())->sole();
        $this->assertSame(HandleDormantFreeShopsAction::KIND_REMIND, $row->kind);
        $this->assertSame(TenantStatus::Active->value, $this->fresh($tenant)->status, 'A reminder never changes the status.');

        $this->artisan(self::COMMAND)->assertSuccessful();
        $this->travel(1)->days();
        $this->artisan(self::COMMAND)->assertSuccessful();

        Mail::assertQueued(DormantShopReminderMail::class, 1);
        $this->assertSame(1, TenantLifecycleReminder::query()->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_a_shop_dormant_for_59_days_is_not_reminded(): void
    {
        $this->shop($this->free, now()->subDays(59));

        $this->artisan(self::COMMAND)->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_a_shop_that_never_logged_activity_counts_from_its_creation_date(): void
    {
        $tenant = $this->shop($this->free, null);
        Tenant::query()->whereKey($tenant->getKey())->update(['created_at' => now()->subDays(75)]);

        $this->artisan(self::COMMAND)->assertSuccessful();

        Mail::assertQueued(DormantShopReminderMail::class, 1);
    }

    public function test_only_free_shops_in_active_or_read_only_status_are_touched(): void
    {
        $this->shop($this->paid, now()->subDays(200));
        $this->shop($this->free, now()->subDays(200), TenantStatus::Suspended);
        $this->shop($this->free, now()->subDays(200), TenantStatus::Cancelled);
        $this->shop($this->free, now()->subDays(200), TenantStatus::PastDue);
        $this->shop($this->free, now()->subDays(200), TenantStatus::Trial);
        $archived = $this->shop($this->free, now()->subDays(200), TenantStatus::Archived);
        $this->shop(null, now()->subDays(200));
        $readOnly = $this->shop($this->free, now()->subDays(61), TenantStatus::ReadOnly);

        $this->artisan(self::COMMAND)->assertSuccessful();

        Mail::assertQueued(DormantShopReminderMail::class, 1);
        $this->assertSame([(string) $readOnly->getKey()], TenantLifecycleReminder::query()->pluck('tenant_id')->all());
        $this->assertSame(TenantStatus::Archived->value, $this->fresh($archived)->status);
        $this->assertSame(0, TenantLifecycleEvent::query()->where('actor', 'system')->count());
        $this->assertSame(0, TenantBackup::query()->count());
    }

    public function test_the_reminder_is_sent_again_only_after_activity_resumed_and_a_new_dormancy_began(): void
    {
        $tenant = $this->shop($this->free, now()->subDays(60));
        $this->artisan(self::COMMAND)->assertSuccessful();
        Mail::assertQueued(DormantShopReminderMail::class, 1);

        // The owner came back (clock restarts), stays active, goes quiet for 60 days again.
        $this->travel(5)->days();
        $this->setActivity($tenant, now());
        $this->artisan(self::COMMAND)->assertSuccessful();
        Mail::assertQueued(DormantShopReminderMail::class, 1);

        $this->travel(61)->days();
        $this->artisan(self::COMMAND)->assertSuccessful();

        Mail::assertQueued(DormantShopReminderMail::class, 2);
        $this->assertSame(2, TenantLifecycleReminder::query()->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_the_reminder_follows_the_signup_language_and_the_default_otherwise(): void
    {
        $ar = $this->shop($this->free, now()->subDays(61));
        $en = $this->shop($this->free, now()->subDays(61));
        SignupRequest::query()->forceCreate([
            'tenant_id' => (string) $en->getKey(),
            'business_name' => 'EN shop',
            'contact_name' => 'Owner',
            'phone' => '01000000777',
            'business_type' => 'retail',
            'email' => 'en.owner@example.test',
            'locale' => 'en',
            'status' => 'provisioned',
        ]);

        $this->artisan(self::COMMAND)->assertSuccessful();

        Mail::assertQueued(DormantShopReminderMail::class, fn (DormantShopReminderMail $mail): bool => $mail->hasTo((string) $en->email) && $mail->locale === 'en');
        Mail::assertQueued(DormantShopReminderMail::class, fn (DormantShopReminderMail $mail): bool => $mail->hasTo((string) $ar->email) && $mail->locale === 'ar');
    }

    public function test_the_mails_render_in_both_locales_without_leaking_translation_keys(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $reminder = (new DormantShopReminderMail('Cafe Test', 'https://shop.test/login', 61, '2026-12-19', $locale))->render();
            $archived = (new DormantShopArchivedMail('Cafe Test', 91, $locale))->render();

            foreach ([$reminder, $archived] as $html) {
                $this->assertStringContainsString('Cafe Test', $html);
                $this->assertStringNotContainsString('mail.dormant_', $html);
            }
            $this->assertStringContainsString($locale === 'ar' ? 'dir="rtl"' : 'dir="ltr"', $reminder);
            $this->assertStringContainsString('https://shop.test/login', $reminder);
        }
    }

    // ------------------------------------------------------------ archive at 90 days

    public function test_a_shop_past_90_days_that_never_got_the_reminder_is_reminded_and_the_archive_waits(): void
    {
        $tenant = $this->shop($this->free, now()->subDays(95));

        $this->artisan(self::COMMAND)->assertSuccessful();

        Mail::assertQueued(DormantShopReminderMail::class, 1);
        Mail::assertNotQueued(DormantShopArchivedMail::class);
        $this->assertSame(TenantStatus::Active->value, $this->fresh($tenant)->status);
        $this->assertSame(0, TenantBackup::query()->count());

        // The reminder is 3 days old: still waiting.
        $this->travel(3)->days();
        $this->artisan(self::COMMAND)->assertSuccessful();
        $this->assertSame(TenantStatus::Active->value, $this->fresh($tenant)->status);

        // 7 days after the reminder: archived.
        $this->travel(4)->days();
        $this->artisan(self::COMMAND)->assertSuccessful();
        $this->assertSame(TenantStatus::Archived->value, $this->fresh($tenant)->status);
    }

    public function test_the_archive_keeps_everything_takes_a_final_backup_and_notifies_the_owner(): void
    {
        $tenant = $this->shop($this->free, now()->subDays(91));
        $this->remindedAt($tenant, now()->subDays(8));
        $user = $this->createTenantUser($tenant);

        $this->artisan(self::COMMAND)->assertSuccessful();

        $archived = $this->fresh($tenant);
        $this->assertSame(TenantStatus::Archived->value, $archived->status);
        $this->assertNotNull($archived->archived_at);
        $this->assertSame(TenantStatus::Suspended, $archived->status_before_archive);
        $this->assertNotNull($archived->final_backup_id);
        $this->assertSame(1, TenantBackup::query()->count());
        $this->assertSame(1, TenantBackup::query()->where('tenant_id', $tenant->getKey())->where('kind', TenantBackupKind::Final->value)->count());

        // No data was dropped: the tenant database and its rows are still there.
        $this->assertTrue($this->tenantDatabaseExists($tenant));
        $this->assertTrue($this->inTenant($tenant, static fn (): bool => User::query()->whereKey($user->getKey())->exists()));

        $events = TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->orderBy('id')->get();
        $this->assertSame(
            ['past_due', 'read_only', 'suspended', 'archived'],
            $events->map(static fn (TenantLifecycleEvent $e): string => $e->to_status->value)->all(),
        );
        $this->assertSame(['system'], $events->map(static fn (TenantLifecycleEvent $e): string => $e->actor->value)->unique()->values()->all());
        $this->assertSame(HandleDormantFreeShopsAction::SOURCE, $events->last()->properties['source'] ?? null);

        Mail::assertQueued(DormantShopArchivedMail::class, 1);
        Mail::assertQueued(DormantShopArchivedMail::class, fn (DormantShopArchivedMail $mail): bool => $mail->hasTo((string) $tenant->email) && $mail->inactiveDays === 91);

        // Rerun: nothing more happens to an archived shop.
        $this->artisan(self::COMMAND)->assertSuccessful();
        Mail::assertQueued(DormantShopArchivedMail::class, 1);
        $this->assertSame(1, TenantBackup::query()->count());
    }

    public function test_a_read_only_free_shop_is_archived_through_the_shorter_path(): void
    {
        $tenant = $this->shop($this->free, now()->subDays(100), TenantStatus::ReadOnly);
        $this->remindedAt($tenant, now()->subDays(10));

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertSame(TenantStatus::Archived->value, $this->fresh($tenant)->status);
        $this->assertSame(
            ['suspended', 'archived'],
            TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->orderBy('id')->get()->map(static fn (TenantLifecycleEvent $e): string => $e->to_status->value)->all(),
        );
    }

    public function test_a_failing_backup_leaves_the_shop_untouched_and_the_run_reports_failure(): void
    {
        config(['backup.tenants.disks' => []]);
        $tenant = $this->shop($this->free, now()->subDays(95));
        $this->remindedAt($tenant, now()->subDays(10));

        $this->artisan(self::COMMAND)->assertFailed();

        $this->assertSame(TenantStatus::Active->value, $this->fresh($tenant)->status);
        $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->count());
        Mail::assertNotQueued(DormantShopArchivedMail::class);
    }

    public function test_one_run_starts_at_most_the_configured_number_of_archives(): void
    {
        config(['tenant_lifecycle.dormant.max_archives_per_run' => 1]);
        $a = $this->shop($this->free, now()->subDays(95));
        $b = $this->shop($this->free, now()->subDays(95));
        $this->remindedAt($a, now()->subDays(10));
        $this->remindedAt($b, now()->subDays(10));

        $this->artisan(self::COMMAND)->assertSuccessful();

        $statuses = [$this->fresh($a)->status, $this->fresh($b)->status];
        sort($statuses);
        $this->assertSame([TenantStatus::Active->value, TenantStatus::Archived->value], $statuses);
    }

    public function test_unarchive_of_a_dormant_archived_free_shop_restores_it_straight_to_active(): void
    {
        $tenant = $this->shop($this->free, now()->subDays(95));
        $this->remindedAt($tenant, now()->subDays(10));
        $this->artisan(self::COMMAND)->assertSuccessful();
        $this->assertSame(TenantStatus::Archived->value, $this->fresh($tenant)->status);

        $operator = $this->centralSuperAdmin();
        $restored = app(UnarchiveTenantAction::class)->execute((string) $tenant->getKey(), $operator, null);

        $this->assertSame(TenantStatus::Active->value, $restored->status);
        $this->assertNull($restored->archived_at);
        $this->assertNull($restored->suspension_reason);
        $this->assertSame(now()->toDateTimeString(), $restored->last_activity_at?->toDateTimeString());
        $this->assertTrue($this->tenantDatabaseExists($tenant));
        $this->assertSame(0, $restored->dormantDays());

        $events = TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->orderBy('id')->get();
        $last = $events->slice(-2)->values();
        $this->assertSame(['suspended', 'active'], $last->map(static fn (TenantLifecycleEvent $e): string => $e->to_status->value)->all());
        $this->assertSame((int) $operator->getKey(), (int) $last[0]->central_user_id);
        $this->assertSame((int) $operator->getKey(), (int) $last[1]->central_user_id);
        $this->assertSame(UnarchiveTenantAction::RESTORE_SOURCE, $last[1]->properties['source'] ?? null);

        // The feature middleware lets the restored shop through (tokens made after the restore).
        $this->createTenantUser($tenant);
        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($tenant))->assertOk();

        // Tomorrow's runs and the lifecycle sweep leave it alone.
        $this->travel(1)->days();
        $this->artisan(self::COMMAND)->assertSuccessful();
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
        $this->assertSame(TenantStatus::Active->value, $this->fresh($tenant)->status);
        $this->assertSame($events->count(), TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_a_free_shop_archived_by_hand_is_still_restored_suspended(): void
    {
        $tenant = $this->shop($this->free, now()->subDays(10), TenantStatus::Archived);

        $restored = app(UnarchiveTenantAction::class)->execute((string) $tenant->getKey(), $this->centralSuperAdmin(), null);

        $this->assertSame(TenantStatus::Suspended->value, $restored->status);
    }

    public function test_a_paid_shop_is_still_restored_suspended(): void
    {
        $tenant = $this->shop($this->paid, now()->subDays(10), TenantStatus::Archived);
        // Even with a dormant-looking archive event, a paid plan never skips billing.
        TenantLifecycleEvent::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'from_status' => 'suspended',
            'to_status' => 'archived',
            'actor' => 'system',
            'properties' => ['source' => HandleDormantFreeShopsAction::SOURCE],
        ]);

        $restored = app(UnarchiveTenantAction::class)->execute((string) $tenant->getKey(), $this->centralSuperAdmin(), null);

        $this->assertSame(TenantStatus::Suspended->value, $restored->status);
    }

    // ------------------------------------------------------------ run behaviour

    public function test_dry_run_writes_and_sends_nothing(): void
    {
        $remind = $this->shop($this->free, now()->subDays(61));
        $archive = $this->shop($this->free, now()->subDays(95));
        $this->remindedAt($archive, now()->subDays(10));

        $this->artisan(self::COMMAND, ['--dry-run' => true])
            ->expectsOutputToContain((string) $remind->getKey())
            ->expectsOutputToContain((string) $archive->getKey())
            ->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertSame(1, TenantLifecycleReminder::query()->count());
        $this->assertSame(TenantStatus::Active->value, $this->fresh($archive)->status);
        $this->assertSame(0, TenantBackup::query()->count());
    }

    public function test_the_command_does_nothing_when_disabled(): void
    {
        config(['tenant_lifecycle.dormant.enabled' => false]);
        $this->shop($this->free, now()->subDays(200));

        $this->artisan(self::COMMAND)->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertSame(0, TenantLifecycleReminder::query()->count());
    }

    public function test_an_overlapping_run_is_a_no_op(): void
    {
        $this->shop($this->free, now()->subDays(61));
        $lock = Cache::lock('tenants:handle-dormant-free', 60);
        $this->assertTrue($lock->get());

        $this->artisan(self::COMMAND)->assertSuccessful();

        Mail::assertNothingQueued();
        $lock->release();
    }

    public function test_a_misconfigured_archive_threshold_can_never_archive_before_reminding(): void
    {
        config(['tenant_lifecycle.dormant.archive_after_days' => 10]);
        $tenant = $this->shop($this->free, now()->subDays(65));

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertSame(TenantStatus::Active->value, $this->fresh($tenant)->status);
        Mail::assertQueued(DormantShopReminderMail::class, 1);
    }

    public function test_the_command_is_scheduled_daily_and_the_feature_never_imports_the_purge(): void
    {
        $this->app->make(Kernel::class)->bootstrap();
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(static fn ($event): bool => str_contains((string) $event->command, self::COMMAND));

        $this->assertCount(1, $events);
        $this->assertSame('0 5 * * *', $events->first()->expression);

        foreach (['Actions/Tenants/HandleDormantFreeShopsAction.php', 'Console/Commands/HandleDormantFreeShopsCommand.php'] as $file) {
            $code = (string) file_get_contents(app_path($file));
            $this->assertStringNotContainsString('PurgeTenantAction;', $code);
            $this->assertStringNotContainsString('->deleteDatabase(', $code);
        }
    }

    // ------------------------------------------------------------ console visibility

    public function test_the_super_admin_tenant_list_exposes_the_activity_and_dormant_days_and_filters_by_dormancy(): void
    {
        $idle = $this->shop($this->free, now()->subDays(40));
        $fresh = $this->shop($this->free, now()->subDays(2));
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $all = $this->getJson('/api/v1/super-admin/tenants', $headers)->assertOk();
        $rows = collect($all->json('tenants.data'))->keyBy('id');
        $this->assertSame(40, $rows[(string) $idle->getKey()]['dormant_days']);
        $this->assertSame(2, $rows[(string) $fresh->getKey()]['dormant_days']);
        $this->assertNotNull($rows[(string) $idle->getKey()]['last_activity_at']);

        $filtered = $this->getJson('/api/v1/super-admin/tenants?dormant_days=30', $headers)->assertOk();
        $ids = collect($filtered->json('tenants.data'))->pluck('id')->all();
        $this->assertContains((string) $idle->getKey(), $ids);
        $this->assertNotContains((string) $fresh->getKey(), $ids);

        $this->getJson('/api/v1/super-admin/tenants?dormant_days=30')->assertUnauthorized();
    }

    // ------------------------------------------------------------ review hardening

    public function test_a_request_inside_the_throttle_window_writes_nothing_to_the_cache_or_the_database(): void
    {
        $tenant = $this->shop($this->free, null);
        $headers = $this->tenantHeaders($tenant);
        $this->getJson('/api/v1/auth/me', $headers)->assertOk();

        $writes = 0;
        Event::listen(KeyWritten::class, static function () use (&$writes): void {
            $writes++;
        });
        DB::connection((new Tenant)->getConnectionName())->enableQueryLog();
        DB::connection((new Tenant)->getConnectionName())->flushQueryLog();

        app(TenantActivityTracker::class)->touch($tenant);

        $this->assertSame(0, $writes);
        $updates = collect(DB::connection((new Tenant)->getConnectionName())->getQueryLog())
            ->filter(static fn (array $q): bool => str_starts_with(strtolower((string) $q['query']), 'update'));
        $this->assertCount(0, $updates);
    }

    public function test_the_last_activity_migration_is_idempotent_and_heals_an_interrupted_run(): void
    {
        $tenant = $this->shop($this->free, null);
        $this->assertNull($this->activity($tenant));

        // The column exists (a first run died after the ALTER): re-running only backfills.
        $migration = require database_path('migrations/2026_11_19_110000_add_last_activity_at_to_tenants_table.php');
        $migration->up();
        $this->assertSame(now()->toDateTimeString(), $this->activity($tenant)?->toDateTimeString());

        // A stamped row is never touched by another run.
        $this->setActivity($tenant, now()->subDays(5));
        $migration->up();
        $this->assertSame(now()->subDays(5)->toDateTimeString(), $this->activity($tenant)?->toDateTimeString());
    }

    public function test_a_free_shop_stranded_in_suspended_by_the_dormant_walk_is_archived_on_the_next_run(): void
    {
        $tenant = $this->shop($this->free, now()->subDays(100), TenantStatus::Suspended);
        $this->dormantEvent($tenant, TenantStatus::Suspended);
        $bystander = $this->shop($this->free, now()->subDays(100), TenantStatus::Suspended); // suspended by a human

        $this->artisan(self::COMMAND)->assertSuccessful();

        $archived = $this->fresh($tenant);
        $this->assertSame(TenantStatus::Archived->value, $archived->status);
        $this->assertNotNull($archived->final_backup_id);
        $this->assertSame(TenantStatus::Suspended->value, $this->fresh($bystander)->status);
        $this->assertSame(1, TenantBackup::query()->count());

        $this->artisan(self::COMMAND)->assertSuccessful();
        $this->assertSame(1, TenantBackup::query()->count());
    }

    public function test_a_stranded_shop_an_operator_touched_afterwards_is_left_alone(): void
    {
        $tenant = $this->shop($this->free, now()->subDays(100), TenantStatus::Suspended);
        $this->dormantEvent($tenant, TenantStatus::Suspended);
        TenantLifecycleEvent::query()->create([
            'tenant_id' => (string) $tenant->getKey(), 'from_status' => 'suspended', 'to_status' => 'suspended',
            'actor' => 'super_admin', 'properties' => ['source' => 'manual'],
        ]);

        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertSame(TenantStatus::Suspended->value, $this->fresh($tenant)->status);
    }

    public function test_a_shop_without_a_valid_owner_email_is_never_reminded_nor_archived(): void
    {
        $tenant = $this->shop($this->free, now()->subDays(120));
        Tenant::query()->whereKey($tenant->getKey())->update(['email' => 'not-an-email']);

        $this->artisan(self::COMMAND)->assertSuccessful();
        $this->artisan(self::COMMAND)->assertSuccessful();

        $this->assertSame(TenantStatus::Active->value, $this->fresh($tenant)->status);
        $this->assertSame(0, TenantLifecycleReminder::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_the_tenant_list_shows_dormant_days_only_for_free_shops(): void
    {
        $paid = $this->shop($this->paid, now()->subDays(40));
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $rows = collect($this->getJson('/api/v1/super-admin/tenants', $headers)->assertOk()->json('tenants.data'))->keyBy('id');

        $this->assertNull($rows[(string) $paid->getKey()]['dormant_days']);
    }

    // ------------------------------------------------------------ helpers

    private function dormantEvent(Tenant $tenant, TenantStatus $to): void
    {
        TenantLifecycleEvent::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'from_status' => TenantStatus::ReadOnly->value,
            'to_status' => $to->value,
            'actor' => 'system',
            'properties' => ['source' => HandleDormantFreeShopsAction::SOURCE],
        ]);
    }

    private function plan(string $prefix, bool $free): Plan
    {
        $slug = $prefix.'-'.Str::lower(Str::random(8));

        return Plan::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => $free ? '0.000' : '449.000',
            'price_yearly' => $free ? '0.000' : '4490.000',
            'is_free' => $free,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
        ]);
    }

    private function shop(?Plan $plan, ?Carbon $lastActivity, TenantStatus $status = TenantStatus::Active): Tenant
    {
        $tenant = $this->createTenant(['plan_id' => $plan?->id, 'status' => $status->value, 'subscription_ends_at' => null]);
        $update = ['last_activity_at' => $lastActivity];
        if ($status === TenantStatus::Archived) {
            $update += ['archived_at' => now(), 'status_before_archive' => TenantStatus::Suspended->value, 'suspension_reason' => 'other'];
        }
        Tenant::query()->whereKey($tenant->getKey())->update($update);

        return $this->fresh($tenant);
    }

    private function setActivity(Tenant $tenant, Carbon $at): void
    {
        Tenant::query()->whereKey($tenant->getKey())->update(['last_activity_at' => $at]);
    }

    private function remindedAt(Tenant $tenant, Carbon $sentAt): void
    {
        $fresh = $this->fresh($tenant);
        $row = TenantLifecycleReminder::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'kind' => HandleDormantFreeShopsAction::KIND_REMIND,
            'target_date' => $fresh->activityAnchor()?->toDateString(),
            'recipients' => 1,
        ]);
        TenantLifecycleReminder::query()->whereKey($row->getKey())->update(['created_at' => $sentAt]);
    }

    private function activity(Tenant $tenant): ?Carbon
    {
        return $this->fresh($tenant)->last_activity_at;
    }

    private function fresh(Tenant $tenant): Tenant
    {
        return Tenant::query()->findOrFail($tenant->getKey());
    }
}
