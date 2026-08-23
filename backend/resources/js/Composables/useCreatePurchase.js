import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useCreatePurchase() {
    const router = useRouter();
    const route = useRoute();
    const { t } = useTrans();

    const suppliers = ref([]);
    const availableItems = ref([]);
    const isSubmitting = ref(false);

    const form = reactive({
        supplier_id: '',
        purchase_date: new Date().toISOString().split('T')[0],
        supplier_invoice_ref: '',
        paid_amount: '0.000',
        discount_amount: '0.000',
        payment_method: 'cash',
        notes: '',
        items: [
            { item_id: '', quantity: '1.000', cost_price: '0.000' }
        ],
    });

    const supplierOptions = computed(() => {
        return suppliers.value.map(s => ({
            value: s.id,
            label: s.company_name ? `${s.name} (${s.company_name})` : s.name,
        }));
    });

    const subtotal = computed(() => {
        return form.items.reduce((sum, item) => {
            const q = parseFloat(item.quantity) || 0;
            const c = parseFloat(item.cost_price) || 0;
            return sum + (q * c);
        }, 0);
    });

    const discount = computed(() => parseFloat(form.discount_amount) || 0);
    const paid = computed(() => parseFloat(form.paid_amount) || 0);
    const netTotal = computed(() => Math.max(0, subtotal.value - discount.value));
    const remaining = computed(() => Math.max(0, netTotal.value - paid.value));

    const addItemLine = () => {
        form.items.push({ item_id: '', quantity: '1.000', cost_price: '0.000' });
    };

    const removeItemLine = (idx) => {
        if (form.items.length > 1) {
            form.items.splice(idx, 1);
        }
    };

    const onItemSelect = (line) => {
        const item = availableItems.value.find(it => it.id === parseInt(line.item_id, 10));
        if (item) {
            line.cost_price = item.cost_price?.toString() || '0.000';
        }
    };

    const loadInitialData = async () => {
        try {
            const [supRes, itemRes] = await Promise.all([
                api.get('/suppliers', { params: { per_page: 100 } }),
                api.get('/items', { params: { per_page: 200 } }),
            ]);

            suppliers.value = supRes.data?.data || [];
            availableItems.value = itemRes.data?.data || [];

            // Check for smart reorder prefill
            if (route.query.prefill) {
                try {
                    const prefilled = JSON.parse(route.query.prefill);
                    if (Array.isArray(prefilled) && prefilled.length > 0) {
                        form.items = prefilled.map(p => ({
                            item_id: p.item_id || p.id,
                            quantity: (p.quantity || p.suggested_reorder_qty || 10).toString(),
                            cost_price: (p.cost_price || 0).toString(),
                        }));
                    }
                } catch (err) {
                    console.error('Error parsing prefill params:', err);
                }
            }
        } catch (error) {
            console.error('Failed to load form dependencies:', error);
        }
    };

    const submitPurchase = async () => {
        if (!form.supplier_id) {
            Swal.fire({ icon: 'warning', title: t('common.warning'), text: t('purchases.select_supplier_warning') });
            return;
        }

        const invalidLine = form.items.find(it => !it.item_id || parseFloat(it.quantity) <= 0);
        if (invalidLine) {
            Swal.fire({ icon: 'warning', title: t('common.warning'), text: t('purchases.invalid_line_warning') });
            return;
        }

        isSubmitting.value = true;
        try {
            const payload = {
                ...form,
                items: form.items.map(it => ({
                    item_id: parseInt(it.item_id, 10),
                    quantity: parseFloat(it.quantity),
                    unit_cost: parseFloat(it.cost_price),
                })),
            };

            const response = await api.post('/purchases', payload);
            Swal.fire({
                icon: 'success',
                title: t('purchases.supply_confirmed_title'),
                text: response.data?.message || t('purchases.supply_confirmed_msg'),
            });
            router.push({ name: 'purchases.index' });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: error.userMessage || t('common.error'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    onMounted(loadInitialData);

    return {
        form,
        suppliers,
        availableItems,
        supplierOptions,
        isSubmitting,
        subtotal,
        discount,
        paid,
        netTotal,
        remaining,
        addItemLine,
        removeItemLine,
        onItemSelect,
        submitPurchase,
    };
}
