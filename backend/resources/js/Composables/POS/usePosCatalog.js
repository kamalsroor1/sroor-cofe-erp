import { ref, computed } from 'vue';
import api from '../../Services/api';

const PRELOAD_ITEMS = 300;

/**
 * POS catalog: items, categories, customers list, store/shift context, initial load and catalog visibility.
 * POS-03: a failed items load is blocking (`loadFailed`); other failed parts are listed in `partialErrors`.
 */
export function usePosCatalog({ authStore, customers }) {
    const items = ref([]);
    const categories = ref([]);
    const totalItemsCount = ref(0);
    const isLoadingCategoryItems = ref(false);
    const activeStore = ref(null);
    const activeShift = ref(null);
    const activeCategoryId = ref('favorites');
    const isLoading = ref(true);
    const loadFailed = ref(false);
    const partialErrors = ref([]);

    const favoriteItemsCount = computed(
        () =>
            items.value.filter((i) => (i.pos_sales_count || 0) > 0 || i.is_pos_pinned).length ||
            Math.min(items.value.length, 20)
    );

    const showCatalog = ref(localStorage.getItem('pos_show_catalog') !== 'false');
    const toggleCatalog = () => {
        showCatalog.value = !showCatalog.value;
        localStorage.setItem('pos_show_catalog', showCatalog.value ? 'true' : 'false');
    };

    const addPartialError = (key) => {
        if (!partialErrors.value.includes(key)) partialErrors.value.push(key);
    };
    const clearPartialError = (key) => {
        partialErrors.value = partialErrors.value.filter((k) => k !== key);
    };

    // Every load takes a ticket; a response from an older ticket (slow store A after switching to B) is dropped.
    let loadSeq = 0;
    let inFlight = false;

    const loaders = {
        items: async () => {
            const res = await api.get('/items', { params: { per_page: PRELOAD_ITEMS } });
            return () => {
                items.value = res.data?.data || [];
                totalItemsCount.value = res.data?.meta?.total || res.data?.summary?.total_items || items.value.length;
            };
        },
        categories: async () => {
            const res = await api.get('/categories', { params: { active_only: true } });
            return () => {
                categories.value = res.data?.data || [];
                if (res.data?.total_items_count) totalItemsCount.value = res.data.total_items_count;
            };
        },
        customers: async () => {
            const res = await api.get('/customers', { params: { per_page: 200 } });
            return () => {
                customers.value = res.data?.data || [];
            };
        },
        shift: async () => {
            const res = await api.get('/shifts/current');
            return () => {
                activeShift.value = res.data?.data || null;
            };
        },
    };

    const hasRealCategory = () => !!activeCategoryId.value && activeCategoryId.value !== 'favorites';

    const loadParts = async (names, { silent = false } = {}) => {
        const seq = ++loadSeq;
        inFlight = true;
        if (!silent) isLoading.value = true;
        try {
            const results = await Promise.allSettled(names.map((name) => loaders[name]()));
            if (seq !== loadSeq) return;
            names.forEach((name, i) => {
                const result = results[i];
                if (name === 'items') loadFailed.value = result.status === 'rejected';
                if (result.status === 'fulfilled') {
                    result.value();
                    clearPartialError(name);
                } else if (name !== 'items') {
                    addPartialError(name);
                }
            });
        } finally {
            if (seq === loadSeq) {
                inFlight = false;
                isLoading.value = false;
            }
        }
    };

    const ALL_PARTS = ['items', 'categories', 'customers', 'shift'];

    const fetchInitialData = async () => {
        activeStore.value = authStore.currentStore;
        await loadParts(ALL_PARTS);
    };

    const retryInitial = async () => {
        if (inFlight) return;
        await fetchInitialData();
    };

    const selectCategory = async (catId) => {
        activeCategoryId.value = catId;
        if (!catId) return;

        isLoadingCategoryItems.value = true;
        clearPartialError('category_items');
        try {
            const res = await api.get('/items', { params: { per_page: 200, category_id: catId } });
            if (res.data?.data) {
                const existingIds = new Set(items.value.map((i) => i.id));
                const toAdd = res.data.data.filter((i) => !existingIds.has(i.id));
                if (toAdd.length > 0) items.value = [...items.value, ...toAdd];
            }
        } catch {
            addPartialError('category_items');
        } finally {
            isLoadingCategoryItems.value = false;
        }
    };

    // Retries only the parts that failed; the loaded items are never replaced.
    const retryPartial = async () => {
        if (inFlight) return;
        const failed = partialErrors.value.filter((k) => k in loaders);
        if (failed.length > 0) await loadParts(failed, { silent: true });
        if (failed.includes('categories') || partialErrors.value.includes('category_items')) {
            if (hasRealCategory()) await selectCategory(activeCategoryId.value);
        }
    };

    const switchStore = async (storeId) => {
        const store = authStore.stores?.find((s) => String(s.id) === String(storeId));
        if (!store) return;

        authStore.switchStore(store);
        activeStore.value = store;
        // Never show the previous branch's shift, even if the new shift fails to load.
        activeShift.value = null;
        partialErrors.value = [];
        await loadParts(ALL_PARTS, { silent: true });
    };

    return {
        items,
        categories,
        totalItemsCount,
        isLoadingCategoryItems,
        activeStore,
        activeShift,
        activeCategoryId,
        isLoading,
        loadFailed,
        partialErrors,
        retryInitial,
        retryPartial,
        favoriteItemsCount,
        showCatalog,
        toggleCatalog,
        fetchInitialData,
        selectCategory,
        switchStore,
    };
}
