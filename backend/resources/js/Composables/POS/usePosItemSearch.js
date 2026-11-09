import { ref, computed, watch } from 'vue';
import { useRemoteSearch } from './useRemoteSearch';
import { useBarcodeScanner } from './useBarcodeScanner';

/**
 * Header search box: local + remote item matches, dropdown navigation, and the POS-01 scan queue.
 */
export function usePosItemSearch({ items, addToCart, focusSearch, onMiss }) {
    const searchQuery = ref('');
    const isSearchFocused = ref(false);
    const highlightedIndex = ref(0);

    const {
        results: remoteResults,
        isSearching: isSearchingRemote,
        search,
    } = useRemoteSearch('/items', (res) => res.data?.data || []);

    const dropdownResults = computed(() => {
        const q = searchQuery.value.trim().toLowerCase();
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
        submit: selectHighlightedOrFirstItem,
        noteQueryChange,
        noteNavigation,
    } = useBarcodeScanner({
        query: searchQuery,
        results: dropdownResults,
        highlightedIndex,
        addItem: addToCart,
        reset: resetSearchBox,
        onMiss,
    });

    watch(searchQuery, (newVal) => {
        noteQueryChange(newVal);
        highlightedIndex.value = 0;
        search(newVal);
    });

    const addItemFromDropdown = (item) => {
        addToCart(item);
        resetSearchBox();
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
    };
}
