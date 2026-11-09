import { ref, computed } from 'vue';
import api from '../../Services/api';

const PRELOAD_ITEMS = 300;

const totalFrom = (categoriesRes, itemsRes, items) =>
    categoriesRes.data?.total_items_count ||
    itemsRes.data?.meta?.total ||
    itemsRes.data?.summary?.total_items ||
    items.length;

/**
 * POS catalog: items, categories, customers list, store/shift context, initial load and catalog visibility.
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

    const fetchInitialData = async () => {
        activeStore.value = authStore.currentStore;
        isLoading.value = true;
        try {
            const [itemsRes, customersRes, shiftRes, categoriesRes] = await Promise.all([
                api.get('/items', { params: { per_page: PRELOAD_ITEMS } }),
                api.get('/customers', { params: { per_page: 200 } }),
                api.get('/shifts/current').catch(() => ({ data: { data: null } })),
                api.get('/categories', { params: { active_only: true } }).catch(() => ({ data: { data: [] } })),
            ]);
            items.value = itemsRes.data?.data || [];
            customers.value = customersRes.data?.data || [];
            activeShift.value = shiftRes.data?.data || null;
            categories.value = categoriesRes.data?.data || [];
            totalItemsCount.value = totalFrom(categoriesRes, itemsRes, items.value);
        } catch (e) {
            console.error('Failed to load POS data:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const selectCategory = async (catId) => {
        activeCategoryId.value = catId;
        if (!catId) return;

        isLoadingCategoryItems.value = true;
        try {
            const res = await api.get('/items', { params: { per_page: 200, category_id: catId } });
            if (res.data?.data) {
                const existingIds = new Set(items.value.map((i) => i.id));
                const toAdd = res.data.data.filter((i) => !existingIds.has(i.id));
                if (toAdd.length > 0) items.value = [...items.value, ...toAdd];
            }
        } catch (err) {
            console.error('Failed to load category items:', err);
        } finally {
            isLoadingCategoryItems.value = false;
        }
    };

    const switchStore = async (storeId) => {
        const store = authStore.stores?.find((s) => String(s.id) === String(storeId));
        if (!store) return;

        authStore.switchStore(store);
        activeStore.value = store;
        try {
            const [itemsRes, categoriesRes] = await Promise.all([
                api.get('/items', { params: { per_page: PRELOAD_ITEMS } }),
                api.get('/categories', { params: { active_only: true } }).catch(() => ({ data: { data: [] } })),
            ]);
            items.value = itemsRes.data?.data || [];
            categories.value = categoriesRes.data?.data || [];
            totalItemsCount.value = totalFrom(categoriesRes, itemsRes, items.value);
        } catch (e) {
            console.error('Failed to refresh items for switched store:', e);
        }
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
        favoriteItemsCount,
        showCatalog,
        toggleCatalog,
        fetchInitialData,
        selectCategory,
        switchStore,
    };
}
