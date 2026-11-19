// SHOP-02: pure helpers of the stocktake counting screen (no Vue, no network) so they can be
// unit-tested with node:test. Quantities are decimal strings (helpers/decimal), never floats.
import { dAdd, dCmp, dSub } from './decimal.js';

const isCounted = (value) => value !== null && value !== undefined && value !== '';

/** The line whose item code matches a scanned barcode (case-insensitive, trimmed), or null. */
export function findLineByCode(lines, rawCode) {
    const code = String(rawCode ?? '')
        .trim()
        .toLowerCase();
    if (!code) return null;
    return (lines || []).find((line) => String(line.item_code ?? '').toLowerCase() === code) || null;
}

/** Counted quantity after one more scan of the item. */
export function nextScanQuantity(current) {
    return dAdd(isCounted(current) ? current : '0', '1');
}

/**
 * Difference of a line: the server's posted difference once the count is closed, otherwise
 * counted - snapshot; null when nothing is counted or the snapshot is hidden (blind count).
 */
export function lineDifference(line, counted, isOpen) {
    if (!isOpen && line.difference !== null && line.difference !== undefined) return line.difference;
    if (!isCounted(counted) || line.system_qty === null || line.system_qty === undefined) return null;
    return dSub(counted, line.system_qty);
}

/** Lines matching the search term and the filter (all | uncounted | counted | differences). */
export function filterLines(lines, { search = '', filter = 'all', countedOf, differenceOf }) {
    const term = String(search).trim().toLowerCase();
    return (lines || []).filter((line) => {
        if (term && !`${line.item_name ?? ''} ${line.item_code ?? ''}`.toLowerCase().includes(term)) return false;
        const counted = isCounted(countedOf(line));
        if (filter === 'uncounted') return !counted;
        if (filter === 'counted') return counted;
        if (filter === 'differences') {
            const diff = differenceOf(line);
            return diff !== null && dCmp(diff, '0') !== 0;
        }
        return true;
    });
}

export { isCounted };
