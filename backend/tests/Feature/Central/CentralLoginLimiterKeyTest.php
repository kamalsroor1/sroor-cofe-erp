<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Models\CentralUser;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * Security audit (W2 lane 3I), LOW: the tight hourly cap of the `central-login` limiter is
 * keyed by email AND client IP, so one attacker can no longer lock an operator out from every
 * network; a looser hourly cap per email (IP-blind, 100 by default) still slows distributed
 * guessing (LoginThrottleApiTest / CentralAuthApiTest cover that one).
 */
final class CentralLoginLimiterKeyTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const LOGIN = '/api/v1/super-admin/auth/login';

    // Fixture credential only (the `fixture` prefix marks it as fake for gitleaks).
    private const PASSWORD = 'fixtureLimiterOperatorPassword';

    private function operator(): CentralUser
    {
        return $this->centralOperator(attributes: ['password' => Hash::make(self::PASSWORD)]);
    }

    public function test_defaults_are_twenty_per_email_and_ip_and_one_hundred_per_email(): void
    {
        $this->assertSame(20, (int) config('rate_limits.central_login.per_email_ip_per_hour'));
        $this->assertSame(100, (int) config('rate_limits.central_login.per_email_per_hour'));
    }

    public function test_the_tight_hourly_cap_is_per_email_and_ip(): void
    {
        config([
            'rate_limits.central_login.per_email_ip_per_hour' => 3,
            'rate_limits.central_login.per_email_per_hour' => 100,
        ]);
        $operator = $this->operator();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7']);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::LOGIN, ['email' => $operator->email, 'password' => 'wrong'])->assertStatus(422);
        }
        $this->postJson(self::LOGIN, ['email' => $operator->email, 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // The attacker's IP is locked; the operator on another network is not.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20']);
        $this->postJson(self::LOGIN, ['email' => $operator->email, 'password' => self::PASSWORD])->assertOk();
    }

    public function test_the_ip_blind_cap_still_stops_distributed_guessing(): void
    {
        config([
            'rate_limits.central_login.per_email_ip_per_hour' => 20,
            'rate_limits.central_login.per_email_per_hour' => 4,
        ]);
        $operator = $this->operator();

        foreach (['192.0.2.1', '192.0.2.2', '192.0.2.3', '192.0.2.4'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson(self::LOGIN, ['email' => $operator->email, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])
            ->postJson(self::LOGIN, ['email' => $operator->email, 'password' => self::PASSWORD])
            ->assertStatus(429);
    }
}
