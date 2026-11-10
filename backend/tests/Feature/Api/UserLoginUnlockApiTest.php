<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\SendAccountLockedAlertJob;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;

/**
 * SEC-05: admin unlock of a locked sign-in, lock state in the users payload, Telegram alert.
 */
final class UserLoginUnlockApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $ipCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        Cache::flush();
    }

    public function test_unlock_lets_a_locked_user_log_in_and_clears_phone_and_email_locks(): void
    {
        $target = $this->createTenantUser($this->tenant, 'cashier', attributes: ['phone' => '01000000061', 'email' => 'lock61@sec05.test']);
        $this->lock($this->tenant, 'lock61@sec05.test');
        $this->lock($this->tenant, '01000000061');
        $this->login($this->tenant, 'lock61@sec05.test')->assertStatus(422);
        $this->login($this->tenant, '01000000061')->assertStatus(422);

        $this->postJson("/api/v1/users/{$target->id}/unlock-login", [], $this->adminHeaders())
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('users.unlock_login_success'));

        $this->login($this->tenant, 'lock61@sec05.test')->assertStatus(200);
        $this->login($this->tenant, '01000000061')->assertStatus(200);

        $logged = $this->inTenant($this->tenant, fn (): int => ActivityLog::query()->where('module', 'users')->where('action', 'login_unlocked')->count());
        $this->assertSame(1, $logged);
    }

    public function test_unlock_also_clears_partial_failure_counters(): void
    {
        $target = $this->createTenantUser($this->tenant, 'cashier', attributes: ['phone' => '01000000062', 'email' => 'part62@sec05.test']);
        for ($i = 0; $i < 4; $i++) {
            $this->login($this->tenant, 'part62@sec05.test', 'bad')->assertStatus(422);
        }

        $this->postJson("/api/v1/users/{$target->id}/unlock-login", [], $this->adminHeaders())->assertStatus(200);

        // 4 more failures would have locked had the counter survived.
        for ($i = 0; $i < 4; $i++) {
            $this->login($this->tenant, 'part62@sec05.test', 'bad')->assertStatus(422)->assertJsonPath('errors.login.0', __('auth.failed'));
        }
    }

    public function test_unlock_is_idempotent_when_not_locked(): void
    {
        $target = $this->createTenantUser($this->tenant, 'cashier');

        $this->postJson("/api/v1/users/{$target->id}/unlock-login", [], $this->adminHeaders())
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('users.login_not_locked'));
    }

    public function test_unlock_requires_authentication_permission_and_an_existing_user(): void
    {
        $target = $this->createTenantUser($this->tenant, 'cashier');
        $xTenant = ['X-Tenant' => (string) $this->tenant->getTenantKey()];

        $this->postJson("/api/v1/users/{$target->id}/unlock-login", [], $xTenant)->assertStatus(401);

        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->postJson("/api/v1/users/{$target->id}/unlock-login", [], $this->tenantHeaders($this->tenant, $cashier))->assertStatus(403);

        $this->postJson('/api/v1/users/999999/unlock-login', [], $this->adminHeaders())->assertStatus(404);
    }

    public function test_non_admin_manager_cannot_unlock_an_admin(): void
    {
        $admin = $this->createTenantUser($this->tenant, 'admin');
        $manager = $this->createTenantUser($this->tenant, 'cashier', ['roles.manage']);

        $this->postJson("/api/v1/users/{$admin->id}/unlock-login", [], $this->tenantHeaders($this->tenant, $manager))->assertStatus(403);
    }

    public function test_unlock_is_tenant_isolated(): void
    {
        $other = $this->createTenant();
        $mine = $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => 'same@sec05.test']);
        $theirs = $this->createTenantUser($other, 'cashier', attributes: ['email' => 'same@sec05.test']);
        $this->lock($this->tenant, 'same@sec05.test');
        $this->lock($other, 'same@sec05.test');

        $this->postJson("/api/v1/users/{$mine->id}/unlock-login", [], $this->adminHeaders())->assertStatus(200);

        $this->login($this->tenant, 'same@sec05.test')->assertStatus(200);
        $this->login($other, 'same@sec05.test')->assertStatus(422);

        // A tenant A token cannot reach a tenant B user: ids resolve in A's database only.
        $this->postJson('/api/v1/users/'.($theirs->id + 1000).'/unlock-login', [], $this->adminHeaders())->assertStatus(404);
    }

    public function test_users_index_and_show_expose_lock_state(): void
    {
        $locked = $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => 'lk@sec05.test']);
        $free = $this->createTenantUser($this->tenant, 'cashier');
        $this->lock($this->tenant, 'lk@sec05.test');
        $headers = $this->adminHeaders();

        $rows = collect($this->getJson('/api/v1/users', $headers)->assertStatus(200)->json('data'))->keyBy('id');
        $this->assertTrue($rows[$locked->id]['login_locked']);
        $this->assertNotNull($rows[$locked->id]['login_locked_until']);
        $this->assertFalse($rows[$free->id]['login_locked']);
        $this->assertNull($rows[$free->id]['login_locked_until']);

        $this->getJson("/api/v1/users/{$locked->id}", $headers)->assertJsonPath('data.login_locked', true);
        $this->getJson("/api/v1/users/{$free->id}", $headers)->assertJsonPath('data.login_locked', false)->assertJsonPath('data.login_locked_until', null);
    }

    // ── Telegram alert ────────────────────────────────────────────────────

    public function test_locking_a_real_account_dispatches_one_alert_job(): void
    {
        Bus::fake();
        $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => 'tg@sec05.test']);

        $this->lock($this->tenant, 'tg@sec05.test');

        Bus::assertDispatchedTimes(SendAccountLockedAlertJob::class, 1);
    }

    public function test_unknown_logins_do_not_dispatch_an_alert(): void
    {
        Bus::fake();

        $this->lock($this->tenant, 'ghost@sec05.test');

        Bus::assertNotDispatched(SendAccountLockedAlertJob::class);
    }

    public function test_repeat_lockout_within_the_hour_is_throttled_per_account(): void
    {
        Bus::fake();
        $target = $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => 'rep@sec05.test']);

        $this->lock($this->tenant, 'rep@sec05.test');
        $this->postJson("/api/v1/users/{$target->id}/unlock-login", [], $this->adminHeaders())->assertStatus(200);
        $this->lock($this->tenant, 'rep@sec05.test');

        Bus::assertDispatchedTimes(SendAccountLockedAlertJob::class, 1);
    }

    public function test_tenant_wide_cap_is_ten_alerts_per_hour(): void
    {
        Bus::fake();
        for ($i = 1; $i <= 12; $i++) {
            $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => "cap{$i}@sec05.test"]);
            $this->lock($this->tenant, "cap{$i}@sec05.test");
        }

        Bus::assertDispatchedTimes(SendAccountLockedAlertJob::class, 10);
    }

    public function test_job_sends_escaped_text_without_secrets(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config(['services.telegram.bot_token' => 'SECRET-BOT-TOKEN', 'services.telegram.enabled' => true]);
        $user = $this->createTenantUser($this->tenant, 'cashier', attributes: ['name' => 'Ahmed <b>']);
        $this->inTenant($this->tenant, function (): void {
            Setting::set('telegram_chat_id', '-100');
            Setting::set('company_name', 'Shop <i>1');
        });

        $this->inTenant($this->tenant, fn () => app()->call([new SendAccountLockedAlertJob($user->id, '<x>login', '10.9.9.9', 15), 'handle']));

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $text = (string) $request['text'];

            return str_contains($text, '&lt;x&gt;login')
                && str_contains($text, 'Shop &lt;i&gt;1')
                && str_contains($text, 'Ahmed &lt;b&gt;')
                && str_contains($text, '10.9.9.9')
                && str_contains($text, '15')
                && ! str_contains($text, 'SECRET-BOT-TOKEN')
                && ! str_contains($text, 'password');
        });
    }

    public function test_job_does_not_fall_back_to_the_global_chat_id(): void
    {
        Http::fake();
        config(['services.telegram.bot_token' => 'tok', 'services.telegram.chat_id' => '-999', 'services.telegram.enabled' => true]);
        $user = $this->createTenantUser($this->tenant, 'cashier');

        $this->inTenant($this->tenant, fn () => app()->call([new SendAccountLockedAlertJob($user->id, 'x@sec05.test', '10.9.9.9', 15), 'handle']));

        Http::assertNothingSent();
    }

    public function test_alert_failure_never_breaks_the_login_response(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('boom', 500)]);
        config(['services.telegram.bot_token' => 'tok', 'services.telegram.enabled' => true]);
        $this->inTenant($this->tenant, fn () => Setting::set('telegram_chat_id', '-100'));
        $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => 'boom@sec05.test']);

        $this->lock($this->tenant, 'boom@sec05.test');
        $this->login($this->tenant, 'boom@sec05.test')->assertStatus(422);
    }

    /**
     * @return array<string, string>
     */
    private function adminHeaders(): array
    {
        return $this->tenantHeaders($this->tenant, $this->tenantAdmin($this->tenant));
    }

    private function lock(Tenant $tenant, string $login): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login($tenant, $login, 'wrong-pass')->assertStatus(422);
        }
    }

    /**
     * @return TestResponse<Response>
     */
    private function login(Tenant $tenant, string $login, string $password = 'password'): TestResponse
    {
        // A fresh IP per attempt: only the per-account lockout is under test, not the per-IP throttle.
        $n = ++$this->ipCounter;
        $this->withServerVariables(['REMOTE_ADDR' => '10.'.intdiv($n, 65025).'.'.intdiv($n % 65025, 255).'.'.(($n % 255) + 1)]);

        return $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => $password], ['X-Tenant' => (string) $tenant->getTenantKey()]);
    }
}
