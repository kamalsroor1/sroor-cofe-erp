<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Signup\ProvisionVerifiedSignupAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * SIGN-12: queued right after a self-signup's email is verified (afterCommit). Registers its
 * tenant through ProvisionVerifiedSignupAction, which records every failure itself (owner mail +
 * Telegram) and never throws on the automatic path. Unique per signup: a re-dispatch by the
 * status endpoint (self-healing) cannot run twice in parallel; the row lock and the status check
 * make a late duplicate a no-op anyway. Carries the signup id only.
 */
final class ProvisionVerifiedSignupJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $signupRequestId)
    {
        $this->onQueue((string) config('tenancy.provisioning.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return (string) $this->signupRequestId;
    }

    public function handle(ProvisionVerifiedSignupAction $provision): void
    {
        $provision->execute($this->signupRequestId);
    }
}
