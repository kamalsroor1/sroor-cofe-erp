import { ref, reactive, computed } from 'vue';
import Swal from 'sweetalert2';
import api from '../Services/api';
import { useTrans } from './useTrans';
import { useAuthStore } from '../stores/auth';
import { normalize } from '../helpers/decimal';

/**
 * SHOP-06: transfers between treasury accounts (drawer, InstaPay, wallet, bank) for the daily journal.
 * The server owns every money rule (balance check under a lock, same-account refusal); this only
 * collects the form and shows the day's history.
 */
export function useTreasuryTransfers() {
    const { t } = useTrans();
    const authStore = useAuthStore();

    const canTransfer = computed(() => authStore.hasRole('admin') || authStore.can('treasury.transfer'));
    const canViewTransfers = computed(() => canTransfer.value || authStore.can('reports.view'));

    const transfers = ref([]);
    const isLoading = ref(false);
    const loadFailed = ref(false);
    const isSubmitting = ref(false);
    const showModal = ref(false);

    const emptyForm = () => ({ from_method: 'cash', to_method: 'instapay', amount: '', transfer_fee: '', notes: '' });
    const form = reactive(emptyForm());

    const updateField = (field, value) => {
        form[field] = value;
    };

    const openModal = () => {
        Object.assign(form, emptyForm());
        showModal.value = true;
    };

    const fetchTransfers = async (date) => {
        if (!canViewTransfers.value) return;
        isLoading.value = true;
        loadFailed.value = false;
        try {
            const response = await api.get('/treasury/transfers', {
                params: { from_date: date, to_date: date, per_page: 100 },
            });
            transfers.value = response.data?.data ?? [];
        } catch {
            loadFailed.value = true;
            transfers.value = [];
        } finally {
            isLoading.value = false;
        }
    };

    const submitTransfer = async (date, onDone) => {
        if (isSubmitting.value) return;
        if (form.from_method === form.to_method) {
            Swal.fire({ icon: 'error', title: t('common.error'), text: t('treasury.same_account_error') });
            return;
        }
        isSubmitting.value = true;
        try {
            const response = await api.post('/treasury/transfers', {
                from_method: form.from_method,
                to_method: form.to_method,
                amount: normalize(form.amount),
                transfer_fee:
                    form.transfer_fee === '' || form.transfer_fee === null ? null : normalize(form.transfer_fee),
                transfer_date: date,
                notes: form.notes || null,
            });
            showModal.value = false;
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: response.data?.message || t('common.success'),
                timer: 2000,
                showConfirmButton: false,
            });
            await fetchTransfers(date);
            if (onDone) await onDone();
        } catch (error) {
            Swal.fire({ icon: 'error', title: t('common.error'), text: error.userMessage || t('common.error') });
        } finally {
            isSubmitting.value = false;
        }
    };

    return {
        canTransfer,
        canViewTransfers,
        transfers,
        isLoading,
        loadFailed,
        isSubmitting,
        showModal,
        form,
        updateField,
        openModal,
        fetchTransfers,
        submitTransfer,
    };
}
