<?php

declare(strict_types=1);

namespace App\Services\Signup;

use App\Enums\Billing\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Services\Branding\PlatformBranding;
use Carbon\CarbonImmutable;

/**
 * SELL-06 (owner decision 2026-11-12, option A): every approved signup starts a trial on the
 * plan it chose, so the shop works at once with that plan's features and limits.
 *
 *  - a plan with its own trial_days > 0 keeps that length;
 *  - a plan with trial_days = 0 (basic / pro / enterprise) gets the PLATFORM default
 *    (platform setting `signup_trial_days`, else config tenant_lifecycle.trial_days = 14);
 *  - PLAN-10: a permanent free plan (`is_free`) has NO trial: the tenant is provisioned
 *    `active` with no end date (TenantProvisionerService), so trialDaysFor() is 0.
 *
 * Without a chosen plan (PLAN-10): the platform setting `default_signup_plan_id` when it names an
 * active plan, else the first active plan with trial days, else the first active free plan.
 *
 * One source for ApproveSignupRequestAction (what is provisioned) and SignupRequestResource
 * (the console's "the shop will be created in state trial until X" preview), so the preview
 * can never disagree with the result. Bound `scoped`: the active plans are read once per
 * request / job, not once per listed signup request.
 */
final class SignupTrialTerms
{
    /** @var array<string, Plan>|null active plans by slug, loaded on first use */
    private ?array $activePlans = null;

    public function __construct(private readonly PlatformBranding $platform) {}

    /** Platform trial length for plans without their own trial (whole days, >= 1). */
    public function defaultTrialDays(): int
    {
        return $this->platform->get()->signupTrialDays;
    }

    /** Trial days the tenant of $plan is provisioned with: 0 for a permanent free plan, else >= 1. */
    public function trialDaysFor(Plan $plan): int
    {
        if ($plan->is_free) {
            return 0;
        }

        return $plan->trial_days > 0 ? $plan->trial_days : $this->defaultTrialDays();
    }

    /**
     * The requested active plan; without a code, the platform's default signup plan (PLAN-10,
     * `default_signup_plan_id`, when active), else the first active plan with trial days, else
     * the first active permanent free plan (by sort order). Null when nothing matches.
     */
    public function resolvePlan(?string $planCode): ?Plan
    {
        $plans = $this->activePlans();

        if ($planCode !== null) {
            // Slugs are stored lowercase; the lookup ignores case and surrounding blanks.
            return $plans[strtolower(trim($planCode))] ?? null;
        }

        $defaultId = $this->platform->get()->defaultSignupPlanId;
        if ($defaultId !== null) {
            foreach ($plans as $plan) {
                if ((int) $plan->getKey() === $defaultId) {
                    return $plan;
                }
            }
        }

        foreach ($plans as $plan) {
            if (! $plan->is_free && $plan->trial_days > 0) {
                return $plan;
            }
        }

        foreach ($plans as $plan) {
            if ($plan->is_free) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * What approving a request for $planCode at $now would create, for the console preview.
     * Null when the plan cannot be resolved (approval would answer 422 plan_code).
     *
     * PLAN-10: a permanent free plan previews `active` / `active` with trial_days 0 and
     * trial_ends_at null (`plan.is_free` true).
     *
     * @return array{plan: array{id: int, code: string, name: string, trial_days: int, is_free: bool}, default_trial_days: int, trial_days: int, tenant_status: array{value: string, label: string}, subscription_status: array{value: string, label: string}, trial_ends_at: string|null}|null
     */
    public function preview(?string $planCode, CarbonImmutable $now): ?array
    {
        $plan = $this->resolvePlan($planCode);

        if (! $plan instanceof Plan) {
            return null;
        }

        $days = $this->trialDaysFor($plan);
        $free = (bool) $plan->is_free;
        $tenantStatus = $free ? TenantStatus::Active : TenantStatus::Trial;
        $subscriptionStatus = $free ? SubscriptionStatus::Active : SubscriptionStatus::Trialing;

        return [
            'plan' => ['id' => (int) $plan->getKey(), 'code' => $plan->slug, 'name' => $plan->name, 'trial_days' => $plan->trial_days, 'is_free' => $free],
            'default_trial_days' => $this->defaultTrialDays(),
            'trial_days' => $days,
            'tenant_status' => ['value' => $tenantStatus->value, 'label' => $tenantStatus->label()],
            'subscription_status' => ['value' => $subscriptionStatus->value, 'label' => $subscriptionStatus->label()],
            'trial_ends_at' => $free ? null : $now->addDays($days)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, Plan>
     */
    private function activePlans(): array
    {
        if ($this->activePlans === null) {
            $this->activePlans = [];

            foreach (Plan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() as $plan) {
                $this->activePlans[strtolower($plan->slug)] ??= $plan;
            }
        }

        return $this->activePlans;
    }
}
