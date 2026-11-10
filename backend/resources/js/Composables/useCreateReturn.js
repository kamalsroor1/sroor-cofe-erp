import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useReturnSource } from './useReturnSource';
import { useRouter } from 'vue-router';
import api from '../Services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';
import { useRemoteOptions } from './useRemoteOptions';
import { useUnits } from './useUnits';
import { businessToday } from './useBusinessDate';
import { useRefundMethod } from './useRefundMethod';

const defaultUnitPrice = (type, item) =>
    type === 'sales_return'
        ? parseFloat(item.price_retail || item.selling_price) || 0
        : parseFloat(item.cost_price) || 0;

export function useCreateReturn() {
    const { unitLabel } = useUnits();
    const { t } = useTrans();
    const router = useRouter();

    const authStore = useAuthStore();
    // BE-01: a normal return references an invoice/purchase; the manual form needs this permission.
    const canWithoutInvoice = computed(() => authStore.can('returns.without_invoice'));
    const manualMode = ref(false);

    const { searchFn: searchCustomers } = useRemoteOptions('customers');
    const { searchFn: searchSuppliers } = useRemoteOptions('suppliers');
    const { searchFn: searchItems } = useRemoteOptions('items');

    const isSubmitting = ref(false);
    const selectedItemToAdd = ref(null);

    const form = reactive({
        return_type: 'sales_return',
        customer_id: null,
        supplier_id: null,
        return_date: businessToday(),
        reason: '',
        items: [],
    });

    const returnType = computed(() => form.return_type);
    const source = useReturnSource(returnType);
    const usingSource = computed(() => !manualMode.value);
    const isSalesReturn = computed(() => form.return_type === 'sales_return');
    const refundParty = computed(() => {
        if (!isSalesReturn.value) return null;
        return (usingSource.value ? source.selected.value?.party_id : form.customer_id) || null;
    });
    const refund = useRefundMethod({
        source: computed(() => (isSalesReturn.value && usingSource.value ? source.selected.value : null)),
        total: source.total,
        partyId: refundParty,
    });

    const itemsCount = computed(() => (usingSource.value ? source.chosenLines.value.length : form.items.length));

    const manualTotal = computed(() => {
        return form.items.reduce(
            (sum, it) => sum + (parseFloat(it.quantity) || 0) * (parseFloat(it.unit_price) || 0),
            0
        );
    });

    const netTotal = computed(() => (usingSource.value ? parseFloat(source.total.value) || 0 : manualTotal.value));

    const setManualMode = (value) => {
        manualMode.value = value && canWithoutInvoice.value;
        source.clearSource();
    };

    const onTypeChange = (type) => {
        form.return_type = type;
        source.clearSource();
        source.loadSources();
        form.items.forEach((line) => {
            line.unit_price = defaultUnitPrice(type, line.catalog);
        });
    };

    const updateField = (field, val) => {
        form[field] = val;
    };

    const addItemRow = () => {
        if (!selectedItemToAdd.value) return;
        const it = selectedItemToAdd.value;

        if (form.items.some((i) => i.item_id === it.id)) {
            Swal.fire({ icon: 'info', title: t('common.warning'), text: t('returns.item_already_in_return') });
            return;
        }

        form.items.push({
            item_id: it.id,
            name: it.name,
            unit: unitLabel(it.unit),
            quantity: 1,
            unit_price: defaultUnitPrice(form.return_type, it),
            catalog: { price_retail: it.price_retail, selling_price: it.selling_price, cost_price: it.cost_price },
        });

        selectedItemToAdd.value = null;
    };

    const removeItemRow = (idx) => {
        form.items.splice(idx, 1);
    };

    const submitReturn = async () => {
        if (!usingSource.value) {
            const isSalesType = form.return_type === 'sales_return';
            if (isSalesType ? !form.customer_id : !form.supplier_id) {
                Swal.fire({
                    icon: 'warning',
                    title: t('common.warning'),
                    text: t(isSalesType ? 'returns.select_customer_warning' : 'returns.select_supplier_warning'),
                });
                return;
            }
        }

        if (itemsCount.value === 0) {
            Swal.fire({ icon: 'warning', title: t('common.warning'), text: t('returns.add_at_least_one_item') });
            return;
        }

        isSubmitting.value = true;
        try {
            const isSales = form.return_type === 'sales_return';
            const payload = {
                return_type: form.return_type,
                customer_id: isSales ? (usingSource.value ? source.selected.value?.party_id : form.customer_id) : null,
                supplier_id: !isSales ? (usingSource.value ? source.selected.value?.party_id : form.supplier_id) : null,
                return_date: form.return_date,
                reason: form.reason || null,
                items: usingSource.value
                    ? source.payloadItems()
                    : form.items.map((it) => ({
                          item_id: it.item_id,
                          quantity: parseFloat(it.quantity),
                          unit_price: parseFloat(it.unit_price),
                      })),
            };
            if (isSales) payload.refund_method = refund.method.value;
            if (usingSource.value) {
                payload[isSales ? 'invoice_id' : 'purchase_id'] = source.selected.value?.id;
            }

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
            if (refund.handleCapError(error)) return;
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: error.response?.data?.message || t('returns.save_return_failed'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    onMounted(source.loadSources);

    return {
        searchCustomers,
        searchSuppliers,
        searchItems,
        isSubmitting,
        selectedItemToAdd,
        form,
        refund,
        isSalesReturn,
        netTotal,
        itemsCount,
        canWithoutInvoice,
        manualMode,
        usingSource,
        source,
        setManualMode,
        onTypeChange,
        updateField,
        addItemRow,
        removeItemRow,
        submitReturn,
    };
}
