import { isManagerApprovalError, askManagerPin } from '../../helpers/managerApprovalDialog';

/**
 * Manager approval (BE-08 / POS-07). The server answers 422 with an `override` error when a sale needs a
 * manager (price below the floor, discount above the store's cap), or `override.pin` when the PIN was wrong.
 * The cashier then hands the screen to the manager, who types their user number and PIN; the sale is
 * re-sent with `override: { user_id, pin }`. The PIN is checked server-side only and is never stored here.
 */

/** True when a failed checkout asks for (or rejected) a manager approval. */
export const needsManagerOverride = isManagerApprovalError;

/** The server messages that explain why an approval is needed (translated by the server), joined for display. */
export function overrideReasons(error) {
    const errors = error?.response?.data?.errors ?? {};
    const lineKey = /^items\.\d+\.(unit_price|discount|discount_amount|discount_percent)$/;
    return Object.entries(errors)
        .filter(([key]) => key === 'discount' || key === 'override' || key === 'override.pin' || lineKey.test(key))
        .flatMap(([, messages]) => messages);
}

/**
 * Ask a manager for their number and PIN. Resolves `{ user_id, pin }`, or null when cancelled.
 * @param {string[]} reasons translated server messages to show above the inputs
 */
export function requestManagerOverride(reasons = []) {
    return askManagerPin({ reasons, idPrefix: 'pos-override' });
}
