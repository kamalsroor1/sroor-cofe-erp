import { ref, computed, onMounted } from 'vue';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useReturns() {
    const { t } = useTrans();

    const returnsList = ref([]);
    const summary = ref({
        total_value: 0,
        sales_count: 0,
        purchase_count: 0,
        total_count: 0,
    });

    const searchQuery = ref('');
    const selectedType = ref('all');
    const dateFrom = ref('');
    const dateTo = ref('');
    const isLoading = ref(false);

    const typeOptions = computed(() => [
        { value: 'all', label: t('returns.all_return_types') },
        { value: 'sales_return', label: t('returns.sales_return_option') },
        { value: 'purchase_return', label: t('returns.purchase_return_option') },
    ]);

    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });

    let debounceTimer = null;

    const showDetailsModal = ref(false);
    const selectedReturnDetails = ref(null);

    const fetchReturns = async (page = 1) => {
        isLoading.value = true;
        try {
            const response = await api.get('/returns', {
                params: {
                    search: searchQuery.value || undefined,
                    type: selectedType.value !== 'all' ? selectedType.value : undefined,
                    from_date: dateFrom.value || undefined,
                    to_date: dateTo.value || undefined,
                    page: page,
                    per_page: 15,
                },
            });
            returnsList.value = response.data?.data || [];
            summary.value = response.data?.summary || {
                total_value: 0,
                sales_count: 0,
                purchase_count: 0,
                total_count: 0,
            };
            pagination.value = response.data?.meta || {
                current_page: page,
                last_page: 1,
                per_page: 15,
                total: returnsList.value.length,
            };
        } catch (error) {
            console.error('Failed to load returns:', error);
        } finally {
            isLoading.value = false;
        }
    };

    const updateSearch = (val) => {
        searchQuery.value = val;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetchReturns(1);
        }, 300);
    };

    const updateType = (val) => {
        selectedType.value = val;
        fetchReturns(1);
    };

    const updateDateFrom = (val) => {
        dateFrom.value = val;
        fetchReturns(1);
    };

    const updateDateTo = (val) => {
        dateTo.value = val;
        fetchReturns(1);
    };

    const openDetailsModal = async (ret) => {
        try {
            const response = await api.get(`/returns/${ret.id}`);
            selectedReturnDetails.value = response.data?.data;
            showDetailsModal.value = true;
        } catch (error) {
            console.error('Failed to load return details:', error);
        }
    };

    const deleteReturnDoc = async (ret) => {
        const result = await Swal.fire({
            title: t('returns.confirm_archive', { number: ret.return_number }),
            text: t('returns.archive_confirm_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
            confirmButtonColor: '#f43f5e',
        });

        if (result.isConfirmed) {
            try {
                await api.delete(`/returns/${ret.id}`);
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('returns.deleted_success', { number: ret.return_number }),
                    timer: 1500,
                    showConfirmButton: false,
                });
                await fetchReturns(pagination.value.current_page);
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: t('common.error'),
                    text: error.response?.data?.message || t('returns.archive_failed'),
                });
            }
        }
    };

    onMounted(() => {
        fetchReturns(1);
    });

    return {
        returnsList,
        summary,
        searchQuery,
        selectedType,
        typeOptions,
        dateFrom,
        dateTo,
        isLoading,
        pagination,
        showDetailsModal,
        selectedReturnDetails,
        fetchReturns,
        updateSearch,
        updateType,
        updateDateFrom,
        updateDateTo,
        openDetailsModal,
        deleteReturnDoc,
    };
}
