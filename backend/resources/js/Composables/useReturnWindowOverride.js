import api from '../Services/api';
import { approvalReasons, needsManagerApproval, requestManagerApproval } from './useManagerApproval';

/** SET-02: the server refused a return because the invoice is older than `returns_max_days`. */
export function isReturnWindowExpired(error) {
    return error?.response?.status === 422 && error.response.data?.error_code === 'returns.window_expired';
}

/**
 * POST /returns, asking for a manager's PIN when the invoice is past the return window.
 *
 *  - 422 `returns.window_expired`  -> manager-PIN dialog (the shared one), then the same request is re-sent
 *    with `override: { user_id, pin }`;
 *  - 422 `override.pin` (wrong PIN or not allowed) -> the dialog opens again with the server's reason;
 *  - 429 (too many tries / locked for the day) and every other error are thrown as they are: the caller
 *    shows `error.response.data.message`, and no further PIN prompt is offered.
 *
 * Users who hold `returns.override_window` are never refused by the server, so they never see the dialog.
 * The PIN only lives inside this call; it is not stored or logged.
 *
 * @returns {Promise<import('axios').AxiosResponse|null>} the response, or null when the manager cancelled
 */
export async function postReturnWithWindowOverride(payload) {
    let override = null;

    for (;;) {
        try {
            return await api.post('/returns', override ? { ...payload, override } : payload);
        } catch (error) {
            const reasons = isReturnWindowExpired(error)
                ? [error.response.data.message]
                : needsManagerApproval(error)
                  ? approvalReasons(error)
                  : null;
            if (reasons === null) throw error;

            override = await requestManagerApproval({ reasons });
            if (override === null) return null;
        }
    }
}
