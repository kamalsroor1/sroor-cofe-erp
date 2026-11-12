import { toWesternDigits } from '../../helpers/digits';
import { normalize } from '../../helpers/decimal';
import { focusPosSearch } from '../../helpers/posFocus';

/**
 * Handlers of the cart quantity input, shared by every cart line layout.
 * `getLine(index)` returns the cart line the input belongs to; `emit` is the owner's emit.
 */
export function useCartQtyField({ emit, getLine }) {
    // Arabic-Indic digits are accepted and converted before they reach the cart.
    const onNumberInput = (event, index, eventName) => {
        // Keep digits and a single decimal point only (whole numbers for non-weighted quantities).
        const wholeOnly = eventName === 'update-qty' && !getLine(index)?.is_weighted;
        const normalized = toWesternDigits(event.target.value)
            .replace(wholeOnly ? /[^0-9]/g : /[^0-9.]/g, '')
            .replace(/^(\d*\.\d*)\./, '$1');
        if (normalized !== event.target.value) event.target.value = normalized;
        emit(eventName, { index, value: normalized });
    };

    // Leaving a field with an invalid or zero value snaps it back to the cart value.
    const onFieldBlur = (event, index, key) => {
        const line = getLine(index);
        if (key === 'quantity') {
            // The field always settles on the cart quantity (weighted: exactly 3 decimals, others: whole).
            if (line?.is_weighted) emit('update-qty', { index, value: normalize(event.target.value) });
            event.target.value = line?.is_weighted ? normalize(line.quantity) : (line?.quantity ?? '');
            return;
        }
        const parsed = parseFloat(event.target.value);
        if (isNaN(parsed) || parsed <= -1) event.target.value = line?.[key] ?? '';
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

    return { onNumberInput, onFieldBlur, onFieldFocus, revertField };
}
