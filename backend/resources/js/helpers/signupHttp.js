/** Seconds from a Retry-After header (numeric form only), or 0 when absent / not numeric. */
export const retryAfterSeconds = (error) => {
    const raw = error?.response?.headers?.['retry-after'];
    const seconds = Number.parseInt(raw, 10);
    return Number.isFinite(seconds) && seconds > 0 ? seconds : 0;
};

/** First message of a Laravel 422 `errors` bag entry. */
export const firstError = (errors, field) => {
    const value = errors?.[field];
    return Array.isArray(value) ? value[0] || '' : value || '';
};
