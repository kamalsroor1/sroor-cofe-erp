<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use DomainException;

/**
 * A credits ledger operation was refused (ENTI-1.10). Nothing is written.
 *
 * The message is translated (lang/{ar,en}/billing.php -> credits.*); reason() is a stable
 * code for callers and future API error payloads:
 *  - insufficient_balance: a debit larger than the current balance;
 *  - invalid_amount: not a positive decimal with at most 3 decimals / 9 integer digits;
 *  - invalid_reason: a reason outside TenantCreditLedgerEntry::REASONS, or the wrong
 *    direction for it (credit with usage/expiry, debit with allowance/top_up);
 *  - immutable: code tried to update or delete a ledger row (append-only).
 */
final class CreditLedgerException extends DomainException
{
    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public static function insufficientBalance(): self
    {
        return self::make('insufficient_balance');
    }

    public static function invalidAmount(): self
    {
        return self::make('invalid_amount');
    }

    public static function invalidReason(): self
    {
        return self::make('invalid_reason');
    }

    public static function immutable(): self
    {
        return self::make('immutable');
    }

    private static function make(string $reason): self
    {
        return new self($reason, (string) __('billing.credits.'.$reason));
    }
}
