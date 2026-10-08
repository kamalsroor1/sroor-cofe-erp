// Plan limits contract (PlanResource / UpdatePlanRequest): every limit is int|null and
// null means UNLIMITED. Never coerce null to 0: 0 is a real limit ("none allowed").

export const PLAN_LIMIT_FIELDS = [
    { key: 'max_users', min: 1, formLabel: 'super.users_limit_label', cardLabel: 'super.max_users_label' },
    { key: 'max_stores', min: 1, formLabel: 'super.stores_limit_label', cardLabel: 'super.max_stores_label' },
    { key: 'max_items', min: 1, formLabel: 'super.items_limit_label', cardLabel: 'super.max_items_label' },
    {
        key: 'max_invoices_per_month',
        min: 1,
        formLabel: 'super.invoices_limit_label',
        cardLabel: 'super.monthly_invoices_label',
    },
    {
        key: 'max_warehouses',
        min: 0,
        formLabel: 'super.warehouses_limit_label',
        cardLabel: 'super.max_warehouses_label',
    },
    { key: 'max_vans', min: 0, formLabel: 'super.vans_limit_label', cardLabel: 'super.max_vans_label' },
    {
        key: 'max_storage_mb',
        min: 1,
        formLabel: 'super.storage_limit_label',
        cardLabel: 'super.max_storage_mb_label',
    },
];

export const PLAN_LIMIT_MAX = 1000000000;

export function isUnlimited(value) {
    return value === null || value === undefined || (typeof value === 'string' && value.trim() === '');
}

/** Empty / null -> null (unlimited); anything else -> Number (may be NaN, caught by validation). */
export function normalizeLimit(value) {
    if (isUnlimited(value)) return null;
    return Number(value);
}

/** Returns null when valid, otherwise { min, max } for the error message. */
export function validateLimit(value, min = 1) {
    const normalized = normalizeLimit(value);
    if (normalized === null) return null;
    if (!Number.isInteger(normalized) || normalized < min || normalized > PLAN_LIMIT_MAX) {
        return { min, max: PLAN_LIMIT_MAX };
    }
    return null;
}

/** Only the limit fields the API actually returned, so an omitted field is left untouched server-side. */
export function pickPlanLimits(plan = {}) {
    const limits = {};
    for (const { key } of PLAN_LIMIT_FIELDS) {
        if (plan && Object.prototype.hasOwnProperty.call(plan, key)) {
            limits[key] = normalizeLimit(plan[key]);
        }
    }
    return limits;
}

/** Limit field definitions present in the given plan/form. */
export function presentLimitFields(source = {}) {
    return PLAN_LIMIT_FIELDS.filter(({ key }) => source && Object.prototype.hasOwnProperty.call(source, key));
}

/** Normalizes every present limit and collects per-field validation errors. */
export function preparePlanLimits(form = {}) {
    const values = {};
    const errors = {};
    for (const { key, min } of presentLimitFields(form)) {
        const error = validateLimit(form[key], min);
        if (error) {
            errors[key] = error;
        } else {
            values[key] = normalizeLimit(form[key]);
        }
    }
    return { values, errors };
}
