import { ref, computed, watch } from 'vue';
import { useBarcodeScanner } from './useBarcodeScanner';
import { toWesternDigits } from '../../helpers/digits';

const MAX_MULTIPLIER = 9999;

// "3*" or "2.5*code": quantity multiplier prefix for the next item.
const MULTIPLIER_PATTERN = /^\s*(\d+(?:[.,،]\d+)?)\s*[*×٭]\s*(.*)$/s;

/** Splits the search text into its `n*` quantity prefix and the item term. */
export function parseMultiplier(raw) {
    const match = MULTIPLIER_PATTERN.exec(toWesternDigits(raw));
    const qty = match ? Math.min(parseFloat(match[1].replace(/[,،]/, '.')), MAX_MULTIPLIER) : 0;
    if (!match || !(qty > 0)) return { multiplier: 1, hasMultiplier: false, term: String(raw ?? '') };
    return { multiplier: qty, hasMultiplier: true, term: match[2] };
}

/**
 * Header search box: local + remote item matches, dropdown navigation, and the POS-01 scan queue.
 */
export function usePosItemSearch({ items, searchResults, isSearching, addToCart, focusSearch, onMiss }) {
    const searchQuery = ref('');
    const isSearchFocused = ref(false);
    const highlightedIndex = ref(0);

    // The server matches come from the catalog grid's first page for the same term (one request, one source).
    const remoteResults = searchResults;
    const isSearchingRemote = isSearching;

    // Decimal quantities only make sense for weighted items.
    const quantityFor = (item, qty) =>
        item?.is_weighted || Number.isInteger(qty) ? qty : Math.max(1, Math.round(qty));
    const addWithMultiplier = (item, qty, meta) => addToCart(item, quantityFor(item, qty), meta);

    const parsedQuery = computed(() => parseMultiplier(searchQuery.value));
    const multiplier = computed(() => parsedQuery.value.multiplier);
    const searchTerm = computed(() => parsedQuery.value.term);

    const dropdownResults = computed(() => {
        const q = searchTerm.value.trim().toLowerCase();
        if (!q) return [];

        const localMatches = items.value.filter(
            (i) => (i.name && i.name.toLowerCase().includes(q)) || (i.code && i.code.toLowerCase().includes(q))
        );

        // Remote matches win over local ones, deduplicated by id.
        const merged = new Map();
        localMatches.forEach((item) => merged.set(item.id, item));
        remoteResults.value.forEach((item) => merged.set(item.id, item));

        return Array.from(merged.values()).slice(0, 15);
    });

    const resetSearchBox = () => {
        searchQuery.value = '';
        isSearchFocused.value = false;
        highlightedIndex.value = 0;
        focusSearch();
    };

    // POS-01: Enter resolves the exact scanned code through /pos/lookup (serialized, never substring).
    const {
        submit: submitScan,
        noteQueryChange,
        noteNavigation,
    } = useBarcodeScanner({
        query: searchTerm,
        results: dropdownResults,
        highlightedIndex,
        addItem: addWithMultiplier,
        reset: resetSearchBox,
        onMiss,
    });

    // "3*" alone waits for the item; with an item it adds that many.
    const selectHighlightedOrFirstItem = () => {
        if (!searchTerm.value.trim()) return;
        submitScan(multiplier.value, parsedQuery.value.hasMultiplier);
    };

    watch(searchQuery, (value) => {
        if (value) isSearchFocused.value = true;
    });

    watch(searchTerm, (newVal) => {
        noteQueryChange(newVal);
        highlightedIndex.value = 0;
    });

    const addItemFromDropdown = (item) => {
        addWithMultiplier(item, multiplier.value, { scanned: false, weighed: parsedQuery.value.hasMultiplier });
        resetSearchBox();
    };

    /** Esc in the search box: close the dropdown first, then clear the text. */
    const escapeSearch = () => {
        if (isSearchFocused.value && dropdownResults.value.length > 0) {
            isSearchFocused.value = false;
        } else if (searchQuery.value) {
            searchQuery.value = '';
        }
    };

    const navigateDropdown = (direction) => {
        noteNavigation();
        const count = dropdownResults.value.length;
        if (count === 0) return;
        if (direction === 'down') {
            highlightedIndex.value = (highlightedIndex.value + 1) % count;
        } else if (direction === 'up') {
            highlightedIndex.value = (highlightedIndex.value - 1 + count) % count;
        }
    };

    return {
        searchQuery,
        isSearchFocused,
        highlightedIndex,
        dropdownResults,
        isSearchingRemote,
        selectHighlightedOrFirstItem,
        addItemFromDropdown,
        navigateDropdown,
        escapeSearch,
        multiplier,
        searchTerm,
    };
}
