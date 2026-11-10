import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import { toWesternDigits } from '../../helpers/digits';

/**
 * Manager approval (BE-08 / POS-07). The server answers 422 with an `override` error when a sale needs a
 * manager (price below the floor, discount above the store's cap), or `override.pin` when the PIN was wrong.
 * The cashier then hands the screen to the manager, who types their user number and PIN; the sale is
 * re-sent with `override: { user_id, pin }`. The PIN is checked server-side only and is never stored here.
 */

/** True when a failed checkout asks for (or rejected) a manager approval. */
export function needsManagerOverride(error) {
    if (error?.response?.status !== 422) return false;
    const errors = error.response.data?.errors ?? {};
    return 'override' in errors || 'override.pin' in errors || 'override.user_id' in errors;
}

/** The server messages that explain why an approval is needed (translated by the server), joined for display. */
export function overrideReasons(error) {
    const errors = error?.response?.data?.errors ?? {};
    const lineKey = /^items\.\d+\.(unit_price|discount|discount_amount|discount_percent)$/;
    return Object.entries(errors)
        .filter(([key]) => key === 'discount' || key === 'override' || key === 'override.pin' || lineKey.test(key))
        .flatMap(([, messages]) => messages);
}

const escapeHtml = (text) =>
    String(text).replace(
        /[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
    );

/**
 * Ask a manager for their number and PIN. Resolves `{ user_id, pin }`, or null when cancelled.
 * @param {string[]} reasons translated server messages to show above the inputs
 */
export async function requestManagerOverride(reasons = []) {
    const result = await Swal.fire({
        title: trans('pos.override_title'),
        html: `
            <div style="text-align:start">
                ${reasons.map((r) => `<p style="margin:0 0 8px">${escapeHtml(r)}</p>`).join('')}
                <label for="pos-override-user" style="display:block;font-weight:700;margin:8px 0 4px">${escapeHtml(trans('pos.override_manager_id'))}</label>
                <input id="pos-override-user" class="swal2-input" style="margin:0;width:100%" inputmode="numeric" autocomplete="off" dir="ltr">
                <label for="pos-override-pin" style="display:block;font-weight:700;margin:8px 0 4px">${escapeHtml(trans('pos.override_pin'))}</label>
                <input id="pos-override-pin" class="swal2-input" style="margin:0;width:100%" type="password" inputmode="numeric" maxlength="6" autocomplete="off" dir="ltr">
            </div>`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: trans('pos.override_confirm'),
        cancelButtonText: trans('common.cancel'),
        didOpen: () => document.getElementById('pos-override-user')?.focus(),
        preConfirm: () => {
            const userId = toWesternDigits(document.getElementById('pos-override-user')?.value ?? '').trim();
            const pin = toWesternDigits(document.getElementById('pos-override-pin')?.value ?? '').trim();
            if (!/^\d+$/.test(userId) || !/^\d{4,6}$/.test(pin)) {
                Swal.showValidationMessage(trans('pos.override_invalid_input'));
                return false;
            }
            return { user_id: Number(userId), pin };
        },
    });

    return result.isConfirmed ? result.value : null;
}
