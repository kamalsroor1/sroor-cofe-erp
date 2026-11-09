<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Support\SentryScrubber;
use Mockery;
use RuntimeException;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Tests\TestCase;

/**
 * OPS-6: Sentry is wired to the exception handler (bootstrap/app.php Integration::handles),
 * scrubbed, PII free, and inert without a DSN.
 */
final class SentryWiringTest extends TestCase
{
    public function test_unhandled_exceptions_reach_sentry(): void
    {
        $previous = SentrySdk::getCurrentHub();
        $hub = Mockery::mock(HubInterface::class);
        $hub->shouldIgnoreMissing();
        $hub->shouldReceive('captureException')->atLeast()->once();
        SentrySdk::setCurrentHub($hub);

        try {
            report(new RuntimeException('boom'));
        } finally {
            SentrySdk::setCurrentHub($previous);
        }

        $this->addToAssertionCount(1); // Mockery verifies the expectation on teardown
    }

    public function test_events_are_scrubbed_before_they_are_sent(): void
    {
        $this->assertSame([SentryScrubber::class, 'beforeSend'], config('sentry.before_send'));
        $this->assertSame([SentryScrubber::class, 'beforeSendTransaction'], config('sentry.before_send_transaction'));
        $this->assertFalse(config('sentry.send_default_pii'));
        $this->assertIsCallable(config('sentry.before_send'));
    }

    public function test_dsn_comes_from_the_environment_only(): void
    {
        $this->assertEmpty(config('sentry.dsn'));
        $source = (string) file_get_contents(config_path('sentry.php'));
        $this->assertDoesNotMatchRegularExpression('#https?://[^\s\'"]*@[^\s\'"]*sentry#i', $source);
    }
}
