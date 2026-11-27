import { toWesternDigits } from '../../helpers/digits';
import { dRoundTo, isPositive, normalize } from '../../helpers/decimal';
import { focusLineField, focusPosSearch } from '../../helpers/posFocus';

/**
 * Handlers of the cart quantity input, shared by every cart line layout.
 * `getLine(index)` returns the cart line the input belongs to; `emit` is the owner's emit.
 */
export function useCartQtyField({ emit, getLine }) {
    const sanitizeQty = (raw, isWeighted) =>
        toWesternDigits(raw ?? '')
            .replace(isWeighted ? /[^0-9.]/g : /[^0-9]/g, '')
            .replace(/^(\d*\.\d*)\./, '$1');

    // Arabic-Indic digits are accepted and converted before they reach the cart.
    const onNumberInput = (event, index, eventName) => {
        // Keep digits and a single decimal point only (whole numbers for non-weighted quantities).
        const isWeighted = eventName !== 'update-qty' || !!getLine(index)?.is_weighted;
        const normalized = sanitizeQty(event.target.value, isWeighted);
        if (normalized !== event.target.value) event.target.value = normalized;
        emit(eventName, { index, value: normalized });
    };

    const commitQty = (event, index) => {
        const line = getLine(index);
        const clean = sanitizeQty(event.target.value, !!line?.is_weighted);
        if (line?.is_weighted) {
            const committed = normalize(clean);
            if (isPositive(committed)) emit('update-qty', { index, value: committed });
            event.target.value = normalize(line.quantity);
            return;
        }
        const whole = dRoundTo(clean, 0);
        if (isPositive(whole)) emit('update-qty', { index, value: whole });
        event.target.value = line?.quantity ?? '';
    };

    // Leaving a field with an invalid or zero value snaps it back to the cart value; valid typed values commit.
    const onFieldBlur = (event, index, key) => {
        const line = getLine(index);
        if (key === 'quantity') {
            commitQty(event, index);
            return;
        }
        const parsed = parseFloat(event.target.value);
        if (isNaN(parsed) || parsed <= -1) event.target.value = line?.[key] ?? '';
    };

    // Enter commits/normalizes the quantity before moving focus to the line's price field.
    const onFieldEnter = (event, index, key) => {
        const line = getLine(index);
        if (key === 'quantity') commitQty(event, index);
        if (line?.id !== undefined) focusLineField(line.id, 'price');
    };

    // Select the whole value on focus so typing replaces it, and remember it for Esc.
    const onFieldFocus = (event) => {
        event.target.dataset.original = event.target.value;
        event.target.select();
    };

    // Esc reverts the field to its value before the edit and returns to the search box.
    const revertField = (event, index, eventName) => {
        const original = event.target.dataset.original;
        if (original !== undefined) {
            event.target.value = original;
            emit(eventName, { index, value: original });
        }
        focusPosSearch();
    };

    return { onNumberInput, onFieldBlur, onFieldEnter, onFieldFocus, revertField };
}
