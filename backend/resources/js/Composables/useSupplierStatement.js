import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../Services/api';
import { useLoadError } from './useLoadError';
import { useTrans } from './useTrans';
import { businessToday, startOfMonth, endOfMonth, startOfYear, endOfYear } from './useBusinessDate';

export function useSupplierStatement() {
    const route = useRoute();
    const supplierId = route.params.id;
    const { error, errorMessage, clearError, setError } = useLoadError();
    const { t } = useTrans();

    const supplier = ref(null);
    const ledger = ref([]);
    const summary = ref({
        total_purchases: 0,
        total_paid: 0,
        current_balance: 0,
    });

    const dateFrom = ref('');
    const dateTo = ref('');
    // Dates of the period the loaded ledger belongs to (not the inputs, which may be mid-edit).
    const loadedFrom = ref('');

    // BUG-08: a period that does not start at the beginning opens with a carried-forward row.
    const rows = computed(() => {
        if (!loadedFrom.value) return ledger.value;
        return [
            {
                date: loadedFrom.value,
                type: t('contacts.statement_opening_row'),
                ref_number: '',
                debit: null,
                credit: null,
                balance_after: summary.value.opening_balance ?? '0.000',
                notes: '',
            },
            ...ledger.value,
        ];
    });
    const activePreset = ref('all');
    const isLoading = ref(false);

    const applyPreset = (preset) => {
        activePreset.value = preset;
        const today = businessToday();

        if (preset === 'today') {
            dateFrom.value = today;
            dateTo.value = today;
        } else if (preset === 'this_month') {
            dateFrom.value = startOfMonth(today);
            dateTo.value = endOfMonth(today);
        } else if (preset === 'this_year') {
            dateFrom.value = startOfYear(today);
            dateTo.value = endOfYear(today);
        } else if (preset === 'all') {
            dateFrom.value = '';
            dateTo.value = '';
        }
        fetchStatement();
    };

    const fetchStatement = async () => {
        isLoading.value = true;
        clearError();
        try {
            const response = await api.get(`/suppliers/${supplierId}/statement`, {
                params: {
                    from_date: dateFrom.value || undefined,
                    to_date: dateTo.value || undefined,
                },
            });
            const data = response.data?.data;
            if (data) {
                supplier.value = data.supplier;
                ledger.value = data.ledger || [];
                summary.value = data.summary || {};
                loadedFrom.value = dateFrom.value || '';
            } else {
                setError(null);
            }
        } catch (err) {
            setError(err);
        } finally {
            isLoading.value = false;
        }
    };

    const printStatement = () => {
        window.print();
    };

    onMounted(fetchStatement);

    return {
        supplier,
        ledger,
        rows,
        summary,
        dateFrom,
        dateTo,
        activePreset,
        isLoading,
        error,
        errorMessage,
        retry: fetchStatement,
        applyPreset,
        fetchStatement,
        printStatement,
    };
}
