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
}
