<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Invoices\CancelSalesInvoiceAction;
use App\DTOs\Invoices\CancelInvoiceDTO;
use App\Exceptions\ManagerPinLockedException;
use App\Jobs\SendManagerPinLockedAlertJob;
use App\Models\ActivityLog;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Approvals\ManagerPinVerifier;
use App\Services\InvoiceService;
use App\Services\Settings\SettingSecrets;
use App\Services\ShiftService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;

/**
 * SEC-21 (2): manager approval PINs get a daily lockout per approver on top of the short
 * 5-per-5-minutes bucket, new PINs are 6 digits, and the lockout lives in the ONE shared
 * ManagerPinVerifier (invoice cancel approval, price/discount overrides, return window, idle
 * screen unlock).
 *
 * Exploit before the fix: the only limit was 5 wrong PINs per approver per 5 minutes, i.e.
 * ~1440 guesses a day. POST /api/v1/invoices/{id}/cancel with override {user_id: <admin>, pin}
 * 20 times spread over 25 minutes answered 422 every time, and the 21st request with the right
 * PIN was accepted (200): a 4-digit PIN falls in about a week.
 */
final class ManagerPinLockoutApiTest extends TenantTestCase
{
    private const ADMIN_PIN = '4321'; // an EXISTING short PIN: it must keep verifying

    private Tenant $tenant;

    private int $adminId;

    private int $storeId;

    private int $customerId;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        // 09:00 in Cairo: 20 attempts spread over half an hour stay on the same tenant day.
        $this->travelTo(CarbonImmutable::parse('2026-11-12 07:00:00', 'UTC'));

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;

