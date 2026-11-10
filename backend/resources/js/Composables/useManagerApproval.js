import Swal from 'sweetalert2';
import { trans } from '../helpers/trans';
import { toWesternDigits } from '../helpers/digits';

/**
 * Manager approval helper for operations requiring a manager/admin PIN override (e.g. BE-03 invoice cancel).
 * The server returns 422 with an `override` error when approval is needed, or `override.pin` when the PIN was wrong.
 */

/** True when a server error asks for (or rejects) a manager approval. */
export function needsManagerApproval(error) {
    if (error?.response?.status !== 422) return false;
    const errors = error.response.data?.errors ?? {};
    return 'override' in errors || 'override.pin' in errors || 'override.user_id' in errors;
}

/** The server messages explaining why approval is required or rejected. */
export function approvalReasons(error) {
    const errors = error?.response?.data?.errors ?? {};
    return Object.entries(errors)
        .filter(([key]) => key === 'override' || key === 'override.pin' || key === 'override.user_id')
        .flatMap(([, messages]) => (Array.isArray(messages) ? messages : [messages]));
}

const escapeHtml = (text) =>
    String(text).replace(
        /[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
    );

/**
 * Ask a manager/admin for their user ID and PIN.
 * Resolves { user_id, pin }, or null when cancelled.
 *
 * @param {Object} options
 * @param {string[]} [options.reasons=[]] Translated server messages to show above the inputs
 * @param {number|string|null} [options.defaultUserId=null] Pre-filled user ID (e.g. current user if admin)
 */
export async function requestManagerApproval({ reasons = [], defaultUserId = null } = {}) {
    const userVal = defaultUserId ? String(defaultUserId) : '';

    const result = await Swal.fire({
        title: trans('pos.override_title'),
        html: `
            <div style="text-align:start">
                ${reasons.map((r) => `<p style="margin:0 0 8px;color:#f43f5e;font-weight:600">${escapeHtml(r)}</p>`).join('')}
                <label for="approval-manager-user" style="display:block;font-weight:700;margin:8px 0 4px">${escapeHtml(trans('pos.override_manager_id'))}</label>
                <input id="approval-manager-user" class="swal2-input" value="${escapeHtml(userVal)}" style="margin:0;width:100%" inputmode="numeric" autocomplete="off" dir="ltr">
                <label for="approval-manager-pin" style="display:block;font-weight:700;margin:8px 0 4px">${escapeHtml(trans('pos.override_pin'))}</label>
                <input id="approval-manager-pin" class="swal2-input" style="margin:0;width:100%" type="password" inputmode="numeric" maxlength="6" autocomplete="off" dir="ltr">
            </div>`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: trans('pos.override_confirm'),
        cancelButtonText: trans('common.cancel'),
        didOpen: () => {
            const userInput = document.getElementById('approval-manager-user');
            const pinInput = document.getElementById('approval-manager-pin');
            if (userVal && pinInput) {
                pinInput.focus();
            } else if (userInput) {
                userInput.focus();
            }
        },
        preConfirm: () => {
            const userId = toWesternDigits(document.getElementById('approval-manager-user')?.value ?? '').trim();
            const pin = toWesternDigits(document.getElementById('approval-manager-pin')?.value ?? '').trim();
            if (!/^\d+$/.test(userId) || !/^\d{4,6}$/.test(pin)) {
                Swal.showValidationMessage(trans('pos.override_invalid_input'));
                return false;
            }
            return { user_id: Number(userId), pin };
        },
    });

    return result.isConfirmed ? result.value : null;
}

export function useManagerApproval() {
    return {
        needsManagerApproval,
        approvalReasons,
        requestManagerApproval,
    };
}
