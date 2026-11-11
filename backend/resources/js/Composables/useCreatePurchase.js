import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '../Services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';
import { useLoadError } from './useLoadError';
import { businessToday } from './useBusinessDate';
import { useRemoteOptions } from './useRemoteOptions';
import { isPositive, normalize } from '../helpers/decimal';

/** Prefilled line quantity: the first positive of quantity / suggested qty ('0.000' is not a quantity), else 10. */
const prefillQuantity = (p) => normalize([p.quantity, p.suggested_reorder_qty].find((q) => isPositive(q)) ?? '10');

const newLine = () => ({ item_id: '', item_option: null, quantity: '1.000', cost_price: '0.000' });

export function useCreatePurchase() {
    const router = useRouter();
    const route = useRoute();
    const { t } = useTrans();
    const { error, errorMessage, clearError, setError } = useLoadError();
    const isLoading = ref(false);

    const { searchFn: searchSuppliers } = useRemoteOptions('suppliers');
    const { searchFn: searchItems, fetchOption: fetchItemOption } = useRemoteOptions('items');
    const isSubmitting = ref(false);

    const form = reactive({
        supplier_id: '',
        purchase_date: businessToday(),
        supplier_invoice_ref: '',
        paid_amount: '0.000',
        discount_amount: '0.000',
        payment_method: 'cash',
        notes: '',
        items: [newLine()],
    });

    const subtotal = computed(() => {
        return form.items.reduce((sum, item) => {
            const q = parseFloat(item.quantity) || 0;
            const c = parseFloat(item.cost_price) || 0;
            return sum + q * c;
        }, 0);
    });

    const discount = computed(() => parseFloat(form.discount_amount) || 0);
    const paid = computed(() => parseFloat(form.paid_amount) || 0);
    const netTotal = computed(() => Math.max(0, subtotal.value - discount.value));
    const remaining = computed(() => Math.max(0, netTotal.value - paid.value));

    const addItemLine = () => {
        form.items.push(newLine());
    };

    const removeItemLine = (idx) => {
        if (form.items.length > 1) {
            form.items.splice(idx, 1);
        }
    };

    const onItemSelect = (line, option) => {
        line.item_option = option;
        line.cost_price = option?.raw?.cost_price?.toString() || '0.000';
    };

    const hydratePrefill = async () => {
        if (!route.query.prefill) return;
        isLoading.value = true;
        clearError();
        try {
            let prefilled = [];
            try {
                prefilled = JSON.parse(route.query.prefill);
            } catch {
                // Malformed prefill param: start with an empty basket.
            }
            if (!Array.isArray(prefilled) || prefilled.length === 0) return;

            // Item labels are not part of the prefill payload: read them by id so the picker shows names.
            const options = await Promise.all(
                prefilled.map(async (p) => {
                    const id = p.item_id || p.id;
                    return (await fetchItemOption(id)) || { value: id, label: p.name || `#${id}`, raw: p };
                })
            );
            form.items = prefilled.map((p, idx) => ({
                item_id: p.item_id || p.id,
                item_option: options[idx],
                quantity: prefillQuantity(p),
                cost_price: normalize(p.cost_price ?? '0'),
            }));
        } catch (err) {
            setError(err);
        } finally {
            isLoading.value = false;
        }
    };

    const submitPurchase = async () => {
        if (!form.supplier_id) {
            Swal.fire({ icon: 'warning', title: t('common.warning'), text: t('purchases.select_supplier_warning') });
            return;
        }

        const invalidLine = form.items.find((it) => !it.item_id || parseFloat(it.quantity) <= 0);
        if (invalidLine) {
            Swal.fire({ icon: 'warning', title: t('common.warning'), text: t('purchases.invalid_line_warning') });
            return;
        }

        isSubmitting.value = true;
        try {
            const payload = {
                ...form,
                items: form.items.map((it) => ({
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

    onMounted(hydratePrefill);

    return {
        isLoading,
        error,
        errorMessage,
        retry: hydratePrefill,
        form,
        searchSuppliers,
        searchItems,
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