        $this->inTenant($this->tenant, function (): void {
            $admin = User::query()->orderBy('id')->firstOrFail();
            $admin->forceFill(['pos_pin' => Hash::make(self::ADMIN_PIN)])->save();
            $this->adminId = (int) $admin->id;

            $this->customerId = (int) Customer::create(['name' => 'عميل', 'phone' => '01000007301', 'price_tier' => 'retail', 'is_active' => true])->id;
            $item = Item::create([
                'name' => 'بن', 'code' => 'PIN-1', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '10.000', 'weighted_avg_cost' => '10.000', 'selling_price' => '20.000',
                'price_retail' => '20.000', 'price_wholesale' => '20.000',
                'current_stock' => '100.000', 'min_stock_level' => '1.000', 'is_active' => true,
            ]);
            $this->itemId = (int) $item->id;
            StoreStock::create(['store_id' => $this->storeId, 'item_id' => $item->id, 'quantity' => '100.000']);
        });
    }

    public function test_twenty_wrong_pins_lock_the_approver_for_the_day_even_with_the_right_pin(): void
    {
        Queue::fake();
        $invoiceId = $this->cashSale();
        $headers = $this->cashierHeaders();

        $this->failTwentyTimes($invoiceId, $this->adminId, $headers);

        // The 21st attempt carries the RIGHT pin and is still refused while the lock holds.
        $this->cancel($invoiceId, $this->adminId, self::ADMIN_PIN, $headers)
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', ManagerPinLockedException::ERROR_CODE)
            ->assertJsonPath('message', __('pos.override_locked_today'))
            ->assertHeader('Retry-After');

        // Still locked hours later on the same day.
        $this->travel(8)->hours();
        $this->cancel($invoiceId, $this->adminId, self::ADMIN_PIN, $headers)->assertStatus(429);

        $this->inTenant($this->tenant, fn () => $this->assertSame('confirmed', Invoice::query()->findOrFail($invoiceId)->status));

        // Next day on the tenant clock: the same existing 4-digit PIN works again (fresh token:
        // the 12-hour token TTL has passed).
        $this->travel(1)->days();
        $this->cancel($invoiceId, $this->adminId, self::ADMIN_PIN, $this->cashierHeaders())->assertOk();
        $this->inTenant($this->tenant, fn () => $this->assertSame('cancelled', Invoice::query()->findOrFail($invoiceId)->status));
    }

    public function test_the_alert_is_queued_once_when_the_lock_trips(): void
    {
        Queue::fake();
        $invoiceId = $this->cashSale();
        $cashier = $this->createTenantUser($this->tenant, permissions: ['invoices.cancel'], attributes: ['name' => 'كاشير يجرب']);
        $headers = $this->tenantHeaders($this->tenant, $cashier);

        $this->failTwentyTimes($invoiceId, $this->adminId, $headers, assertNoAlertBefore: true);
        Queue::assertPushed(SendManagerPinLockedAlertJob::class, 1);
        Queue::assertPushed(SendManagerPinLockedAlertJob::class, fn (SendManagerPinLockedAlertJob $job): bool => $job->approverId === $this->adminId
            && $job->actorId === (int) $cashier->id
            && $job->failures === ManagerPinVerifier::MAX_DAILY_FAILURES_PER_APPROVER);

        // Further attempts while locked are refused without another alert.
        foreach (range(1, 3) as $_) {
            $this->cancel($invoiceId, $this->adminId, '999999', $headers)->assertStatus(429);
        }
        Queue::assertPushed(SendManagerPinLockedAlertJob::class, 1);

        // The job never carries a PIN.
        Queue::assertPushed(SendManagerPinLockedAlertJob::class, function (SendManagerPinLockedAlertJob $job): bool {
            $payload = serialize($job);

            return ! str_contains($payload, self::ADMIN_PIN) && ! str_contains($payload, '999999');
        });
    }

    public function test_the_alert_job_posts_to_the_shops_own_telegram_chat_only(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $cashier = $this->createTenantUser($this->tenant, attributes: ['name' => 'كاشير يجرب']);

        $this->inTenant($this->tenant, function () use ($cashier): void {
            // No shop chat configured: nothing is sent (never the platform chat).
            config(['services.telegram.chat_id' => '-100999', 'services.telegram.bot_token' => '111:fixturePlatformBot']);
            app()->call([new SendManagerPinLockedAlertJob($this->adminId, (int) $cashier->id, 20), 'handle']);
            Http::assertNothingSent();

            Setting::set('telegram_bot_token', SettingSecrets::encrypt('222:fixtureShopBot'));
            Setting::set('telegram_chat_id', '-100123');
            app()->call([new SendManagerPinLockedAlertJob($this->adminId, (int) $cashier->id, 20), 'handle']);
        });

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/bot222:fixtureShopBot/sendMessage')
            && $request['chat_id'] === '-100123'
            && str_contains((string) $request['text'], 'كاشير يجرب')
            && ! str_contains((string) $request['text'], self::ADMIN_PIN));
    }

    public function test_the_lockout_is_per_approver(): void
    {
        Queue::fake();
        $invoiceId = $this->cashSale();
        $headers = $this->cashierHeaders();
        $otherAdmin = $this->createTenantUser($this->tenant, 'admin', attributes: ['name' => 'مدير تاني']);
        $this->inTenant($this->tenant, fn () => User::query()->findOrFail($otherAdmin->id)->forceFill(['pos_pin' => Hash::make('864201')])->save());

        $this->failTwentyTimes($invoiceId, $this->adminId, $headers);
        $this->cancel($invoiceId, $this->adminId, self::ADMIN_PIN, $headers)->assertStatus(429);

        // Another approver of the same shop is not affected.
        $this->cancel($invoiceId, (int) $otherAdmin->id, '864201', $headers)->assertOk();
        $this->inTenant($this->tenant, fn () => $this->assertSame('cancelled', Invoice::query()->findOrFail($invoiceId)->status));
    }

    public function test_changing_the_pin_lifts_the_lockout(): void
    {
        Queue::fake();
        $invoiceId = $this->cashSale();
        $headers = $this->cashierHeaders();
        $otherAdmin = $this->createTenantUser($this->tenant, 'admin');

        $this->failTwentyTimes($invoiceId, $this->adminId, $headers);
        $this->cancel($invoiceId, $this->adminId, self::ADMIN_PIN, $headers)->assertStatus(429);

        $this->putJson("/api/v1/users/{$this->adminId}/pos-pin", ['pin' => '975310'], $this->tenantHeaders($this->tenant, $otherAdmin))->assertOk();

        $this->cancel($invoiceId, $this->adminId, '975310', $headers)->assertOk();
    }

    public function test_the_screen_unlock_shares_the_same_daily_lockout(): void
    {
        Queue::fake();
        $invoiceId = $this->cashSale();

        $this->failTwentyTimes($invoiceId, $this->adminId, $this->cashierHeaders());

        // The approver's own PIN no longer unlocks the idle screen today: one verifier, one lock.
        $this->postJson('/api/v1/auth/unlock-screen', ['pin' => self::ADMIN_PIN], $this->tenantHeaders($this->tenant))
            ->assertStatus(429)
            ->assertJsonPath('error_code', ManagerPinLockedException::ERROR_CODE);
    }

    public function test_new_pins_must_be_six_digits_while_existing_short_pins_keep_working(): void
    {
        $target = $this->createTenantUser($this->tenant, 'admin');
        $headers = $this->tenantHeaders($this->tenant);

        foreach (['1234', '12345', '1234567'] as $short) {
            $this->putJson("/api/v1/users/{$target->id}/pos-pin", ['pin' => $short], $headers)
                ->assertStatus(422)
                ->assertJsonValidationErrors('pin')
                ->assertJsonPath('errors.pin.0', __('users.pos_pin_format'));
            $this->putJson('/api/v1/profile/pos-pin', ['pin' => $short, 'current_password' => 'password'], $headers)
                ->assertStatus(422)
                ->assertJsonValidationErrors('pin');
        }
        $this->assertNull($this->inTenant($this->tenant, fn () => User::query()->findOrFail($target->id)->pos_pin));

        $this->putJson("/api/v1/users/{$target->id}/pos-pin", ['pin' => '246813'], $headers)->assertOk();
        $this->assertTrue(Hash::check('246813', (string) $this->inTenant($this->tenant, fn () => User::query()->findOrFail($target->id)->pos_pin)));

        // The admin's stored 4-digit PIN still approves.
        $this->cancel($this->cashSale(), $this->adminId, self::ADMIN_PIN, $this->cashierHeaders())->assertOk();
    }

    public function test_a_wrong_pin_is_counted_even_when_the_limiter_lives_on_the_tenant_connection(): void
    {
        $invoiceId = $this->cashSale();
        $cashierId = (int) $this->createTenantUser($this->tenant, permissions: ['invoices.cancel'])->id;

        try {
            $this->inTenant($this->tenant, function () use ($invoiceId, $cashierId): void {
                config([
                    'cache.stores.sec21_tenant_db' => ['driver' => 'database', 'connection' => 'tenant', 'table' => 'cache', 'lock_connection' => 'tenant'],
                    'cache.limiter' => 'sec21_tenant_db',
                ]);
                app('cache')->forgetDriver('sec21_tenant_db');
                app()->forgetInstance(CacheRateLimiter::class);
                Facade::clearResolvedInstance(CacheRateLimiter::class);

                // Control: on this store a hit written inside a rolled-back transaction is lost.
                try {
                    DB::transaction(function (): void {
                        RateLimiter::hit('sec21-control', 60);
                        throw new \RuntimeException('rollback');
                    });
                } catch (\RuntimeException) {
                }
                $this->assertSame(0, RateLimiter::attempts('sec21-control'));

                $cashier = User::query()->findOrFail($cashierId);
                foreach ([1, 2, 3] as $expected) {
                    try {
                        app(CancelSalesInvoiceAction::class)->execute(
                            new CancelInvoiceDTO(invoice_id: $invoiceId, reason: 'x', override: ['user_id' => $this->adminId, 'pin' => '000000']),
                            $cashier,
                        );
                        $this->fail('A wrong manager PIN must be refused.');
                    } catch (ValidationException $e) {
                        $this->assertArrayHasKey('override.pin', $e->errors());
                    }

                    // The failure is in the tenant DB cache table: the cancel transaction never ran.
                    $this->assertSame($expected, RateLimiter::attempts(ManagerPinVerifier::dailyKey($this->adminId)));
                }
                $this->assertTrue(DB::connection('tenant')->table('cache')->where('key', 'like', '%approver-day%')->exists());
                $this->assertSame('confirmed', Invoice::query()->findOrFail($invoiceId)->status);
            });
        } finally {
            config(['cache.limiter' => null]);
            app()->forgetInstance(CacheRateLimiter::class);
            Facade::clearResolvedInstance(CacheRateLimiter::class);
        }
    }

    public function test_the_lockout_of_one_tenant_does_not_lock_another_tenant(): void
    {
        Queue::fake();
        $invoiceId = $this->cashSale();
        $this->failTwentyTimes($invoiceId, $this->adminId, $this->cashierHeaders());

        $other = $this->createTenant();
        $this->inTenant($other, function (): void {
            $this->assertFalse(RateLimiter::tooManyAttempts(ManagerPinVerifier::dailyKey($this->adminId), ManagerPinVerifier::MAX_DAILY_FAILURES_PER_APPROVER));
        });
        $this->inTenant($this->tenant, function (): void {
            $this->assertTrue(RateLimiter::tooManyAttempts(ManagerPinVerifier::dailyKey($this->adminId), ManagerPinVerifier::MAX_DAILY_FAILURES_PER_APPROVER));
        });
    }

    /**
     * Security review of SEC-21 (3): the limiter was check-then-increment, so N concurrent
     * requests with N different PINs all passed tooManyAttempts() before any hit() landed and a
     * burst tested more than 5 PINs. Simulated deterministically: every bcrypt check re-enters
     * verify() with the next guess before finishing (the next request arrives while this one is
     * still hashing). Before the fix all 8 guesses were hashed; now only 5 are.
     */
    public function test_a_concurrent_burst_cannot_test_more_pins_than_the_cap(): void
    {
        Queue::fake();
        $actorIds = $this->actorIds(8);

        $hashed = $this->inTenant($this->tenant, function () use ($actorIds): int {
            $verifier = new ManagerPinVerifier;
            $hasher = $this->reentrantHasher(function (int $depth) use ($verifier, $actorIds): void {
                if ($depth < count($actorIds)) {
                    $this->attempt($verifier, $actorIds[$depth], sprintf('%06d', $depth));
                }
            });

            $this->attempt($verifier, $actorIds[0], '000000');

            return $hasher->checks;
        });

        $this->assertSame(ManagerPinVerifier::MAX_ATTEMPTS_PER_APPROVER, $hashed);
    }

    public function test_a_burst_at_the_daily_limit_tests_only_the_remaining_slots_and_alerts_once(): void
    {
        Queue::fake();
        $actorIds = $this->actorIds(5);

        $hashed = $this->inTenant($this->tenant, function () use ($actorIds): int {
            // 18 failures earlier today.
            foreach (range(1, ManagerPinVerifier::MAX_DAILY_FAILURES_PER_APPROVER - 2) as $_) {
                RateLimiter::increment(ManagerPinVerifier::dailyKey($this->adminId), 3600);
            }

            $verifier = new ManagerPinVerifier;
            $hasher = $this->reentrantHasher(function (int $depth) use ($verifier, $actorIds): void {
                if ($depth < count($actorIds)) {
                    $this->attempt($verifier, $actorIds[$depth], sprintf('%06d', $depth));
                }
            });
            $this->attempt($verifier, $actorIds[0], '000000');

            $this->assertGreaterThanOrEqual(ManagerPinVerifier::MAX_DAILY_FAILURES_PER_APPROVER, RateLimiter::attempts(ManagerPinVerifier::dailyKey($this->adminId)));

            return $hasher->checks;
        });

        $this->assertSame(2, $hashed, 'Only the 19th and 20th slots may test a PIN.');
        Queue::assertPushed(SendManagerPinLockedAlertJob::class, 1);
        $this->cancel($this->cashSale(), $this->adminId, self::ADMIN_PIN, $this->cashierHeaders())->assertStatus(429);
    }

    /**
     * Security review of SEC-21 (4): bcrypt ran only for an existing, active, authorised approver
     * with a PIN, so the response time told which user ids are PIN-holding managers. Every
     * refusal now costs exactly one bcrypt check and returns the same body.
     */
    public function test_every_refusal_costs_exactly_one_bcrypt_check_and_returns_the_same_body(): void
    {
        $invoiceId = $this->cashSale();
        $noPin = $this->createTenantUser($this->tenant, 'admin');
        $notAllowed = $this->createTenantUser($this->tenant, 'cashier');
        $this->inTenant($this->tenant, fn () => User::query()->findOrFail($notAllowed->id)->forceFill(['pos_pin' => Hash::make('111111')])->save());

        $bodies = [];
        foreach ([
            'wrong pin' => [$this->adminId, '999999'],
            'unknown user' => [99999999, '999999'],
            'no pin set' => [(int) $noPin->id, '999999'],
            'not allowed' => [(int) $notAllowed->id, '111111'],
        ] as $case => [$approverId, $pin]) {
            $hasher = $this->reentrantHasher(static function (): void {});
            $response = $this->cancel($invoiceId, $approverId, $pin, $this->cashierHeaders())->assertStatus(422);
            $this->assertSame(1, $hasher->checks, $case);
            $bodies[$case] = $response->json();
        }

        $this->assertCount(1, array_unique(array_map('serialize', $bodies)), 'Every refusal must look the same.');
    }

    /** LEAVE item of the review: each wrong PIN is attributable (actor id, never the PIN). */
    public function test_each_wrong_pin_is_activity_logged_with_the_actor_and_the_alert_names_every_actor(): void
    {
        Queue::fake();
        $invoiceId = $this->cashSale();
        $first = $this->createTenantUser($this->tenant, permissions: ['invoices.cancel'], attributes: ['name' => 'كاشير أول']);
        $second = $this->createTenantUser($this->tenant, permissions: ['invoices.cancel'], attributes: ['name' => 'كاشير تاني']);

        $this->cancel($invoiceId, $this->adminId, '111111', $this->tenantHeaders($this->tenant, $first))->assertStatus(422);
        $this->cancel($invoiceId, $this->adminId, '222222', $this->tenantHeaders($this->tenant, $second))->assertStatus(422);

        $this->inTenant($this->tenant, function () use ($first, $second): void {
            $rows = ActivityLog::query()->where('action', ManagerPinVerifier::FAILED_ACTION)->orderBy('id')->get();
            $this->assertSame([(int) $first->id, (int) $second->id], $rows->pluck('user_id')->map(fn ($id): int => (int) $id)->all());
            $this->assertSame([$this->adminId, $this->adminId], $rows->pluck('subject_id')->map(fn ($id): int => (int) $id)->all());
            foreach ($rows as $row) {
                $this->assertStringNotContainsString('111111', json_encode($row->toArray(), JSON_UNESCAPED_UNICODE) ?: '');
                $this->assertStringNotContainsString('222222', json_encode($row->toArray(), JSON_UNESCAPED_UNICODE) ?: '');
            }
        });

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $this->inTenant($this->tenant, function () use ($second): void {
            Setting::set('telegram_bot_token', SettingSecrets::encrypt('222:fixtureShopBot'));
            Setting::set('telegram_chat_id', '-100123');
            app()->call([new SendManagerPinLockedAlertJob($this->adminId, (int) $second->id, 20), 'handle']);
        });
        Http::assertSent(fn (HttpRequest $request): bool => str_contains((string) $request['text'], 'كاشير أول')
            && str_contains((string) $request['text'], 'كاشير تاني'));
    }

    /**
     * Review item 11: the lock follows the shop's BUSINESS day. With a 03:00 cutoff, 20 failures
     * at 22:00 stay locked after midnight (00:30 is still the same business day) and lift at 03:30.
     */
    public function test_the_lock_follows_the_business_day_cutoff(): void
    {
        Queue::fake();
        $this->inTenant($this->tenant, fn () => Setting::set('business_day_cutoff', '03:00'));
        $this->travelTo(CarbonImmutable::parse('2026-11-12 20:00:00', 'UTC')); // 22:00 Cairo
        $invoiceId = $this->cashSale();

        $this->failTwentyTimes($invoiceId, $this->adminId, $this->cashierHeaders());

        $this->travelTo(CarbonImmutable::parse('2026-11-12 22:30:00', 'UTC')); // 00:30 Cairo, next calendar day
        $this->cancel($invoiceId, $this->adminId, self::ADMIN_PIN, $this->cashierHeaders())->assertStatus(429);

        $this->travelTo(CarbonImmutable::parse('2026-11-13 01:30:00', 'UTC')); // 03:30 Cairo, next business day
        $this->cancel($invoiceId, $this->adminId, self::ADMIN_PIN, $this->cashierHeaders())->assertOk();
    }

    /** @return list<int> */
    private function actorIds(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = (int) $this->createTenantUser($this->tenant, permissions: ['invoices.cancel'])->id;
        }

        return $ids;
    }

    /** One verify() call as user $actorId; any refusal is swallowed (a parallel request). Runs in the tenant. */
    private function attempt(ManagerPinVerifier $verifier, int $actorId, string $pin): void
    {
        try {
            $verifier->verify($this->adminId, $pin, User::query()->findOrFail($actorId), $this->storeId, static fn (User $u): bool => $u->hasRole('admin'));
        } catch (ValidationException|ThrottleRequestsException) {
        }
    }

    /**
     * Swaps the hasher for one that counts check() calls and, before answering, runs $onCheck
     * with the current nesting depth (a request arriving while this one is hashing).
     *
     * @param  Closure(int): void  $onCheck
     */
    private function reentrantHasher(Closure $onCheck): object
    {
        $real = Hash::getFacadeRoot();
        $hasher = new class($real, $onCheck) implements Hasher
        {
            public int $checks = 0;

            private int $depth = 0;

            public function __construct(private readonly Hasher $real, private readonly Closure $onCheck) {}

            public function info($hashedValue): array
            {
                return $this->real->info($hashedValue);
            }

            public function make($value, array $options = []): string
            {
                return $this->real->make($value, $options);
            }

            public function check($value, $hashedValue, array $options = []): bool
            {
                $this->checks++;
                $this->depth++;
                ($this->onCheck)($this->depth);

                return $this->real->check($value, $hashedValue, $options);
            }

            public function needsRehash($hashedValue, array $options = []): bool
            {
                return $this->real->needsRehash($hashedValue, $options);
            }

            /** @param  array<int, mixed>  $arguments */
            public function __call(string $method, array $arguments): mixed
            {
                return $this->real->{$method}(...$arguments);
            }
        };
        Hash::swap($hasher);

        return $hasher;
    }

    /**
     * 20 wrong PINs spread so the short buckets (5 per approver, 10 per actor, per 5 minutes)
     * never answer first: every one of them must be a plain 422.
     *
     * @param  array<string, string>  $headers
     */
    private function failTwentyTimes(int $invoiceId, int $approverId, array $headers, bool $assertNoAlertBefore = false): void
    {
        for ($i = 1; $i <= ManagerPinVerifier::MAX_DAILY_FAILURES_PER_APPROVER; $i++) {
            $this->cancel($invoiceId, $approverId, sprintf('%06d', $i), $headers)
                ->assertStatus(422)
                ->assertJsonValidationErrors('override.pin');

            if ($assertNoAlertBefore && $i < ManagerPinVerifier::MAX_DAILY_FAILURES_PER_APPROVER) {
                Queue::assertNotPushed(SendManagerPinLockedAlertJob::class);
            }

            if ($i % ManagerPinVerifier::MAX_ATTEMPTS_PER_APPROVER === 0) {
                $this->travel(ManagerPinVerifier::DECAY_SECONDS + 60)->seconds();
            }
        }
    }

    /**
     * @param  array<string, string>  $headers
     * @return TestResponse<Response>
     */
    private function cancel(int $invoiceId, int $approverId, string $pin, array $headers): TestResponse
    {
        return $this->postJson('/api/v1/invoices/'.$invoiceId.'/cancel', [
            'reason' => 'طلب العميل',
            'override' => ['user_id' => $approverId, 'pin' => $pin],
        ], $headers);
    }

    /** @return array<string, string> */
    private function cashierHeaders(): array
    {
        return $this->tenantHeaders($this->tenant, $this->createTenantUser($this->tenant, permissions: ['invoices.cancel']));
    }

    /** A paid cash sale (an active cash payment, so cancelling it needs an admin PIN). */
    private function cashSale(): int
    {
        return $this->inTenant($this->tenant, function (): int {
            $this->actingAs(User::query()->findOrFail($this->adminId));

            if (! CashShift::query()->where('status', 'open')->where('store_id', $this->storeId)->exists()) {
                app(ShiftService::class)->openShift('100.000', null, $this->storeId);
            }

            return (int) app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $this->customerId, 'store_id' => $this->storeId,
                'payment_type' => 'cash', 'payment_method' => 'cash',
                'items' => [['item_id' => $this->itemId, 'quantity' => '1.000', 'unit_price' => '20.000']],
            ])->id;
        });
    }
}
