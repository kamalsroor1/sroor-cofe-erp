<?php

declare(strict_types=1);

namespace App\Actions\Landing;

use App\Http\Resources\Landing\PublicAddonResource;
use App\Http\Resources\Landing\PublicPlanResource;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Services\Billing\FounderPricingService;
use App\Services\Branding\PlatformBranding;
use App\Support\PublicCatalogCache;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

/**
 * PRD-07: the public pricing payload, read live from the central catalog (plans,
 * plan_features, addons). Nothing here is hardcoded: prices, limits, founder state and
 * brand all come from the database / platform settings. Hidden features and hidden
 * add-ons (is_public = false) never appear. Cached for a few minutes per locale and
 * invalidated by PublicCatalogObserver.
 */
final class GetPublicPlansAction
{
    public function __construct(
        private readonly FounderPricingService $founderPricing,
        private readonly PlatformBranding $branding,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $locale): array
    {
        return PublicCatalogCache::remember($locale, fn (): array => $this->build());
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $founder = $this->founderState();

        /** @var Collection<string, PlanFeature> $publicFeatures */
        $publicFeatures = PlanFeature::query()->public()->orderBy('sort_order')->orderBy('id')->get()->keyBy('key');
        $hiddenKeys = PlanFeature::query()->where('is_public', false)->pluck('key')->all();

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $addons = Addon::query()->active()->visible()->with('planAddons.plan')->get()
            ->reject(fn (Addon $addon): bool => $addon->feature_key !== null && in_array($addon->feature_key, $hiddenKeys, true));

        $publicCodes = $plans->pluck('slug')->all();

        $brand = $this->branding->get();

        return [
            'currency' => Subscription::DEFAULT_CURRENCY,
            'prices_exclude_vat' => true,
            'founder' => $founder,
            'brand' => [
                'name' => $brand->name,
                'subtitle' => $brand->subtitle,
                'support_phone' => $brand->supportPhone,
                'support_email' => $brand->supportEmail,
                'website_url' => $brand->websiteUrl,
            ],
            'plans' => $plans
                ->map(fn (Plan $plan): array => (new PublicPlanResource($plan, $publicFeatures, $founder['available']))->resolve(new Request))
                ->values()
                ->all(),
            'addons' => $addons
                ->map(fn (Addon $addon): array => (new PublicAddonResource($addon, $this->addonPlans($addon, $publicCodes)))->resolve(new Request))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{available: bool, slots_left: int|null, months: int|null}
     */
    private function founderState(): array
    {
        try {
            $left = $this->founderPricing->remainingSlots();

            return [
                'available' => $left > 0,
                'slots_left' => $left,
                'months' => $this->founderPricing->durationMonths(),
            ];
        } catch (Throwable) {
            // Misconfigured slots or no counter table: show no founder offer rather than fail the page.
            return ['available' => false, 'slots_left' => null, 'months' => null];
        }
    }

    /**
     * Plans the add-on is sold on, with the plan-specific override price when set.
     *
     * @param  list<string>  $publicCodes
     * @return list<array{code: string, unit_price: string, yearly_price: string|null}>
     */
    private function addonPlans(Addon $addon, array $publicCodes): array
    {
        $rows = [];

        foreach ($addon->planAddons as $planAddon) {
            $plan = $planAddon->plan;

            if (! $planAddon->is_available || ! in_array($plan->slug, $publicCodes, true)) {
                continue;
            }

            $unit = $planAddon->unit_price_override ?? $addon->unit_price;
            $yearly = $planAddon->yearly_price_override ?? $addon->yearly_price;

            $rows[] = [
                'code' => $plan->slug,
                'unit_price' => bcadd((string) $unit, '0', 3),
                'yearly_price' => $yearly === null ? null : bcadd((string) $yearly, '0', 3),
            ];
        }

        return $rows;
    }
}
