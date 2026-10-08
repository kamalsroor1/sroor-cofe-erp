<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Obvious dummy phone for the primary test user. Never put a real number in a
     * fixture: Tests\Unit\NoHardcodedRealPhonesTest fails on real-looking literals.
     * Chosen to not collide with the 01000000000-01000000003 dummies some tests use.
     */
    protected const ADMIN_PHONE = '01000000500';

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        }
    }

    /**
     * A test that fails inside $tenant->run() leaves tenancy initialised (stancl v3's run()
     * has no finally), so the default connection still points at the tenant DB when
     * RefreshDatabase rolls back. The shared sqlite :memory: connection then stays inside
     * its transaction and every later test fails with "cannot start a transaction within a
     * transaction". End tenancy first so one failure stays one failure.
     */
    protected function tearDown(): void
    {
        if (isset($this->app) && function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }
}
