/**
 * Pure helpers over the public pricing catalog (GET /public/plans) for the marketing pages:
 * yearly saving, founder prices, the merged feature list of the comparison table and the default
 * plan pair of the mobile comparison. No Vue, no translations, so they run under `node --test`.
 * Prices are compared as BigInt thousandths (toMilli), never as floats.
 */
import { dAdd, dPercent, fromMilli, toMilli } from './decimal.js';

/** True when the platform VAT rate (percent string from the API, e.g. "14.000") adds anything. */
export function hasVat(rate) {
    return toMilli(rate) > 0n;
}

/** "14.000" -> "14", "14.500" -> "14.5": the rate as shown in "incl. 14% VAT". */
export function vatRateLabel(rate) {
    return fromMilli(toMilli(rate)).replace(/\.?0+$/, '');
}

/**
 * Whole-percent saving of paying yearly instead of 12 monthly payments, in BigInt thousandths
 * (display only, never sent anywhere). Returns 0 when there is no saving or a price is missing.
 */
export function yearlySavingPercent(monthly, yearly) {
    const twelveMonths = toMilli(monthly) * 12n;
    const year = toMilli(yearly);
    if (twelveMonths <= 0n || year <= 0n || year >= twelveMonths) return 0;
    return Number(((twelveMonths - year) * 100n) / twelveMonths);
}

/**
 * The best yearly saving across the catalog. `upTo` is true when paid plans save different
 * percentages, so the label must read "save up to X%".
 *
 * @returns {{ percent: number, upTo: boolean }}
 */
export function catalogYearlySaving(plans = []) {
    const savings = plans
        .map((plan) => yearlySavingPercent(plan.price_monthly, plan.price_yearly))
        .filter((percent) => percent > 0);
    if (!savings.length) return { percent: 0, upTo: false };
    const percent = Math.max(...savings);
    return { percent, upTo: savings.some((value) => value !== percent) };
}

/**
 * A catalog price with the API's VAT rate added (half-up at scale 3, then rounded to 2 decimals by
 * the formatter). Display only: invoices are computed on the server.
 */
export function priceWithVat(value, rate) {
    return dAdd(value, dPercent(value, rate));
}

/** Whole-percent discount of `price` against `compareAt` (0 when there is no real discount). */
export function discountPercent(compareAt, price) {
    const was = toMilli(compareAt);
    const now = toMilli(price);
    if (was <= 0n || now < 0n || now >= was) return 0;
    return Number(((was - now) * 100n) / was);
}

/**
 * The price a card shows for the chosen billing cycle: the founder price while the offer has slots,
 * otherwise the regular one. `compareAt` is the struck "was" price: the regular price next to a
 * founder price, or the plan promotion's compare-at price (only while the API sends a promotion
 * that really is higher than the price shown). Never set without a real offer.
 */
export function planDisplayPrice(plan, billing = 'monthly', founder = { available: false }) {
    const yearly = billing === 'yearly';
    const regular = yearly ? plan?.price_yearly : plan?.price_monthly;
    const founderPrice = yearly ? plan?.founder_price_yearly : plan?.founder_price_monthly;
    const hasFounder = Boolean(founder?.available && founderPrice);
    const shown = hasFounder ? founderPrice : regular;

    const promo = plan?.promotion || null;
    const promoCompareAt = promo ? (yearly ? promo.compare_at_yearly : promo.compare_at_monthly) : null;
    const hasPromo = Boolean(!hasFounder && promoCompareAt && toMilli(promoCompareAt) > toMilli(shown));
    const compareAt = hasFounder ? regular : hasPromo ? promoCompareAt : null;

    return {
        regular,
        founderPrice: hasFounder ? founderPrice : null,
        shown,
        hasFounder,
        hasPromo,
        compareAt,
        discountPercent: hasPromo ? discountPercent(promoCompareAt, shown) : 0,
        promoEndsAt: hasPromo ? promo.ends_at || null : null,
    };
}

/**
 * The transparent founder schedule of one plan for a billing cycle: step 1 founder price, step 2
 * transition price, then the list price. `from` is the first month of each step. Steps whose price
 * the API did not send are left out. Empty when the founder offer is not available.
 *
 * @returns {Array<{ step: 'founder'|'transition'|'list', price: string, from: number, to: number|null }>}
 */
export function founderSchedule(plan, billing = 'monthly', founder = { available: false }) {
    if (!founder?.available) return [];
    const yearly = billing === 'yearly';
    const first = yearly ? plan?.founder_price_yearly : plan?.founder_price_monthly;
    const second = yearly ? plan?.founder_transition_price_yearly : plan?.founder_transition_price_monthly;
    const list = yearly ? plan?.price_yearly : plan?.price_monthly;
    const months = Number(founder.months) || 0;
    const transition = Number(founder.transition_months) || 0;
    if (!first || !months) return [];

    const steps = [{ step: 'founder', price: first, from: 1, to: months }];
    if (second && transition)
        steps.push({ step: 'transition', price: second, from: months + 1, to: months + transition });
    steps.push({ step: 'list', price: list, from: months + (second && transition ? transition : 0) + 1, to: null });
    return steps;
}

/**
 * Union of the features every plan lists, in catalog order. Each plan's list is already sorted by
 * the registry's sort_order, so a feature missing from earlier plans is inserted right after the
 * feature that precedes it in the plan that has it.
 */
export function mergeCatalogFeatures(plans = []) {
    const merged = [];
    const index = new Map();
    plans.forEach((plan) => {
        let previous = null;
        (plan.features || []).forEach((feature) => {
            if (!index.has(feature.key)) {
                const at = previous === null ? 0 : merged.findIndex((f) => f.key === previous) + 1;
                merged.splice(at, 0, feature);
                index.set(feature.key, true);
            }
            previous = feature.key;
        });
    });
    return merged;
}

/** Features grouped by their registry module, groups in first-seen order. */
export function groupCatalogFeatures(features = []) {
    const groups = [];
    features.forEach((feature) => {
        const module = feature.module || 'other';
        let group = groups.find((g) => g.module === module);
        if (!group) {
            group = { module, features: [] };
            groups.push(group);
        }
        group.features.push(feature);
    });
    return groups;
}

/**
 * A plan's features with the ones that set it apart (not included in every plan) first, so a short
 * card list shows what differs instead of the same core features on every card.
 */
export function distinctFirstFeatures(plan, plans = []) {
    const list = plan?.features || [];
    if (plans.length < 2) return list;
    const everywhere = (key) => plans.every((p) => (p.features || []).some((f) => f.key === key));
    return [...list.filter((f) => !everywhere(f.key)), ...list.filter((f) => everywhere(f.key))];
}

/** The two plans the mobile comparison starts with: the popular one and its cheaper neighbour. */
export function defaultComparePair(plans = []) {
    if (plans.length < 2) return plans.map((plan) => plan.code);
    const popular = Math.max(
        0,
        plans.findIndex((plan) => plan.is_popular)
    );
    const other = popular > 0 ? popular - 1 : popular + 1;
    return [plans[Math.min(popular, other)].code, plans[Math.max(popular, other)].code];
}
