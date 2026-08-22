import { ref } from 'vue';

/**
 * useSearchFilter - Reusable Composable for managing search queries and filters in Pure Vue 3 SPA.
 * 
 * @param {Function} fetchCallback - Async function to call when filters change
 * @param {Object} initialFilters - Initial filter values
 * @param {Object} options - Configuration options (debounceMs)
 */
export function useSearchFilter(fetchCallback, initialFilters = {}, options = {}) {
    const { debounceMs = 300 } = options;

    const filters = ref({ ...initialFilters });
    const isFiltering = ref(false);
    let debounceTimer = null;

    const applyFilters = async () => {
        isFiltering.value = true;
        try {
            if (typeof fetchCallback === 'function') {
                await fetchCallback(filters.value);
            }
        } finally {
            isFiltering.value = false;
        }
    };

    const debouncedApply = () => {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            applyFilters();
        }, debounceMs);
    };

    const resetFilters = (defaultValues = {}) => {
        filters.value = { ...defaultValues };
        applyFilters();
    };

    return {
        filters,
        isFiltering,
        applyFilters,
        debouncedApply,
        resetFilters
    };
}
