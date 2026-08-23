import { ref, computed, onMounted } from 'vue';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useTrash() {
    const { t } = useTrans();

    const currentTab = ref('items');
    const search = ref('');
    const records = ref([]);
    const counts = ref({});
    const isLoading = ref(false);

    const tabsList = computed(() => [
        { id: 'items', label: t('trash.tab_items_label'), icon: '📦' },
        { id: 'customers', label: t('trash.tab_customers_label'), icon: '👥' },
        { id: 'suppliers', label: t('trash.tab_suppliers_label'), icon: '🏭' },
        { id: 'stores', label: t('trash.tab_stores_label'), icon: '🏬' },
        { id: 'expenses', label: t('trash.tab_expenses_label'), icon: '💸' },
        { id: 'returns', label: t('trash.tab_returns_label'), icon: '🔄' },
    ]);

    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });

    let debounceTimer = null;
    const debouncedFetch = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            pagination.value.current_page = 1;
            fetchRecords();
        }, 300);
    };

    const updateSearch = (val) => {
        search.value = val;
        debouncedFetch();
    };

    const changeTab = (tab) => {
        currentTab.value = tab;
        search.value = '';
        pagination.value.current_page = 1;
        fetchRecords();
    };

    const fetchRecords = async () => {
        isLoading.value = true;
        try {
            const res = await api.get('/trash', {
                params: {
                    tab: currentTab.value,
                    search: search.value,
                    page: pagination.value.current_page,
                },
            });
            records.value = res.data?.data || [];
            counts.value = res.data?.counts || {};
            pagination.value = res.data?.pagination || pagination.value;
        } catch (e) {
            console.error('Failed to load trash records:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const changePage = (page) => {
        pagination.value.current_page = page;
        fetchRecords();
    };

    const restoreRecord = async (item) => {
        const result = await Swal.fire({
            title: t('trash.restore_record_confirm_title', { title: item.title }),
            text: t('trash.restore_record_confirm_text'),
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#334155',
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
        });

        if (result.isConfirmed) {
            try {
                await api.post(`/trash/${currentTab.value}/${item.id}/restore`);
                Swal.fire({ icon: 'success', title: t('trash.restore_success'), timer: 1500, showConfirmButton: false });
                fetchRecords();
            } catch (e) {
                Swal.fire({ icon: 'error', title: t('common.error'), text: e.response?.data?.message || t('trash.restore_failed') });
            }
        }
    };

    const forceDeleteRecord = async (item) => {
        const result = await Swal.fire({
            title: t('trash.force_delete_confirm_title', { title: item.title }),
            text: t('trash.force_delete_confirm_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#334155',
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
        });

        if (result.isConfirmed) {
            try {
                await api.delete(`/trash/${currentTab.value}/${item.id}/force`);
                Swal.fire({ icon: 'success', title: t('trash.force_delete_success'), timer: 1500, showConfirmButton: false });
                fetchRecords();
            } catch (e) {
                Swal.fire({ icon: 'error', title: t('common.error'), text: e.response?.data?.message || t('trash.force_delete_failed') });
            }
        }
    };

    onMounted(() => {
        fetchRecords();
    });

    return {
        currentTab,
        search,
        records,
        counts,
        isLoading,
        tabsList,
        pagination,
        updateSearch,
        changeTab,
        fetchRecords,
        changePage,
        restoreRecord,
        forceDeleteRecord,
    };
}
