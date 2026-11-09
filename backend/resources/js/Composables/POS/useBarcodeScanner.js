import api from '../../Services/api';
import { trans } from '../../helpers/trans';
import { notifyError } from '../../helpers/alert';

// A wedge scanner types a whole code in a few milliseconds; a human never does.
const MIN_SCAN_LENGTH = 4;
const MAX_MS_PER_CHAR = 40;

/**
 * POS-01: barcode scanner input handling for the POS search box.
 *
 * - Enter resolves the EXACT scanned text through GET /pos/lookup (never the in-flight
 *   text-search results, never a substring match).
 * - Scans are serialized through a promise queue so fast consecutive scans are neither lost
 *   nor reordered, and the input is cleared at once so the next scan starts empty.
 * - A scale label returns a weight quantity that is passed to addItem.
 * - Manual typing keeps working: with arrow navigation, or a non-burst query that misses,
 *   the highlighted dropdown row is added instead.
 *
 * @param {object} options
 * @param {import('vue').Ref<string>} options.query search box text
 * @param {import('vue').Ref<Array>} options.results text-search dropdown rows
 * @param {import('vue').Ref<number>} options.highlightedIndex dropdown highlight
 * @param {(item: object, qty: number, meta?: { scanned: boolean }) => void} options.addItem adds a line to the cart
 * @param {() => void} options.reset clears the search box, closes the dropdown, refocuses it
 * @param {() => void} [options.onMiss] audio/visual hook when a code is unknown
 */
export function useBarcodeScanner({ query, results, highlightedIndex, addItem, reset, onMiss }) {
    let queue = Promise.resolve();
    let firstInputAt = 0;
    let lastInputAt = 0;
    let navigated = false;

    const noteQueryChange = (value) => {
        const now = Date.now();
        if (!value) {
            firstInputAt = 0;
            navigated = false;
            return;
        }
        if (!firstInputAt) firstInputAt = now;
        lastInputAt = now;
    };

    const noteNavigation = () => {
        navigated = true;
    };

    const isBurst = (code) => {
        if (code.length < MIN_SCAN_LENGTH || !firstInputAt) return false;
        return (lastInputAt - firstInputAt) / (code.length - 1) < MAX_MS_PER_CHAR;
    };

    const reportMiss = (code) => {
        onMiss?.();
        notifyError(trans('pos.barcode_not_found', { code }));
    };

    const resolve = async (code, fallbackItem, multiplier, scanned) => {
        try {
            const res = await api.get('/pos/lookup', { params: { code } });
            const data = res.data?.data;
            if (!data?.item) return reportMiss(code);
            const qty = parseFloat(data.quantity);
            // A scale-label weight wins over the typed `n*` multiplier.
            addItem(data.item, qty > 0 ? qty : multiplier, { scanned });
        } catch (err) {
            if (err.response?.status === 404) {
                if (fallbackItem) return addItem(fallbackItem, multiplier, { scanned });
                return reportMiss(code);
            }
            // Network failure while typing manually: keep the cashier moving with the row they see.
            if (!err.response && fallbackItem) return addItem(fallbackItem, multiplier, { scanned });
            onMiss?.();
            notifyError(err.userMessage || trans('common.unexpected_error'));
        }
    };

    /** Enter pressed in the search box. `multiplier` is the typed `n*` quantity prefix (default 1). */
    const submit = (multiplier = 1) => {
        const code = query.value.trim();
        if (!code) return;

        const burst = isBurst(code);
        const highlighted = results.value[highlightedIndex.value] || results.value[0] || null;

        if (navigated && !burst && highlighted) {
            addItem(highlighted, multiplier, { scanned: false });
            reset();
            return;
        }

        const fallbackItem = burst ? null : highlighted;
        reset();
        queue = queue.then(() => resolve(code, fallbackItem, multiplier, burst));
    };

    return { submit, noteQueryChange, noteNavigation };
}
