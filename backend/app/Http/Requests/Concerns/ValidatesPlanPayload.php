<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Addon;
use App\Models\Plan;
use App\Models\PlanFeature;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Shared validation of the super-admin plan editor payload (POST /plans and PUT /plans/{id}).
 *
 * The using FormRequest supplies storedPlan(): the plan being edited, or null when creating.
 * Every cross-field rule is checked on the plan AFTER the write (a field the request omits keeps
 * its stored value; on create there is no stored value).
 *
 * Money leaves validated() as DECIMAL(12,3) strings, dates as 'Y-m-d H:i:s' in the app timezone.
 */
trait ValidatesPlanPayload
{
    private const MONEY = ['numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'];

    /** Features that must be on whenever `pos.access` is. */
    private const POS_DEPENDENCIES = ['invoices.create', 'shifts.manage'];

    private const MAX_LIMIT = 'max:1000000000';

    /** Money fields that leave validated() as DECIMAL(12,3) strings. */
    private const MONEY_FIELDS = [
        'price_monthly',
        'price_yearly',
        'founder_price_monthly',
        'founder_price_yearly',
        'founder_transition_price_monthly',
        'founder_transition_price_yearly',
        'compare_at_price_monthly',
        'compare_at_price_yearly',
    ];

    /** Fields of the promotion; changing any of them is "writing" the offer. */
    private const PROMO_FIELDS = ['compare_at_price_monthly', 'compare_at_price_yearly', 'promo_starts_at', 'promo_ends_at'];

    private const DATE_FIELDS = ['promo_starts_at', 'promo_ends_at'];

    private const CYCLES = ['monthly', 'yearly'];

    /** The plan being edited; null on create. */
    abstract protected function storedPlan(): ?Plan;

