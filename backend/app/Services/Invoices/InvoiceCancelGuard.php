<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\RateLimitKey;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * BE-03: verifies admin PIN approval when cancelling an invoice with active cash payments.
 *
 * An invoice without active (unreversed) cash payments can be cancelled with the `invoices.cancel`
 * permission alone. When active cash payments exist (reversing them affects the cash drawer/treasury),
 * a manager/admin PIN override is required. The approver must hold the `admin` role, have access
 * to the invoice's store, and match the hashed `pos_pin`. Failed attempts are rate-limited per tenant.
 */
final class InvoiceCancelGuard
{
    private const MAX_ATTEMPTS_PER_APPROVER = 5;

    private const MAX_ATTEMPTS_PER_ACTOR = 10;

    private const DECAY_SECONDS = 300;

    /**
     * @param  array{user_id?: mixed, pin?: mixed}|null  $override
     * @return int|null The ID of the admin who approved the cancellation (null if no cash was present)
     *
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    public function validate(Invoice $invoice, ?User $actor, ?array $override): ?int
    {
        if (! $this->hasActiveCashPayments($invoice)) {
            return null;
        }

        if ($override === null || ! isset($override['user_id'], $override['pin'])) {
            throw ValidationException::withMessages([
                'override' => __('invoices.cancel_cash_requires_admin_pin'),
            ]);
        }

        $storeId = $invoice->store_id !== null ? (int) $invoice->store_id : null;

        return $this->verifyAdminPin(
            (int) $override['user_id'],
            (string) $override['pin'],
            $actor,
            $storeId
        );
    }

    /**
     * Determines whether the invoice has active (unreversed positive) cash payments.
     */
    public function hasActiveCashPayments(Invoice $invoice): bool
    {
        return Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('amount', '>', 0)
            ->where('payment_method', 'cash')
            ->excludingRefunds()
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('payments as reversal')
                    ->whereColumn('reversal.reverses_payment_id', 'payments.id')
                    ->whereNull('reversal.deleted_at');
            })
            ->exists();
    }

    /**
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    private function verifyAdminPin(int $approverId, string $pin, ?User $actor, ?int $storeId): int
    {
        $scope = RateLimitKey::scope();
        $approverKey = 'cancel-override:'.$scope.':approver:'.$approverId;
        $actorKey = 'cancel-override:'.$scope.':actor:'.($actor !== null ? (string) $actor->id : 'guest');

        if (RateLimiter::tooManyAttempts($approverKey, self::MAX_ATTEMPTS_PER_APPROVER)
            || RateLimiter::tooManyAttempts($actorKey, self::MAX_ATTEMPTS_PER_ACTOR)) {
            $wait = max(RateLimiter::availableIn($approverKey), RateLimiter::availableIn($actorKey), 1);

            throw new ThrottleRequestsException(__('pos.override_throttled'), null, ['Retry-After' => (string) $wait]);
        }

        $approver = User::query()->whereKey($approverId)->where('is_active', true)->first();

        $valid = $approver instanceof User
            && $approver->hasRole('admin')
            && ($storeId === null || ActiveStore::canAccess($approver, $storeId))
            && is_string($approver->pos_pin)
            && $approver->pos_pin !== ''
            && Hash::check($pin, $approver->pos_pin);

        if (! $valid) {
            RateLimiter::hit($approverKey, self::DECAY_SECONDS);
            RateLimiter::hit($actorKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'override.pin' => __('pos.override_invalid'),
            ]);
        }

        RateLimiter::clear($approverKey);

        return (int) $approver->id;
    }
}
