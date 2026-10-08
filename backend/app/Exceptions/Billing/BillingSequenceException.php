<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use LogicException;

/**
 * A billing document number could not be allocated safely. These are programming or
 * configuration errors (no transaction, invalid sequence key, invalid config), so the
 * allocation stops before any number is consumed. The message is translated
 * (lang/{ar,en}/billing.php -> sequence.*); reason() is a stable code for callers.
 */
final class BillingSequenceException extends LogicException
{
    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /** next() was called without an open transaction on the central connection. */
    public static function transactionRequired(): self
    {
        return self::make('transaction_required');
    }

    public static function invalidKey(): self
    {
        return self::make('invalid_key');
    }

    public static function invalidConfiguration(string $key): self
    {
        return self::make('invalid_configuration', ['key' => $key]);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function make(string $reason, array $replace = []): self
    {
        return new self($reason, (string) __('billing.sequence.'.$reason, $replace));
    }
}
