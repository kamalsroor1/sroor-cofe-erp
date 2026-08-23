import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useCreateReturn() {
    const { t } = useTrans();
    const router = useRouter();

    const customers = ref([]);
    const suppliers = ref([]);
    const items = ref([]);

    const isSubmitting = ref(false);
    const selectedItemToAdd = ref(null);

    const form = reactive({
        return_type: 'sales_return',
        customer_id: null,
        supplier_id: null,
        return_date: new Date().toISOString().split('T')[0],
        refund_amount: '0.000',
        reason: '',
        items: [],
    });

    const netTotal = computed(() => {
        return form.items.reduce((sum, it) => sum + (parseFloat(it.quantity) || 0) * (parseFloat(it.unit_price) || 0), 0);
    });

    const loadFormDependencies = async () => {
        try {
            const [custRes, suppRes, itemRes] = await Promise.all([
                api.get('/customers?per_page=100'),
                api.get('/suppliers?per_page=100'),
                api.get('/items?per_page=100'),
            ]);

            customers.value = custRes.data?.data || [];
            suppliers.value = suppRes.data?.data || [];
            items.value = itemRes.data?.data || [];

            if (customers.value.length > 0) {
                form.customer_id = customers.value[0].id;
            }
            if (suppliers.value.length > 0) {
                form.supplier_id = suppliers.value[0].id;
            }
        } catch (error) {
            console.error('Failed to load return form data:', error);
        }
    };

    const onTypeChange = (type) => {
        form.return_type = type;
        form.items.forEach(line => {
            const it = items.value.find(i => i.id === line.item_id);
            if (it) {
                line.unit_price = type === 'sales_return' ? (parseFloat(it.price_retail || it.selling_price) || 0) : (parseFloat(it.cost_price) || 0);
            }
        });
    };

    const updateField = (field, val) => {
        form[field] = val;
    };

    const addItemRow = () => {
        if (!selectedItemToAdd.value) return;
        const it = selectedItemToAdd.value;

        if (form.items.some(i => i.item_id === it.id)) {
            Swal.fire({ icon: 'info', title: t('common.warning'), text: t('returns.item_already_in_return') });
            return;
        }

        const unitPrice = form.return_type === 'sales_return'
            ? (parseFloat(it.price_retail || it.selling_price) || 0)
            : (parseFloat(it.cost_price) || 0);

        form.items.push({
            item_id: it.id,
            name: it.name,
            unit: it.unit || 'كجم',
            quantity: 1,
            unit_price: unitPrice,
        });

        selectedItemToAdd.value = null;
    };

    const removeItemRow = (idx) => {
        form.items.splice(idx, 1);
    };

    const submitReturn = async () => {
        if (form.items.length === 0) {
            Swal.fire({ icon: 'warning', title: t('common.warning'), text: t('returns.add_at_least_one_item') });
            return;
        }

        isSubmitting.value = true;
        try {
            const payload = {
                return_type: form.return_type,
                customer_id: form.return_type === 'sales_return' ? form.customer_id : null,
                supplier_id: form.return_type === 'purchase_return' ? form.supplier_id : null,
                return_date: form.return_date,
                refund_amount: parseFloat(form.refund_amount) || 0,
                reason: form.reason || null,
                items: form.items.map(it => ({
                    item_id: it.item_id,
                    quantity: parseFloat(it.quantity),
                    unit_price: parseFloat(it.unit_price),
                })),
            };

            const response = await api.post('/returns', payload);
            Swal.fire({
                icon: 'success',
                title: t('returns.return_confirmed_title'),
                text: response.data?.message || t('returns.return_confirmed_msg'),
                timer: 1500,
                showConfirmButton: false,
            });

            router.push('/returns');
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: error.response?.data?.message || t('returns.save_return_failed'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    onMounted(() => {
        loadFormDependencies();
    });

    return {
        customers,
        suppliers,
        items,
        isSubmitting,
        selectedItemToAdd,
        form,
        netTotal,
        onTypeChange,
        updateField,
        addItemRow,
        removeItemRow,
        submitReturn,
    };
}
