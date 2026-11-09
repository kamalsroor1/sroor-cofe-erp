import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../Services/api';
import { businessToday, startOfMonth, endOfMonth, startOfYear, endOfYear } from './useBusinessDate';

export function useSupplierStatement() {
    const route = useRoute();
    const supplierId = route.params.id;

    const supplier = ref(null);
    const ledger = ref([]);
    const summary = ref({
        total_purchases: 0,
        total_paid: 0,
        current_balance: 0,
    });

    const dateFrom = ref('');
    const dateTo = ref('');
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
            }
        } catch (error) {
            console.error('Failed to load supplier statement:', error);
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
