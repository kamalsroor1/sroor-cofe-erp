// STOR-1 recovery: the SPA keeps the active branch in localStorage (`current_store_id`) and sends
// it as `X-Store-Id` on every request. When the user loses access to that branch, the API answers
// 403 with `error_code: 'store_access_denied'` on every data route. Without recovery the UI is
// stuck (the interceptor only cleared the session on 401).
//
// storeAccessRetryConfig() forgets the stored branch and returns a config to retry the request ONCE
// without the header; the API then falls back to the user's own default store. A second
// store_access_denied (e.g. a body store_id the user may not use) is returned as a normal error.

export const STORE_ACCESS_DENIED = 'store_access_denied';

/** localStorage keys holding the active branch (the id sent as X-Store-Id, and its cached object). */
export const STORED_STORE_KEYS = ['current_store_id', 'auth_store'];

const RETRY_FLAG = '_storeAccessRetried';

export function isStoreAccessDenied(error) {
    return error?.response?.status === 403 && error?.response?.data?.error_code === STORE_ACCESS_DENIED;
}

function plainHeaders(headers) {
    if (!headers) return {};
    if (typeof headers.toJSON === 'function') return { ...headers.toJSON() };
    return { ...headers };
}

/**
 * @param {object} error axios error
 * @param {{ removeItem: (key: string) => void }} storage localStorage-like
 * @returns {object|null} the config to retry with, or null when no retry applies
 */
export function storeAccessRetryConfig(error, storage) {
    const config = error?.config;
    if (!isStoreAccessDenied(error) || !config || config[RETRY_FLAG]) {
        return null;
    }

    STORED_STORE_KEYS.forEach((key) => storage.removeItem(key));

    const headers = plainHeaders(config.headers);
    Object.keys(headers)
        .filter((name) => name.toLowerCase() === 'x-store-id')
        .forEach((name) => delete headers[name]);

    return { ...config, headers, [RETRY_FLAG]: true };
}
