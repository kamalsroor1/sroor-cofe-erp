import { dAdd, dPercent, dRoundTo, toMilli } from './decimal.js';
import { isUnlimited, normalizeLimit, validateLimit } from './planLimits.js';

// Plan editor helpers (super-admin). Money stays a string end to end: the server owns the real values.

export const PLAN_EDITOR_TABS = ['basics', 'prices', 'promotion', 'limits', 'features', 'addons'];

export const MONEY_FIELDS = [
    'price_monthly',
    'price_yearly',
    'founder_price_monthly',
    'founder_price_yearly',
    'founder_transition_price_monthly',
    'founder_transition_price_yearly',
    'compare_at_price_monthly',
    'compare_at_price_yearly',
];

/** Limits the server enforces (null = unlimited; storage is in MB). */
export const EDITOR_LIMIT_FIELDS = [
    { key: 'max_users', min: 1, label: 'super.users_limit_label' },
    { key: 'max_stores', min: 1, label: 'super.stores_limit_label' },
    { key: 'max_items', min: 1, label: 'super.items_limit_label' },
    { key: 'max_invoices_per_month', min: 1, label: 'super.invoices_limit_label' },
    { key: 'max_storage_mb', min: 1, label: 'super.storage_limit_label' },
    { key: 'max_warehouses', min: 0, label: 'super.warehouses_limit_label' },
    { key: 'max_vans', min: 0, label: 'super.vans_limit_label' },
];

/** Which tab owns a (possibly nested) API error key. */
export function tabOfErrorKey(key) {
    const root = String(key).split('.')[0];
    if (['price_monthly', 'price_yearly', 'founder_price_monthly', 'founder_price_yearly'].includes(root)) {
        return 'prices';
    }
    if (root.startsWith('founder_transition_price')) return 'prices';
    if (root.startsWith('compare_at_price') || root.startsWith('promo_')) return 'promotion';
    if (EDITOR_LIMIT_FIELDS.some((f) => f.key === root)) return 'limits';
    if (root === 'features') return 'features';
    if (root === 'addons') return 'addons';
    return 'basics';
}

const moneyText = (value) => (value === null || value === undefined ? '' : String(value));

const pad2 = (n) => String(Math.abs(n)).padStart(2, '0');

/**
 * ISO 8601 from the API ("2026-12-01T02:00:00+02:00") -> the browser's local wall time for a
 * datetime-local input ("2026-12-01T02:00"). The instant is kept; only the zone it is shown in changes.
 */
export function toDatetimeLocal(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}T${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
}

/**
 * datetime-local value (browser local time) -> ISO 8601 carrying the browser's own offset
 * ("2026-12-01T02:00:00+02:00"), so the server reads the same instant the admin typed in any timezone.
 * Returns null for an empty or unparsable value.
 */
export function fromDatetimeLocal(local) {
    if (!local) return null;
    const date = new Date(local);
    if (Number.isNaN(date.getTime())) return null;
    const offset = -date.getTimezoneOffset();
    const sign = offset < 0 ? '-' : '+';
    const zone = `${sign}${pad2(Math.trunc(Math.abs(offset) / 60))}:${pad2(Math.abs(offset) % 60)}`;
    return `${toDatetimeLocal(date)}:00${zone}`;
}

/**
 * VAT-inclusive display text of a typed price, or '' when the input is not a usable amount or the
 * platform VAT rate (percent string from GET /super-admin/plans, `tax_rate`) is zero / unknown.
 */
export function vatInclusiveText(value, rate) {
    const text = moneyText(value).trim();
    if (text === '' || Number.isNaN(Number(text)) || !(toMilli(rate) > 0n)) return '';
    try {
        return dRoundTo(dAdd(text, dPercent(text, rate)), 2);
    } catch {
        return '';
    }
}

