<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Exceptions\DiscountCapExceededException;
use App\Models\Customer;
use App\Models\Item;
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\Money\Decimal;
use App\Support\RateLimitKey;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * BE-08 / POS-18: the server is the authority on a sale line's price.
 *
 *  - listPrice(): the tier price of a line (wholesale customer + a wholesale price set = wholesale,
 *    otherwise the store's effective retail price). Lines of one invoice may mix tiers.
 *  - evaluate(): validates the submitted unit price and discount. Charging MORE than the list price is
 *    always fine. Selling below `min_selling_price` (> 0; 0 = no floor), after the line discount, needs
 *    an approval: the acting user holds `pos.price.below_min`, or the request carries a manager
 *    `override` {user_id, pin} whose user holds it. The approver is returned for the line audit.
 *
 * One instance per invoice: a manager PIN is verified once and covers every below-floor line of that
 * invoice. A guard built without an actor (commands, jobs) validates nothing, it only resolves prices.
 */
final class SalePriceGuard
{
    public const PERMISSION = 'pos.price.below_min';

    /** POS-07: a total discount above the store's cap. Approved by the same manager PIN mechanism. */
    public const DISCOUNT_PERMISSION = 'pos.discount.override';

    public const SOURCE_RETAIL = 'retail';

    public const SOURCE_WHOLESALE = 'wholesale';

    /** Failed PIN attempts allowed per approver and per acting user inside the decay window. */
    private const MAX_ATTEMPTS_PER_APPROVER = 5;

    private const MAX_ATTEMPTS_PER_ACTOR = 10;

    private const DECAY_SECONDS = 300;

    private ?int $approverId = null;

    private ?int $discountApproverId = null;

    /** The approver of a floor breach caused only by the invoice-level discount (not recorded on any line). */
    private ?int $invoiceDiscountFloorApproverId = null;

    /** The manager whose PIN was already verified in this request (checked once, reused per permission). */
    private ?User $pinApprover = null;

    /**
     * @param  array{user_id?: mixed, pin?: mixed}|null  $override
     */
    public function __construct(
        private readonly ?User $actor,
        private readonly ?array $override = null,
    ) {}

    /**
     * @return array{price: string, source: string}
     */
    public function listPrice(Item $item, Customer $customer, ?int $storeId): array
    {
        $wholesale = (string) ($item->getAttribute('wholesale_price') ?? '0.000');

        if ($customer->price_tier === 'wholesale' && bccomp($wholesale, '0', 3) > 0) {
            return ['price' => Decimal::normalize($wholesale), 'source' => self::SOURCE_WHOLESALE];
        }

        return ['price' => Decimal::normalize($item->getEffectivePriceForStore($storeId)), 'source' => self::SOURCE_RETAIL];
    }

    /**
     * A missing unit price (null / empty) means "the list price": the server prices the line itself.
     *
     * @return array{unit_price: string, list_unit_price: string, price_source: string, override_approved_by: int|null}
     *
     * @throws ValidationException
     */
    public function evaluate(Item $item, Customer $customer, ?int $storeId, string $quantity, ?string $submittedUnitPrice, string $discount, int $index): array
    {
        $list = $this->listPrice($item, $customer, $storeId);
        $unitPrice = $submittedUnitPrice === null || trim($submittedUnitPrice) === '' ? $list['price'] : Decimal::normalize($submittedUnitPrice);
        $audit = [
            'unit_price' => $unitPrice,
            'list_unit_price' => $list['price'],
            'price_source' => $list['source'],
            'override_approved_by' => null,
        ];

        if (! $this->actor instanceof User) {
            return $audit;
        }

        $gross = Decimal::mul($quantity, $unitPrice);

        if (bccomp($discount, '0', 3) < 0 || bccomp($discount, $gross, 3) > 0) {
            throw ValidationException::withMessages([
                "items.{$index}.discount" => __('invoices.line_discount_exceeds_total'),
            ]);
        }

        $min = (string) ($item->min_selling_price ?? '0.000');
        if (bccomp($min, '0', 3) <= 0) {
            return $audit;
        }

        // Owner rule: anything whose net is below quantity x floor needs an approval, even at list price.
        if (bccomp(bcsub($gross, $discount, 3), Decimal::mul($quantity, $min), 3) >= 0) {
            return $audit;
        }

        // Inside the invoice transaction only a pre-verified approver is reused (preflight()).
        $audit['override_approved_by'] = $this->approverId
            ?? ($this->actor->can(self::PERMISSION) ? (int) $this->actor->id : throw $this->approvalRequired($index));

        return $audit;
    }

    /**
     * Runs BEFORE the invoice transaction: finds the first line that sells below its floor and, if so,
     * resolves the approver (permission, else manager PIN + rate limiter) so that failed-PIN limiter hits
     * can never be rolled back with the transaction. Later evaluate() calls only reuse that approver.
     *
     * @param  list<array<string, mixed>>|array<int, array<string, mixed>>  $lines
     *
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    public function preflight(Customer $customer, ?int $storeId, array $lines): void
    {
        if (! $this->actor instanceof User || $this->approverId !== null) {
            return;
        }

        foreach ($lines as $index => $line) {
            $item = Item::query()->find($line['item_id'] ?? 0);
            if (! $item instanceof Item) {
                continue;
            }

            $min = (string) ($item->min_selling_price ?? '0.000');
            if (bccomp($min, '0', 3) <= 0) {
                continue;
            }

            $submitted = isset($line['unit_price']) ? (string) $line['unit_price'] : '';
            $unitPrice = trim($submitted) === '' ? $this->listPrice($item, $customer, $storeId)['price'] : Decimal::normalize($submitted);
            $discount = Decimal::normalize((string) ($line['discount_amount'] ?? '0.000'));
            $qty = (string) ($line['quantity'] ?? '0');
            $gross = Decimal::mul($qty, $unitPrice);

            // Malformed discounts are reported by evaluate() inside the transaction.
            if (bccomp($discount, '0', 3) < 0 || bccomp($discount, $gross, 3) > 0) {
                continue;
            }

            if (bccomp(bcsub($gross, $discount, 3), Decimal::mul($qty, $min), 3) < 0) {
                $this->approve((int) $index, $storeId);

                return;
            }
        }
    }

    private function approvalRequired(int $index): ValidationException
    {
        // The floor value is never part of the message.
        return ValidationException::withMessages([
            "items.{$index}.unit_price" => __('pos.price_below_minimum'),
            'override' => __('pos.price_override_required'),
        ]);
    }

    /**
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    private function approve(int $index, ?int $storeId): int
    {
        if ($this->approverId !== null) {
            return $this->approverId;
        }

        $actor = $this->actor;
        if ($actor instanceof User && $actor->can(self::PERMISSION)) {
            return $this->approverId = (int) $actor->id;
        }

        $override = $this->override;
        if ($override === null || ! isset($override['user_id'], $override['pin'])) {
            throw $this->approvalRequired($index);
        }

        return $this->approverId = $this->verifyManagerPin((int) $override['user_id'], (string) $override['pin'], $storeId, self::PERMISSION);
    }

    /**
     * POS-07: approve a below-minimum sale caused by the invoice-level discount (the lines alone are fine).
     *
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    public function requireBelowMinApproval(int $index, ?int $storeId): void
    {
        $this->invoiceDiscountFloorApproverId = $this->approve($index, $storeId);
    }

    public function invoiceDiscountFloorApproverId(): ?int
    {
        return $this->invoiceDiscountFloorApproverId;
    }

    /**
     * POS-07: approval of a total discount above the store's cap. The acting user holds
     * `pos.discount.override`, else the request carries a manager {user_id, pin} whose user does.
     * Verified before the invoice transaction (limiter hits survive); returns the approver id.
     *
     * @throws DiscountCapExceededException
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    public function approveDiscount(string $maxPercent, ?int $storeId): int
    {
        if ($this->discountApproverId !== null) {
            return $this->discountApproverId;
        }

        $actor = $this->actor;
        if ($actor instanceof User && $actor->can(self::DISCOUNT_PERMISSION)) {
            return $this->discountApproverId = (int) $actor->id;
        }

        $override = $this->override;
        if ($override === null || ! isset($override['user_id'], $override['pin'])) {
            throw DiscountCapExceededException::forCap($maxPercent);
        }

        return $this->discountApproverId = $this->verifyManagerPin((int) $override['user_id'], (string) $override['pin'], $storeId, self::DISCOUNT_PERMISSION);
    }

    /** The user who approved an over-cap discount (null = none was needed / given). */
    public function discountApproverId(): ?int
    {
        return $this->discountApproverId;
    }

    private function verifyManagerPin(int $approverId, string $pin, ?int $storeId, string $permission): int
    {
        // The PIN of this manager was already verified for another approval of the same request.
        if ($this->pinApprover instanceof User && (int) $this->pinApprover->id === $approverId) {
            if ($this->pinApprover->can($permission)) {
                return $approverId;
            }

            throw ValidationException::withMessages(['override.pin' => __('pos.override_invalid')]);
        }

        $scope = RateLimitKey::scope();
        $approverKey = 'pos-override:'.$scope.':approver:'.$approverId;
        $actorKey = 'pos-override:'.$scope.':actor:'.$this->actor->id;

        if (RateLimiter::tooManyAttempts($approverKey, self::MAX_ATTEMPTS_PER_APPROVER)
            || RateLimiter::tooManyAttempts($actorKey, self::MAX_ATTEMPTS_PER_ACTOR)) {
            $wait = max(RateLimiter::availableIn($approverKey), RateLimiter::availableIn($actorKey), 1);

            throw new ThrottleRequestsException(__('pos.override_throttled'), null, ['Retry-After' => (string) $wait]);
        }

        $approver = User::query()->whereKey($approverId)->where('is_active', true)->first();

        // One generic failure for unknown user / no permission / no PIN / wrong PIN / no store access.
        $valid = $approver instanceof User
            && $approver->can($permission)
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
        $this->pinApprover = $approver;

        return (int) $approver->id;
    }
}
