<?php

declare(strict_types=1);

namespace App\Exceptions\Signup;

use RuntimeException;

/**
 * SIGN-12: the provisioning of a verified self-signup was refused before any tenant was
 * registered (`failureCode`, see SelfSignupOutcomes::FAILURE_*). Caught by
 * ProvisionVerifiedSignupAction, which records the failure; never rendered to a client.
 */
final class SelfSignupProvisioningRefused extends RuntimeException
{
    public function __construct(public readonly string $failureCode)
    {
        parent::__construct('Self-signup provisioning refused: '.$failureCode);
    }
}
