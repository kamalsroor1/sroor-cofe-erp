import Swal from 'sweetalert2';
import { trans } from './trans';
import { toWesternDigits } from './digits';
import { escapeHtml } from './escapeHtml';

/**
 * The single manager-approval prompt shared by every flow that needs a manager PIN: the POS (below-floor
 * price, over-cap discount) and non-POS screens (cancelling an invoice that holds cash payments).
 * The server answers 422 with an `override` / `override.pin` error; the caller re-sends the request with
 * `override: { user_id, pin }`. The PIN is only ever checked server-side and is never stored here.
 */

/** True when a failed request asks for (or rejected) a manager approval. */
export function isManagerApprovalError(error) {
    if (error?.response?.status !== 422) return false;
    const errors = error.response.data?.errors ?? {};
    return 'override' in errors || 'override.pin' in errors || 'override.user_id' in errors;
}

/**
 * SET-02: the approver's PIN is locked until tomorrow (or until the PIN is changed): 429 `pos.override_locked_today`.
 * Unlike the short `pos.override_throttled` 429 there is nothing to retry with that PIN: show the server message
 * and do not open the dialog again.
 */
export function isManagerPinLocked(error) {
    return error?.response?.status === 429 && error.response.data?.error_code === 'pos.override_locked_today';
}

/**
 * Ask a manager for their user number and PIN. Resolves `{ user_id, pin }`, or null when cancelled.
 *
 * @param {Object} options
 * @param {string[]} [options.reasons=[]] translated server messages shown above the inputs
 * @param {number|string|null} [options.defaultUserId=null] pre-filled manager number
 * @param {string} [options.idPrefix='manager-approval'] prefix of the input ids (`<prefix>-user`, `<prefix>-pin`)
 * @param {string} [options.reasonClass=''] extra classes of each reason line (theme-aware, e.g. Tailwind colours)
 */
export async function askManagerPin({
    reasons = [],
    defaultUserId = null,
    idPrefix = 'manager-approval',
    reasonClass = '',
} = {}) {
    const userId = defaultUserId ? String(defaultUserId) : '';
    const userInputId = `${idPrefix}-user`;
    const pinInputId = `${idPrefix}-pin`;

    const result = await Swal.fire({
        title: trans('pos.override_title'),
        html: `
            <div style="text-align:start">
                ${reasons.map((r) => `<p class="${escapeHtml(reasonClass)}" style="margin:0 0 8px">${escapeHtml(r)}</p>`).join('')}
                <label for="${userInputId}" style="display:block;font-weight:700;margin:8px 0 4px">${escapeHtml(trans('pos.override_manager_id'))}</label>
                <input id="${userInputId}" class="swal2-input" value="${escapeHtml(userId)}" style="margin:0;width:100%" inputmode="numeric" autocomplete="off" dir="ltr">
                <label for="${pinInputId}" style="display:block;font-weight:700;margin:8px 0 4px">${escapeHtml(trans('pos.override_pin'))}</label>
                <input id="${pinInputId}" class="swal2-input" style="margin:0;width:100%" type="password" inputmode="numeric" maxlength="6" autocomplete="off" dir="ltr">
            </div>`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: trans('pos.override_confirm'),
        cancelButtonText: trans('common.cancel'),
        didOpen: () => {
            const focusId = userId ? pinInputId : userInputId;
            document.getElementById(focusId)?.focus();
        },
        preConfirm: () => {
            const user = toWesternDigits(document.getElementById(userInputId)?.value ?? '').trim();
            const pin = toWesternDigits(document.getElementById(pinInputId)?.value ?? '').trim();
            if (!/^\d+$/.test(user) || !/^\d{4,6}$/.test(pin)) {
                Swal.showValidationMessage(trans('pos.override_invalid_input'));
                return false;
            }
            return { user_id: Number(user), pin };
        },
    });

    return result.isConfirmed ? result.value : null;
}
