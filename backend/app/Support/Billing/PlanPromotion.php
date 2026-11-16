<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Models\Plan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * PRICE-2026-11: a time-bound "was / now" offer on a plan (plans.compare_at_price_*,
 * promo_starts_at, promo_ends_at). The "now" price is always the plan's real list price; the
 * compare-at price is only the crossed-out "was" figure, it never changes what is billed.
 *
 * A "was" price is shown only for a REAL promotion:
 *  - promo_ends_at is set (an offer without an end is never shown);
 *  - the compare-at price of a cycle is above that cycle's real price (a cycle whose compare-at
 *    is missing or not higher is dropped; when both are dropped there is no promotion);
 *  - the moment is inside [promo_starts_at, promo_ends_at), promo_starts_at NULL = started.
 * UpdatePlanRequest refuses to store anything else, but a later price edit can still make a
 * stored compare-at stale, so this class re-checks instead of trusting the row.
 */
final readonly class PlanPromotion
{
    public function __construct(
        public ?string $compareAtMonthly,
        public ?string $compareAtYearly,
        public ?CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}

    /** The plan's promotion window and valid compare-at prices, or null when it has none. */
    public static function fromPlan(Plan $plan): ?self
    {
        if ($plan->promo_ends_at === null) {
            return null;
        }

        $monthly = self::validCompareAt($plan->compare_at_price_monthly, $plan->price_monthly);
        $yearly = self::validCompareAt($plan->compare_at_price_yearly, $plan->price_yearly);

        if ($monthly === null && $yearly === null) {
            return null;
        }

        return new self(
            $monthly,
            $yearly,
            $plan->promo_starts_at === null ? null : CarbonImmutable::instance($plan->promo_starts_at),
            CarbonImmutable::instance($plan->promo_ends_at),
        );
    }

    /**
     * Rebuilds a promotion stored with toCacheArray() (the public catalog cache).
     *
     * @param  array{compare_at_monthly: string|null, compare_at_yearly: string|null, starts_at: string|null, ends_at: string}  $data
     */
    public static function fromCacheArray(array $data): self
    {
        return new self(
            $data['compare_at_monthly'],
            $data['compare_at_yearly'],
            $data['starts_at'] === null ? null : CarbonImmutable::parse($data['starts_at']),
            CarbonImmutable::parse($data['ends_at']),
        );
    }

    public function isActiveAt(CarbonInterface $at): bool
    {
        if ($this->startsAt !== null && $this->startsAt->greaterThan($at)) {
            return false;
        }

        return $this->endsAt->greaterThan($at);
    }

    /**
     * The public / console shape of a running promotion.
     *
     * @return array{compare_at_monthly: string|null, compare_at_yearly: string|null, ends_at: string}
     */
    public function toArray(): array
    {
        return [
            'compare_at_monthly' => $this->compareAtMonthly,
            'compare_at_yearly' => $this->compareAtYearly,
            'ends_at' => $this->endsAt->toIso8601String(),
        ];
    }

    /**
     * Everything needed to decide later whether the promotion runs (includes the start).
     *
     * @return array{compare_at_monthly: string|null, compare_at_yearly: string|null, starts_at: string|null, ends_at: string}
     */
    public function toCacheArray(): array
    {
        return [
            'compare_at_monthly' => $this->compareAtMonthly,
            'compare_at_yearly' => $this->compareAtYearly,
            'starts_at' => $this->startsAt?->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
        ];
    }

    private static function validCompareAt(?string $compareAt, ?string $price): ?string
    {
        if ($compareAt === null || $price === null) {
            return null;
        }

        return bccomp($compareAt, $price, 3) > 0 ? bcadd($compareAt, '0', 3) : null;
    }
}
