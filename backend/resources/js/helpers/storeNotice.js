// ROLE-01: a user assigned to no branch. The login / me payloads carry `store_notice`, and every
// store-scoped call answers 403 with this error code. Pure helpers (tests/js/store-notice.test.js).

export const NO_STORE_ASSIGNED = 'no_store_assigned';

const toNotice = (code, message) =>
    code === NO_STORE_ASSIGNED && typeof message === 'string' && message !== ''
        ? { error_code: NO_STORE_ASSIGNED, message }
        : null;

/** The notice of a login / me payload, or null when the user has a branch (or the shape is unknown). */
export function storeNoticeFromPayload(payload) {
    return toNotice(payload?.store_notice?.error_code, payload?.store_notice?.message);
}

/** True for the 403 every store-scoped request of a branch-less user gets. */
export function isNoStoreAssigned(error) {
    return error?.response?.status === 403 && error?.response?.data?.error_code === NO_STORE_ASSIGNED;
}

/** The notice carried by such a 403, or null for any other error. */
export function storeNoticeFromError(error) {
    return isNoStoreAssigned(error) ? toNotice(NO_STORE_ASSIGNED, error.response.data.message) : null;
}