/** off | scheduled | active | ended — derived from the typed offer fields. */
export function promotionStatus(form, now = Date.now()) {
    const hasOffer = [form.compare_at_price_monthly, form.compare_at_price_yearly].some(
        (v) => moneyText(v).trim() !== ''
    );
    if (!hasOffer) return 'off';
    const starts = form.promo_starts_at ? Date.parse(form.promo_starts_at) : null;
    const ends = form.promo_ends_at ? Date.parse(form.promo_ends_at) : null;
    if (ends !== null && !Number.isNaN(ends) && ends <= now) return 'ended';
    if (starts !== null && !Number.isNaN(starts) && starts > now) return 'scheduled';
    return 'active';
}

export function clearPromotion(form) {
    form.compare_at_price_monthly = '';
    form.compare_at_price_yearly = '';
    form.promo_starts_at = '';
    form.promo_ends_at = '';
}

function addonRows(plan, catalog) {
    const byId = new Map((plan?.addons || []).map((row) => [row.addon_id, row]));
    return catalog.map((addon) => {
        const row = byId.get(addon.id);
        return {
            addon_id: addon.id,
            is_available: !!row?.is_available,
            included_quantity: Number(row?.included_quantity || 0),
            included_billing_cycle: row?.included_billing_cycle || null,
        };
    });
}

function featureMap(plan, registry) {
    const features = {};
    for (const feature of registry) {
        if (!feature.is_public) continue;
        features[feature.key] = plan ? !!plan.features?.[feature.key] : !!feature.is_core;
    }
    return features;
}

/** Editor form state from a plan (edit) or defaults (create, plan = null). */
export function buildPlanForm(plan, registry, catalog) {
    const p = plan || {};
    const form = {
        name: p.name || '',
        description: p.description || '',
        sort_order: p.sort_order ?? 0,
        trial_days: p.trial_days ?? 0,
        is_active: plan ? !!p.is_active : true,
        is_public: plan ? !!p.is_public : false,
        is_popular: !!p.is_popular,
        promo_starts_at: toDatetimeLocal(p.promo_starts_at),
        promo_ends_at: toDatetimeLocal(p.promo_ends_at),
        max_users: plan ? normalizeLimit(p.max_users) : 3,
        max_stores: plan ? normalizeLimit(p.max_stores) : 1,
        max_items: plan ? normalizeLimit(p.max_items) : null,
        max_invoices_per_month: plan ? normalizeLimit(p.max_invoices_per_month) : null,
        max_storage_mb: plan ? normalizeLimit(p.max_storage_mb) : null,
        max_warehouses: plan ? normalizeLimit(p.max_warehouses) : 1,
        max_vans: plan ? normalizeLimit(p.max_vans) : 0,
        features: featureMap(plan, registry),
        addons: addonRows(plan, catalog),
    };
    for (const field of MONEY_FIELDS) form[field] = moneyText(p[field]);
    return form;
}

/** Client-side limit validation + API payload. Returns { payload, errors }. */
export function buildPlanPayload(form) {
    const errors = {};
    const payload = {
        name: form.name,
        description: form.description?.trim() ? form.description : null,
        sort_order: Number(form.sort_order || 0),
        trial_days: Number(form.trial_days || 0),
        is_active: !!form.is_active,
        is_public: !!form.is_public,
        is_popular: !!form.is_popular,
        promo_starts_at: fromDatetimeLocal(form.promo_starts_at),
        promo_ends_at: fromDatetimeLocal(form.promo_ends_at),
        features: { ...form.features },
        addons: form.addons.map((row) => ({
            addon_id: row.addon_id,
            is_available: !!row.is_available,
            included_quantity: Number(row.included_quantity || 0),
            included_billing_cycle: row.included_quantity > 0 ? row.included_billing_cycle || null : null,
        })),
    };

    for (const field of MONEY_FIELDS) {
        const text = moneyText(form[field]).trim();
        payload[field] = text === '' ? null : text;
    }

    for (const { key, min } of EDITOR_LIMIT_FIELDS) {
        const error = validateLimit(form[key], min);
        if (error) errors[key] = error;
        else payload[key] = isUnlimited(form[key]) ? null : normalizeLimit(form[key]);
    }

    return { payload, errors };
}
