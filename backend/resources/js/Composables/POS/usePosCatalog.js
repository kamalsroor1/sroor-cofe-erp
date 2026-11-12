import { ref, computed, onBeforeUnmount } from 'vue';
import api from '../../Services/api';
import { usePosSaleSettings } from './usePosSaleSettings';
import { catalogParams, FAVORITES_LIMIT, mergePage } from '../../helpers/posCatalog';

const PAGE_SIZE = 60;
const SEARCH_DEBOUNCE_MS = 250;
const DEFAULT_CARDS_PER_ROW = '3';

const isCancel = (err) => err?.name === 'CanceledError' || err?.code === 'ERR_CANCELED' || err?.message === 'canceled';

/**
 * POS catalog: items, categories, customers list, store/shift context, initial load and catalog visibility.
 * POS-03: a failed items load is blocking (`loadFailed`); other failed parts are listed in `partialErrors`.
 * POS-04/05: the grid is a server-paged GET /pos/items (filters, search, sort, per-category counts in meta.counts).
 */
export function usePosCatalog({ authStore, customers }) {
    const { applySaleSettings } = usePosSaleSettings();
    const items = ref([]);
    const categories = ref([]);
    // meta.counts of the last items page: counters honour every filter but the category.
    const counts = ref({ total: 0, categories: [] });
    const lastPage = ref(1);
    const currentPage = ref(1);
    const isLoadingMore = ref(false);
    // searchTerm is what the cashier typed (live); gridTerm is the term the grid currently shows or is loading;
    // loadedTerm is the term of the last page 1 that actually arrived.
    const searchTerm = ref('');
    const gridTerm = ref('');
    const loadedTerm = ref('');
    // Per-branch display settings (store_pos_settings), read from /pos/bootstrap.
    const cardsPerRow = ref(DEFAULT_CARDS_PER_ROW);
    const showItemImages = ref(false);
    const isLoadingCategoryItems = ref(false);
    const activeStore = ref(null);
    const activeShift = ref(null);
    // POS-10: live totals of the open shift (X-report) and whether the active branch needs an open shift to sell.
    const shiftMetrics = ref(null);
    const requiresShift = ref(false);
    // POS-07: the store's discount cap (% of the sale); null until loaded. The server enforces it either way.
    const maxDiscountPercent = ref(null);
    const activeCategoryId = ref('favorites');
    const isLoading = ref(true);
    const loadFailed = ref(false);
    const partialErrors = ref([]);

    const totalItemsCount = computed(() => counts.value.total);

    const categoryCounts = computed(() => {
        const map = new Map();
        counts.value.categories.forEach((c) => map.set(c.category_id, c.count));
        return map;
    });

    // Real counters from the server; before the first page arrives the /categories figure is shown.
    const categoriesWithCounts = computed(() =>
        categories.value.map((c) => ({
            ...c,
            items_count: counts.value.categories.length ? (categoryCounts.value.get(c.id) ?? 0) : c.items_count,
        }))
    );

    const favoriteItemsCount = computed(() => Math.min(counts.value.total, FAVORITES_LIMIT));

    // Number shown next to the grid title: the active category's count, else the total.
    const visibleCount = computed(() => {
        const tab = activeCategoryId.value;
        if (!gridTerm.value && typeof tab === 'number') return categoryCounts.value.get(tab) ?? 0;
        if (!gridTerm.value && tab === 'favorites') return favoriteItemsCount.value;
        return counts.value.total;
    });

    const hasMore = computed(() => {
        if (!gridTerm.value && activeCategoryId.value === 'favorites') return false;
        return currentPage.value < lastPage.value;
    });

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

    let pageController = null;
    let moreController = null;
    let gridSeq = 0;

    const fetchPage = async (page, { signal } = {}) => {
        const params = { per_page: PAGE_SIZE, page, ...catalogParams(activeCategoryId.value, gridTerm.value) };
        return api.get('/pos/items', { params, signal });
    };

    const applyPage = (res, page) => {
        const rows = res.data?.data || [];
        const meta = res.data?.meta || {};
        items.value = mergePage(items.value, rows, page);
        if (page === 1) loadedTerm.value = gridTerm.value;
        currentPage.value = meta.current_page || page;
        lastPage.value = meta.last_page || 1;
        if (meta.counts) counts.value = meta.counts;
    };

    // Aborts the grid reload and any "load more" in flight; their late responses are dropped by the seq check too.
    const cancelPageLoad = () => {
        if (pageController) pageController.abort();
        if (moreController) moreController.abort();
        pageController = null;
        moreController = null;
    };

    const loaders = {
        items: async () => {
            const res = await fetchPage(1);
            return () => {
                // A full reload supersedes any grid request still in flight.
                gridSeq++;
                cancelPageLoad();
                isLoadingCategoryItems.value = false;
                isLoadingMore.value = false;
                applyPage(res, 1);
            };
        },
        categories: async () => {
            const res = await api.get('/categories', { params: { active_only: true } });
            return () => {
                categories.value = res.data?.data || [];
            };
        },
        customers: async () => {
            const res = await api.get('/customers', { params: { per_page: 200 } });
            return () => {
                customers.value = res.data?.data || [];
            };
        },
        // Never blocks the POS: without the cap the warning is simply not shown (the server still enforces it).
        discountPolicy: async () => {
            let cap = null;
            let saleSettings = null;
            let display = { cardsPerRow: DEFAULT_CARDS_PER_ROW, showImages: false };
            try {
                const res = await api.get('/pos/bootstrap', { params: { include_items: 0, include_customers: 0 } });
                const posSettings = res.data?.data?.pos_settings;
                cap = posSettings?.effective_max_discount_percent ?? null;
                saleSettings = res.data?.data?.sale_settings ?? null;
                display = {
                    cardsPerRow: posSettings?.catalog_cards_per_row || DEFAULT_CARDS_PER_ROW,
                    showImages: !!posSettings?.show_item_images,
                };
            } catch {
                cap = null;
            }
            return () => {
                maxDiscountPercent.value = cap;
                applySaleSettings(saleSettings);
                cardsPerRow.value = display.cardsPerRow;
                showItemImages.value = display.showImages;
            };
        },
        shift: async () => {
            const res = await api.get('/shifts/current');
            return () => {
                activeShift.value = res.data?.active_shift || null;
                shiftMetrics.value = res.data?.metrics || null;
                requiresShift.value = !!res.data?.requires_shift;
            };
        },
    };

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

    const ALL_PARTS = ['items', 'categories', 'customers', 'shift', 'discountPolicy'];

    const fetchInitialData = async () => {
        activeStore.value = authStore.currentStore;
        await loadParts(ALL_PARTS);
    };

    const retryInitial = async () => {
        if (inFlight) return;
        await fetchInitialData();
    };

    // Reloads page 1 of the grid for the active tab / search; a newer request cancels the older one.
    const reloadGrid = async () => {
        const seq = ++gridSeq;
        cancelPageLoad();
        gridTerm.value = searchTerm.value;
        pageController = new AbortController();
        const { signal } = pageController;
        isLoadingCategoryItems.value = true;
        isLoadingMore.value = false;
        clearPartialError('category_items');
        clearPartialError('catalog_more');
        try {
            const res = await fetchPage(1, { signal });
            if (seq === gridSeq) applyPage(res, 1);
        } catch (err) {
            if (seq === gridSeq && !isCancel(err)) addPartialError('category_items');
        } finally {
            if (seq === gridSeq) isLoadingCategoryItems.value = false;
        }
    };

    const selectCategory = async (catId) => {
        activeCategoryId.value = catId;
        items.value = [];
        await reloadGrid();
    };

    const loadMore = async () => {
        // A failed page is retried only from the error bar, never by the scroll sentinel in a loop.
        if (partialErrors.value.includes('catalog_more')) return;
        if (!hasMore.value || isLoadingMore.value || isLoadingCategoryItems.value || inFlight) return;
        const seq = gridSeq;
        const nextPage = currentPage.value + 1;
        isLoadingMore.value = true;
        clearPartialError('catalog_more');
        if (moreController) moreController.abort();
        moreController = new AbortController();
        const { signal } = moreController;
        try {
            const res = await fetchPage(nextPage, { signal });
            if (seq === gridSeq) applyPage(res, nextPage);
        } catch (err) {
            if (seq === gridSeq && !isCancel(err)) addPartialError('catalog_more');
        } finally {
            if (seq === gridSeq) isLoadingMore.value = false;
        }
    };

    // The header search drives the grid (and, through searchResults, the header dropdown: one request per term).
    // A change cancels whatever is in flight at once; the request itself is debounced and skipped when the text
    // is back to what the grid already shows (a scanner burst that Enter clears before the debounce fires).
    let searchTimer = null;
    let reloadCancelled = false;
    const setSearch = (term) => {
        const next = (term || '').trim();
        if (next === searchTerm.value) return;
        searchTerm.value = next;
        if (searchTimer) clearTimeout(searchTimer);
        if (isLoadingCategoryItems.value) reloadCancelled = true;
        gridSeq++;
        cancelPageLoad();
        isLoadingCategoryItems.value = false;
        isLoadingMore.value = false;
        searchTimer = setTimeout(
            () => {
                searchTimer = null;
                if (next === gridTerm.value && !reloadCancelled) return;
                reloadCancelled = false;
                items.value = [];
                reloadGrid();
            },
            next ? SEARCH_DEBOUNCE_MS : 0
        );
    };

    // Matches of the term the cashier typed, ready for the header dropdown (empty until that term has loaded).
    const searchResults = computed(() =>
        searchTerm.value && loadedTerm.value === searchTerm.value && gridTerm.value === searchTerm.value
            ? items.value
            : []
    );
    const isSearchingResults = computed(
        () => searchTerm.value !== '' && (loadedTerm.value !== searchTerm.value || isLoadingCategoryItems.value)
    );

    onBeforeUnmount(() => {
        if (searchTimer) clearTimeout(searchTimer);
        cancelPageLoad();
    });

    // Retries only the parts that failed; the loaded items are never replaced.
    const retryPartial = async () => {
        if (inFlight) return;
        const failed = partialErrors.value.filter((k) => k in loaders);
        if (failed.length > 0) await loadParts(failed, { silent: true });
        if (partialErrors.value.includes('category_items')) await reloadGrid();
        if (partialErrors.value.includes('catalog_more')) {
            clearPartialError('catalog_more');
            await loadMore();
        }
    };

    const switchStore = async (storeId) => {
        const store = authStore.stores?.find((s) => String(s.id) === String(storeId));
        if (!store) return;

        authStore.switchStore(store);
        activeStore.value = store;
        // Never show the previous branch's shift, even if the new shift fails to load.
        activeShift.value = null;
        shiftMetrics.value = null;
        requiresShift.value = false;
        maxDiscountPercent.value = null;
        partialErrors.value = [];
        await loadParts(ALL_PARTS, { silent: true });
    };

    // POS-10: re-read only the shift (after open/close, a refused sale or opening the X-report).
    const reloadShift = async () => {
        await loadParts(['shift'], { silent: true });
    };

    return {
        items,
        categories: categoriesWithCounts,
        totalItemsCount,
        visibleCount,
        hasMore,
        isLoadingMore,
        loadMore,
        setSearch,
        searchResults,
        isSearchingResults,
        cardsPerRow,
        showItemImages,
        isLoadingCategoryItems,
        activeStore,
        activeShift,
        shiftMetrics,
        requiresShift,
        reloadShift,
        maxDiscountPercent,
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
