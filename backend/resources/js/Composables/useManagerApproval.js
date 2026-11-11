import { isManagerApprovalError, askManagerPin } from '../helpers/managerApprovalDialog';

/**
 * Manager approval for non-POS screens that need a manager/admin PIN (BE-03: cancelling an invoice that
 * holds cash payments). The prompt itself is shared with the POS through `helpers/managerApprovalDialog`.
 * The server returns 422 with an `override` error when approval is needed, or `override.pin` when the PIN was wrong.
 */

/** True when a server error asks for (or rejects) a manager approval. */
export const needsManagerApproval = isManagerApprovalError;

/** The server messages explaining why approval is required or rejected. */
export function approvalReasons(error) {
    const errors = error?.response?.data?.errors ?? {};
    return Object.entries(errors)
        .filter(([key]) => key === 'override' || key === 'override.pin' || key === 'override.user_id')
        .flatMap(([, messages]) => (Array.isArray(messages) ? messages : [messages]));
}

/**
 * Ask a manager/admin for their user ID and PIN. Resolves { user_id, pin }, or null when cancelled.
 *
 * @param {Object} options
 * @param {string[]} [options.reasons=[]] Translated server messages to show above the inputs
 * @param {number|string|null} [options.defaultUserId=null] Pre-filled user ID (e.g. current user if admin)
 */
export function requestManagerApproval({ reasons = [], defaultUserId = null } = {}) {
    return askManagerPin({
        reasons,
        defaultUserId,
        idPrefix: 'approval-manager',
        reasonClass: 'font-semibold text-rose-600 dark:text-rose-400',
    });
}

export function useManagerApproval() {
    return {
        needsManagerApproval,
        approvalReasons,
        requestManagerApproval,
    };
}
