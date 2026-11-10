import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../Services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';
import { useLoadError } from './useLoadError';
import { useUnits } from './useUnits';
import { useRemoteOptions } from './useRemoteOptions';
import { businessToday } from './useBusinessDate';

export function useCreateStockTransfer() {
    const { unitLabel } = useUnits();
    const router = useRouter();
    const { t } = useTrans();
    const { error, errorMessage, clearError, setError } = useLoadError();
    const isLoading = ref(false);

    const stores = ref([]);
    const { searchFn: searchItems } = useRemoteOptions('items', {
        map: (row) => ({
            value: row.id,
            label: `${row.name} (${row.code || '—'}) — ${t('inventory.current_stock')}: ${row.current_stock} ${row.unit || ''}`,
            raw: row,
        }),
    });
    const isSubmitting = ref(false);
    const selectedItem = ref(null);

    const form = reactive({
        from_store_id: null,
        to_store_id: null,
        transfer_date: businessToday(),
        notes: '',
        items: [],
    });

    const fromStoreOptions = computed(() => {
        return stores.value.map((s) => ({
            value: s.id,
            label: `${s.name} (${s.type === 'warehouse' ? t('inventory.store_type_warehouse_short') : t('inventory.store_type_branch_short')})`,
        }));
    });

    const toStoreOptions = computed(() => {
        return stores.value.map((s) => ({
            value: s.id,
            label: `${s.name} (${s.type === 'warehouse' ? t('inventory.store_type_warehouse_short') : t('inventory.store_type_branch_short')})`,
            disabled: s.id === form.from_store_id,
        }));
    });

    const loadDependencies = async () => {
        isLoading.value = true;
        clearError();
        try {
            const storesRes = await api.get('/stores');

            stores.value = storesRes.data?.data || storesRes.data?.stores || [];

            if (stores.value.length >= 2) {
                form.from_store_id = stores.value[0].id;
                form.to_store_id = stores.value[1].id;
            } else if (stores.value.length === 1) {
                form.from_store_id = stores.value[0].id;
            }
        } catch (err) {
            setError(err);
        } finally {
            isLoading.value = false;
        }
    };

    const addItemRow = () => {
        const it = selectedItem.value;
        if (!it) return;

        if (form.items.some((i) => i.item_id === it.id)) {
            Swal.fire({ icon: 'info', title: t('common.warning'), text: t('inventory.item_already_in_transfer') });
            return;
        }

        form.items.push({
            item_id: it.id,
            name: it.name,
            code: it.code,
            unit: unitLabel(it.unit),
            quantity: 1,
        });

        selectedItem.value = null;
    };

    const removeItemRow = (idx) => {
        form.items.splice(idx, 1);
    };

    const submitTransfer = async () => {
        if (!form.from_store_id || !form.to_store_id) {
            Swal.fire({ icon: 'warning', title: t('common.warning'), text: t('inventory.choose_source_store') });
            return;
        }
        if (form.from_store_id === form.to_store_id) {
            Swal.fire({ icon: 'error', title: t('common.error'), text: t('inventory.same_store_transfer_error') });
            return;
        }
        if (form.items.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: t('common.warning'),
                text: t('inventory.add_at_least_one_item_transfer'),
            });
            return;
        }

        isSubmitting.value = true;
        try {
            const payload = {
                from_store_id: form.from_store_id,
                to_store_id: form.to_store_id,
                transfer_date: form.transfer_date,
                notes: form.notes || null,
                items: form.items.map((it) => ({
                    item_id: it.item_id,
                    quantity: parseFloat(it.quantity),
                })),
            };

            const response = await api.post('/transfers', payload);
            Swal.fire({
                icon: 'success',
                title: t('inventory.transfer_executed_title'),
                text: response.data?.message || t('inventory.transfer_executed_msg'),
                timer: 1500,
                showConfirmButton: false,
            });

            router.push('/stock-transfers');
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: error.userMessage || t('inventory.transfer_execution_failed'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    onMounted(loadDependencies);

    return {
        isLoading,
        error,
        errorMessage,
        retry: loadDependencies,
        stores,
        form,
        isSubmitting,
        selectedItem,
        fromStoreOptions,
        toStoreOptions,
        searchItems,
        addItemRow,
        removeItemRow,
        submitTransfer,
    };
}
