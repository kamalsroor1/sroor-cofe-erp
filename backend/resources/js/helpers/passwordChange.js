// SELL-01: forced password change. Pure helpers shared by the axios layer, the router guard, the
// auth store and the change-password screen (unit tested in tests/js/password-change.test.js).

export const PASSWORD_CHANGE_REQUIRED = 'auth.password_change_required';
export const PASSWORD_CHANGE_ROUTE = 'auth.change_password';
export const PASSWORD_MIN_LENGTH = 8;

/** True for the 403 every flagged user gets on any API call except the change itself. */
export function isPasswordChangeRequired(error) {
    return error?.response?.status === 403 && error?.response?.data?.error_code === PASSWORD_CHANGE_REQUIRED;
}

/** The flag as the login / me payloads carry it (top level and/or inside `user`). */
export function mustChangeFromPayload(payload) {
    return payload?.must_change_password === true || payload?.user?.must_change_password === true;
}

/** Only same-origin paths may be used as a post-change destination (no open redirect). */
export function safeInternalRedirect(target) {
    if (typeof target !== 'string' || !target.startsWith('/') || target.startsWith('//')) return null;
    if (target.includes('\\')) return null;
    const path = target.split(/[?#]/)[0];
    if (path === '/login' || path === '/change-password') return null;
    return target;
}

/** Field errors of a failed change request, keyed like the form (first message per field). */
export function passwordChangeFieldErrors(error) {
    const errors = error?.response?.data?.errors || {};
    const first = (value) => (Array.isArray(value) ? value[0] : value) || '';
    return {
        current_password: first(errors.current_password),
        password: first(errors.password),
        password_confirmation: first(errors.password_confirmation),
    };
}

export const isThrottled = (error) => error?.response?.status === 429;
