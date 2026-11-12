// ROLE-01: branch assignment of the users form. Every non-admin user needs at least one branch and the
// default branch is one of the selected ones. Pure helpers (tests/js/user-stores.test.js).

export const ADMIN_ROLE = 'admin';
export const STORE_REQUIRED_KEY = 'users.store_required';

export const isStoreRequired = (role) => role !== ADMIN_ROLE;

/** Translation key of the client-side error, or '' when the branch selection is acceptable. */
export function userStoresErrorKey({ role, store_ids } = {}) {
    return isStoreRequired(role) && (!Array.isArray(store_ids) || store_ids.length === 0) ? STORE_REQUIRED_KEY : '';
}

/** Add or remove one branch id from the selection (order of selection is kept). */
export function toggleStoreId(selected = [], id) {
    return selected.includes(id) ? selected.filter((value) => value !== id) : [...selected, id];
}

/** The default branch must be one of the selected ones: keep it, else fall back to the first selected, else null. */
export function normalizeDefaultStore(selected = [], defaultId = null) {
    if (selected.length === 0) return null;
    return selected.includes(defaultId) ? defaultId : selected[0];
}

/** Ids of an existing user, as numbers, without duplicates. */
export function userStoreIds(user) {
    const ids = (user?.store_ids || []).map(Number).filter((id) => Number.isFinite(id));
    return [...new Set(ids)];
}

/** First server message per branch field (`store_ids`, `store_ids.0`, `default_store_id`), keyed like the form. */
export function userStoreFieldErrors(error) {
    const errors = error?.response?.data?.errors || {};
    const first = (value) => (Array.isArray(value) ? value[0] : value) || '';
    const itemKey = Object.keys(errors).find((key) => key.startsWith('store_ids.'));
    return {
        store_ids: first(errors.store_ids) || (itemKey ? first(errors[itemKey]) : ''),
        default_store_id: first(errors.default_store_id),
    };
}
