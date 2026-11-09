import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../Services/api';
import { useTrans } from './useTrans';
import { businessToday, startOfMonth, endOfMonth, startOfYear, endOfYear } from './useBusinessDate';

export function useCustomerStatement() {
    const route = useRoute();
    const customerId = route.params.id;
    const { t } = useTrans();

    const customer = ref(null);
    const ledger = ref([]);
    const summary = ref({
        total_debit: 0,
        total_credit: 0,
        current_balance: 0,
    });

    const dateFrom = ref('');
    const dateTo = ref('');
    const activePreset = ref('all');
    const isLoading = ref(false);
    const error = ref(false);
    const errorMessage = ref('');

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
        error.value = false;
        errorMessage.value = '';
        try {
            const response = await api.get(`/customers/${customerId}/statement`, {
                params: {
                    from_date: dateFrom.value || undefined,
                    to_date: dateTo.value || undefined,
                },
            });
            const data = response.data?.data;
            if (data) {
                customer.value = data.customer;
                ledger.value = data.ledger || [];
                summary.value = data.summary || {};
            }
        } catch (err) {
            error.value = true;
            errorMessage.value = err.userMessage || err.message || t('common.error_occurred');
            console.error('Failed to load customer statement:', err);
        } finally {
            isLoading.value = false;
        }
    };

    const printStatement = () => {
        window.print();
    };

    onMounted(fetchStatement);

    return {
        error,
        errorMessage,
        customer,
        ledger,
        summary,
        dateFrom,
        dateTo,
        activePreset,
        isLoading,
        applyPreset,
        fetchStatement,
        printStatement,
    };
}
