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
 *  - a plan with its own trial_days > 0 keeps that length (e.g. `free`, 14 days);
 *  - a plan with trial_days = 0 (basic / pro / enterprise) gets the PLATFORM default
 *    (platform setting `signup_trial_days`, else config tenant_lifecycle.trial_days = 14).
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

    /** Trial days the tenant of $plan is provisioned with (always >= 1). */
    public function trialDaysFor(Plan $plan): int
    {
        return $plan->trial_days > 0 ? $plan->trial_days : $this->defaultTrialDays();
    }

    /**
     * The requested active plan; without a code, the catalog's trial plan (first active plan
     * with trial days, by sort order). Null when nothing matches.
     */
    public function resolvePlan(?string $planCode): ?Plan
    {
        $plans = $this->activePlans();

        if ($planCode !== null) {
            // Slugs are stored lowercase; the lookup ignores case and surrounding blanks.
            return $plans[strtolower(trim($planCode))] ?? null;
        }

        foreach ($plans as $plan) {
            if ($plan->trial_days > 0) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * What approving a request for $planCode at $now would create, for the console preview.
     * Null when the plan cannot be resolved (approval would answer 422 plan_code).
     *
     * @return array{plan: array{id: int, code: string, name: string, trial_days: int}, default_trial_days: int, trial_days: int, tenant_status: array{value: string, label: string}, subscription_status: array{value: string, label: string}, trial_ends_at: string}|null
     */
    public function preview(?string $planCode, CarbonImmutable $now): ?array
    {
        $plan = $this->resolvePlan($planCode);

        if (! $plan instanceof Plan) {
            return null;
        }

        $days = $this->trialDaysFor($plan);

        return [
            'plan' => ['id' => (int) $plan->getKey(), 'code' => $plan->slug, 'name' => $plan->name, 'trial_days' => $plan->trial_days],
            'default_trial_days' => $this->defaultTrialDays(),
            'trial_days' => $days,
            'tenant_status' => ['value' => TenantStatus::Trial->value, 'label' => TenantStatus::Trial->label()],
            'subscription_status' => ['value' => SubscriptionStatus::Trialing->value, 'label' => SubscriptionStatus::Trialing->label()],
            'trial_ends_at' => $now->addDays($days)->toIso8601String(),
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
