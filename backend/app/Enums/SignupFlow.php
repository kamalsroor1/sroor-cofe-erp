<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * SIGN-11: how a central `signup_requests` row was created (`signup_flow`). Never rename a value.
 *
 *  - Assisted: the PRD-05 public request form; an operator approves or rejects it.
 *  - SelfSignup: the owner signed up with a password and verifies the email with a code.
 */
enum SignupFlow: string
{
    case Assisted = 'assisted';
    case SelfSignup = 'self_signup';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
