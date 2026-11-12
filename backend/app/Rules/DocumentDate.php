<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use App\Services\Settings\TenantSettings;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * DATE-02: the one rule every write document date (sales/purchase/return/expense/payment) goes through.
 *
 *  - never after the tenant's business date (TenantClock, honours timezone + day cutoff);
 *  - earlier than the business date only with `documents.backdate` (admin has it implicitly)
 *    and not further back than the `backdate_max_days` setting.
 *
 * Document NUMBERS keep using the creation date on purpose (sequence per creation day); only the
 * stored document date is back-dated.
 */
final class DocumentDate implements ValidationRule
{
    public const PERMISSION = 'documents.backdate';

    public function __construct(private readonly ?User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $date = CarbonImmutable::parse((string) $value)->toDateString();
        } catch (Throwable) {
            $fail('validation.date')->translate();

            return;
        }

        $today = app(TenantClock::class)->businessDate();

        if ($date > $today) {
            $fail('documents.date_in_future')->translate();

            return;
        }

        if ($date === $today) {
            return;
        }

        if (! $this->canBackdate()) {
            $fail('documents.backdate_forbidden')->translate();

            return;
        }

        $maxDays = app(TenantSettings::class)->backdateMaxDays();

        if ($maxDays !== null) {
            $oldest = CarbonImmutable::parse($today)->subDays($maxDays)->toDateString();

            if ($date < $oldest) {
                $fail('documents.date_too_old')->translate(['days' => $maxDays]);
            }
        }
    }

    private function canBackdate(): bool
    {
        return $this->user !== null
            && ($this->user->hasRole('admin') || $this->user->can(self::PERMISSION));
    }
}