    /**
     * Limits: null = unlimited. users/stores/items must be present (the console form always sends
     * them); the other limits are optional and an omitted field is left untouched on update.
     * PLAN-ENF: max_invoices_per_month / max_storage_mb are enforced and written like the others
     * (on create an omitted one is NULL = unlimited, see CreatePlanAction).
     *
     * @return array<string, list<mixed>>
     */
    protected function planRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'name_key' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price_monthly' => ['required', ...self::MONEY],
            'price_yearly' => ['required', ...self::MONEY],
            'founder_price_monthly' => ['sometimes', 'nullable', ...self::MONEY],
            'founder_price_yearly' => ['sometimes', 'nullable', ...self::MONEY],
            // PRICE-2026-11: founder months 13-24 (null = straight to the list price after year 1).
            'founder_transition_price_monthly' => ['sometimes', 'nullable', ...self::MONEY],
            'founder_transition_price_yearly' => ['sometimes', 'nullable', ...self::MONEY],
            // PRICE-2026-11: promotion "was" price + window (null compare-at = no offer for the cycle).
            'compare_at_price_monthly' => ['sometimes', 'nullable', ...self::MONEY],
            'compare_at_price_yearly' => ['sometimes', 'nullable', ...self::MONEY],
            'promo_starts_at' => ['sometimes', 'nullable', 'date'],
            'promo_ends_at' => ['sometimes', 'nullable', 'date'],
            'max_users' => ['present', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'max_stores' => ['present', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'max_items' => ['present', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'max_invoices_per_month' => ['sometimes', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'max_storage_mb' => ['sometimes', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'max_warehouses' => ['sometimes', 'nullable', 'integer', 'min:0', self::MAX_LIMIT],
            'max_vans' => ['sometimes', 'nullable', 'integer', 'min:0', self::MAX_LIMIT],
            // PLAN-11: optional like the other newer limits (omitted on create = unlimited).
            'max_customers' => ['sometimes', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'max_suppliers' => ['sometimes', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            // PLAN-10: permanent free plan; every price must then be 0 (validateFreePlan()).
            'is_free' => ['sometimes', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'is_popular' => ['required', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'features' => ['required', 'array'],
            // Add-on availability per plan; add-ons not listed are left as they are.
            'addons' => ['sometimes', 'array', 'max:200'],
            'addons.*' => ['array'],
            'addons.*.addon_id' => ['required', 'integer', 'distinct', Rule::exists(Addon::class, 'id')],
            'addons.*.is_available' => ['required', 'boolean'],
            'addons.*.included_quantity' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'addons.*.included_billing_cycle' => ['sometimes', 'nullable', Rule::in(['monthly', 'yearly'])],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateFeatures($validator),
            fn (Validator $validator) => $this->validateAddons($validator),
            fn (Validator $validator) => $this->validatePricing($validator),
            fn (Validator $validator) => $this->validateFreePlan($validator),
        ];
    }

    /**
     * Money leaves the request as numeric strings, never floats (golden rule 1).
     *
     * @param  array-key|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        foreach (self::MONEY_FIELDS as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = $this->moneyString($data[$field]);
            }
        }

        // Stored in the app timezone (the datetime cast drops a client offset instead of converting).
        foreach (self::DATE_FIELDS as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $data[$field] = CarbonImmutable::parse((string) $data[$field])
                    ->setTimezone((string) config('app.timezone'))
                    ->format('Y-m-d H:i:s');
            } elseif (array_key_exists($field, $data)) {
                $data[$field] = null;
            }
        }

        return $key === null ? $data : data_get($data, $key, $default);
    }

    /**
     * Every key must be a registered feature (plan_features, the runtime copy of the catalog).
     * Hidden keys are accepted but never written: the response lists them as ignored. FE-03: a
     * plan that sells POS must also sell what POS checkout and shift lookup call.
     */
    private function validateFeatures(Validator $validator): void
    {
        $features = $this->input('features');
        if (! is_array($features)) {
            return;
        }

        $known = PlanFeature::query()->pluck('key')->all();
        foreach (array_keys($features) as $key) {
            if (! in_array((string) $key, $known, true)) {
                $validator->errors()->add('features', (string) __('super.plan_feature_unknown', ['feature' => (string) $key]));
            }
        }

        if (! $this->featureOn($features, 'pos.access')) {
            return;
        }

        foreach (self::POS_DEPENDENCIES as $dependency) {
            if (! $this->featureOn($features, $dependency)) {
                $validator->errors()->add('features', (string) __('super.plan_pos_requires', [
                    'feature' => $dependency,
                ]));
            }
        }
    }

    /**
     * Only a SERVICE-like add-on (no feature, no feature bundle, no bundled limits) can be
     * included in a plan: the entitlement engine does not read included quantities, so an
     * "included" store or feature add-on would promise something nothing grants.
     */
    private function validateAddons(Validator $validator): void
    {
        $rows = $this->input('addons');
        if (! is_array($rows) || $validator->errors()->isNotEmpty()) {
            return;
        }

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $included = (int) ($row['included_quantity'] ?? 0) > 0;
            $offered = filter_var($row['is_available'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $addon = Addon::query()->find((int) ($row['addon_id'] ?? 0));
            if (! $addon instanceof Addon) {
                continue;
            }

            // A retired (inactive) add-on can still be marked unavailable, never offered or included.
            if (! $addon->is_active && ($offered || $included)) {
                $validator->errors()->add("addons.{$index}.addon_id", (string) __('billing.plan_editor.addon_inactive', ['addon' => $addon->key]));

                continue;
            }

            if ($included && ! $addon->isIncludable()) {
                $validator->errors()->add("addons.{$index}.included_quantity", (string) __('super.plan_addon_include_not_allowed', ['addon' => $addon->key]));
            }
        }
    }

    /**
     * PRICE-2026-11, checked on the plan after the write:
     *  - a compare-at ("was") price must be above the plan price of the same cycle;
     *  - an offer (any compare-at) needs promo_ends_at, after promo_starts_at, and in the future
     *    whenever this request changes the offer (an untouched, ended offer does not block
     *    editing the rest of the plan: it is simply not shown);
     *  - a transition price needs a founder price and lies between it and the list price;
     *  - a founder price is not above the list price of the same cycle.
     */
    private function validatePricing(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $plan = $this->storedPlan();
        $hasOffer = false;

        foreach (self::CYCLES as $cycle) {
            $price = $this->moneyAfter($plan, 'price_'.$cycle);
            $compareAt = $this->moneyAfter($plan, 'compare_at_price_'.$cycle);
            $founder = $this->moneyAfter($plan, 'founder_price_'.$cycle);
            $transition = $this->moneyAfter($plan, 'founder_transition_price_'.$cycle);

            if ($founder !== null && $price !== null && bccomp($founder, $price, 3) > 0) {
                $validator->errors()->add('founder_price_'.$cycle, (string) __('billing.plan_editor.founder_above_list'));
            }

            if ($compareAt !== null) {
                $hasOffer = true;
                if ($price !== null && bccomp($compareAt, $price, 3) <= 0) {
                    $validator->errors()->add('compare_at_price_'.$cycle, (string) __('super.plan_compare_at_not_above_price'));
                }
            }

            if ($transition === null) {
                continue;
            }

            if ($founder === null) {
                $validator->errors()->add('founder_transition_price_'.$cycle, (string) __('super.plan_transition_requires_founder'));
            } elseif (bccomp($transition, $founder, 3) < 0 || ($price !== null && bccomp($transition, $price, 3) > 0)) {
                $validator->errors()->add('founder_transition_price_'.$cycle, (string) __('super.plan_transition_out_of_range'));
            }
        }

        $startsAt = $this->dateAfter($plan, 'promo_starts_at');
        $endsAt = $this->dateAfter($plan, 'promo_ends_at');

        if ($startsAt !== null && $endsAt !== null && ! $endsAt->greaterThan($startsAt)) {
            $validator->errors()->add('promo_ends_at', (string) __('super.plan_promo_window_invalid'));

            return;
        }

        if (! $hasOffer) {
            return;
        }

        if ($endsAt === null) {
            $validator->errors()->add('promo_ends_at', (string) __('super.plan_promo_end_required'));
        } elseif ($this->changesPromotion($plan) && ! $endsAt->isFuture()) {
            $validator->errors()->add('promo_ends_at', (string) __('super.plan_promo_end_past'));
        }
    }

    /**
     * PLAN-10, checked on the plan after the write: a permanent free plan (is_free) is never
     * billed, so its monthly and yearly prices are 0 and it has no founder, transition or
     * compare-at ("was") price, and no trial days (review).
     */
    private function validateFreePlan(Validator $validator): void
    {
        $plan = $this->storedPlan();
        $isFree = $this->has('is_free')
            ? filter_var($this->input('is_free'), FILTER_VALIDATE_BOOLEAN)
            : (bool) $plan?->is_free;

        if (! $isFree) {
            return;
        }

        // A permanent free plan has no trial (the tenant is active from day one).
        $trialDays = $this->has('trial_days') ? $this->input('trial_days') : $plan?->trial_days;
        if (is_numeric($trialDays) && (int) $trialDays > 0) {
            $validator->errors()->add('trial_days', (string) __('billing.plan_editor.free_plan_trial_days'));
        }

        foreach (self::CYCLES as $cycle) {
            $price = $this->moneyAfter($plan, 'price_'.$cycle);
            if ($price !== null && bccomp($price, '0', 3) !== 0) {
                $validator->errors()->add('price_'.$cycle, (string) __('billing.plan_editor.free_plan_price_must_be_zero'));
            }

            foreach (['founder_price_', 'founder_transition_price_', 'compare_at_price_'] as $prefix) {
                if ($this->moneyAfter($plan, $prefix.$cycle) !== null) {
                    $validator->errors()->add($prefix.$cycle, (string) __('billing.plan_editor.free_plan_no_special_price'));
                }
            }
        }
    }

    /** The money value of $field after this write, as a DECIMAL(12,3) string or null. */
    private function moneyAfter(?Plan $plan, string $field): ?string
    {
        $value = $this->has($field) ? $this->input($field) : $plan?->getAttribute($field);

        return $value === null || $value === '' ? null : $this->moneyString($value);
    }

    private function dateAfter(?Plan $plan, string $field): ?CarbonImmutable
    {
        $value = $this->has($field) ? $this->input($field) : $plan?->getAttribute($field);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    /** On create every submitted offer field is a change. */
    private function changesPromotion(?Plan $plan): bool
    {
        foreach (self::PROMO_FIELDS as $field) {
            if (! $this->has($field)) {
                continue;
            }

            if (in_array($field, self::DATE_FIELDS, true)) {
                $stored = $plan?->getAttribute($field);
                $before = $stored instanceof DateTimeInterface ? CarbonImmutable::instance($stored) : null;
                $after = $this->dateAfter($plan, $field);
                if ($before?->getTimestamp() !== $after?->getTimestamp()) {
                    return true;
                }

                continue;
            }

            $before = $plan?->getAttribute($field);
            $after = $this->moneyAfter($plan, $field);
            if (($before === null) !== ($after === null) || ($before !== null && $after !== null && bccomp((string) $before, $after, 3) !== 0)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<array-key, mixed>  $features */
    private function featureOn(array $features, string $key): bool
    {
        return filter_var($features[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function moneyString(mixed $value): string
    {
        // JSON numbers decode to float; they were already validated to <= 3 decimals,
        // so formatting at scale 3 is exact.
        $string = is_float($value) ? sprintf('%.3F', $value) : (string) $value;

        return bcadd($string, '0', 3);
    }
}
