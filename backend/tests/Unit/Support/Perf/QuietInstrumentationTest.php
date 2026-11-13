<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Perf;

use App\Support\Perf\QuietInstrumentation;
use Laravel\Pulse\Pulse;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

/**
 * PERF-04 review: QuietInstrumentation must leave Pulse exactly as it found it — a run must
 * not switch Pulse recording back on when it was off before.
 */
final class QuietInstrumentationTest extends TestCase
{
    public function test_pulse_is_silent_during_the_run_and_restored_to_on(): void
    {
        $pulse = app(Pulse::class)->startRecording();

        $during = QuietInstrumentation::run(fn (): bool => $this->recording($pulse));

        $this->assertFalse($during);
        $this->assertTrue($this->recording($pulse));
    }

    public function test_pulse_that_was_off_stays_off(): void
    {
        $pulse = app(Pulse::class)->stopRecording();

        QuietInstrumentation::run(fn (): null => null);

        $this->assertFalse($this->recording($pulse));
        $pulse->startRecording();
    }

    public function test_the_state_is_restored_when_the_callback_throws(): void
    {
        $pulse = app(Pulse::class)->startRecording();

        $thrown = false;
        try {
            QuietInstrumentation::run(fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'The exception must propagate.');
        $this->assertTrue($this->recording($pulse));
    }

    private function recording(Pulse $pulse): bool
    {
        return (bool) (new ReflectionProperty(Pulse::class, 'shouldRecord'))->getValue($pulse);
    }
}
