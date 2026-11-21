/**
 * SEC-21: manager approval PIN rule, shared by the user form and the profile card.
 * Mirrors the server (UpdateUserPosPinRequest / UpdateProfilePosPinRequest: regex /^\d{6}$/).
 */
export const POS_PIN_LENGTH = 6;
export const POS_PIN_PATTERN = new RegExp(`^\\d{${POS_PIN_LENGTH}}$`);

/** Attributes for the PIN inputs, so the browser constraint matches the server rule. */
export const POS_PIN_INPUT_ATTRS = Object.freeze({
    inputmode: 'numeric',
    pattern: `\\d{${POS_PIN_LENGTH}}`,
    minlength: String(POS_PIN_LENGTH),
    maxlength: String(POS_PIN_LENGTH),
    placeholder: '•'.repeat(POS_PIN_LENGTH),
});

/**
 * Returns null when the pair is valid, else the error kind: 'format' or 'mismatch'.
 * Values are trimmed first; the caller sends the trimmed pin.
 */
export function posPinError(pin, confirmation) {
    const value = String(pin ?? '').trim();
    if (!POS_PIN_PATTERN.test(value)) return 'format';
    if (value !== String(confirmation ?? '').trim()) return 'mismatch';
    return null;
}
