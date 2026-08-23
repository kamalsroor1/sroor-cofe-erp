import { ref, computed, onMounted } from 'vue';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function usePurchases() {
    const { t } = useTrans();

    const purchases = ref([]);
    const metrics = ref({
        total_purchases: 0,
        unpaid_total: 0,
        confirmed_count: 0,
    });

    const searchQuery = ref('');
    const selectedStatus = ref('all');
    const dateFrom = ref('');
    const dateTo = ref('');
    const isLoading = ref(false);

    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });

    let debounceTimeout = null;

    const showDetailsModal = ref(false);
    const selectedPurchase = ref(null);

    const statusOptions = computed(() => [
        { value: 'all', label: t('purchases.status_all') },
        { value: 'confirmed', label: t('purchases.status_confirmed_badge') },
        { value: 'cancelled', label: t('purchases.status_cancelled_badge') },
    ]);

    const fetchPurchases = async (page = 1) => {
        isLoading.value = true;
        try {
            const response = await api.get('/purchases', {
                params: {
                    search: searchQuery.value || undefined,
                    status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
                    from: dateFrom.value || undefined,
                    to: dateTo.value || undefined,
                    page: page,
                    per_page: 15,
                },
            });
            purchases.value = response.data?.data || [];
            metrics.value = response.data?.summary || {
                total_purchases: 0,
                unpaid_total: 0,
                confirmed_count: 0,
            };
            pagination.value = response.data?.meta || {
                current_page: page,
                last_page: 1,
                per_page: 15,
                total: purchases.value.length,
            };
        } catch (error) {
            console.error('Failed to load purchases:', error);
        } finally {
            isLoading.value = false;
        }
    };

    const debounceSearch = () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            fetchPurchases(1);
        }, 300);
    };

    const openDetailsModal = (p) => {
        selectedPurchase.value = p;
        showDetailsModal.value = true;
    };

    const cancelPurchase = async (p) => {
        const result = await Swal.fire({
            title: t('purchases.cancel_po_confirm_title', { number: p.purchase_number }),
            text: t('purchases.cancel_po_confirm_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
            confirmButtonColor: '#f43f5e',
        });

        if (result.isConfirmed) {
            try {
                await api.post(`/purchases/${p.id}/cancel`, { reason: t('purchases.cancel_reason_default') });
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('purchases.purchase_cancelled_success'),
                    timer: 1500,
                    showConfirmButton: false,
                });
                await fetchPurchases(pagination.value.current_page);
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: t('common.error'),
                    text: error.userMessage || t('purchases.purchase_cancelled_failed'),
                });
            }
        }
    };

    onMounted(() => {
        fetchPurchases(1);
    });

    return {
        purchases,
        metrics,
        searchQuery,
        selectedStatus,
        statusOptions,
        dateFrom,
        dateTo,
        isLoading,
        pagination,
        showDetailsModal,
        selectedPurchase,
        fetchPurchases,
        debounceSearch,
        openDetailsModal,
        cancelPurchase,
    };
}
