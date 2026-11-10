import { ref, computed } from 'vue';
import centralApi from '../Services/centralApi';
import { notifyError, notifySuccess, notifyWarning } from '../helpers/alert';
import { useTrans } from './useTrans';

/**
 * "Issue invoice" form of the tenant billing section:
 *   POST /super-admin/tenants/{id}/billing-invoices -> 201; 409 billing.invoice_duplicate (details.invoice_number);
 *   422 errors keyed like service_lines.0.amount. Plans come from GET /super-admin/plans.
 * Nothing is calculated here: the server owns subtotal, tax and total.
 */

export const ISSUE_TYPES = Object.freeze(['plan', 'renewal', 'upgrade', 'service']);
export const ISSUE_CYCLES = Object.freeze(['monthly', 'yearly']);
export const MAX_SERVICE_LINES = 10;

const emptyLine = () => ({ description: '', amount: '' });

const emptyForm = () => ({
    type: 'plan',
    plan_id: null,
    billing_cycle: 'monthly',
    use_founder_price: false,
    discount: '',
    notes: '',
    service_lines: [emptyLine()],
});

export function useIssueInvoice(tenantId, onIssued) {
    const { t } = useTrans();

    const isOpen = ref(false);
    const isSubmitting = ref(false);
    const form = ref(emptyForm());
    const errors = ref({});
    const plans = ref([]);
    const plansLoading = ref(false);

    const isService = computed(() => form.value.type === 'service');
    const canAddLine = computed(() => form.value.service_lines.length < MAX_SERVICE_LINES);

    const loadPlans = async () => {
        if (plans.value.length || plansLoading.value) return;
        plansLoading.value = true;
        try {
            const response = await centralApi.get('/super-admin/plans');
            plans.value = response.data?.data?.plans || response.data?.plans || [];
        } catch (error) {
            notifyError(t('billing.console.issue.failed'), error.userMessage || '');
        } finally {
            plansLoading.value = false;
        }
    };

    const open = () => {
        form.value = emptyForm();
        errors.value = {};
        isOpen.value = true;
        loadPlans();
    };

    const close = () => {
        if (isSubmitting.value) return;
        isOpen.value = false;
    };

    const setField = (key, value) => {
        form.value = { ...form.value, [key]: value };
        if (errors.value[key]) {
            const next = { ...errors.value };
            delete next[key];
            errors.value = next;
        }
    };

    const addLine = () => {
        if (canAddLine.value) form.value.service_lines.push(emptyLine());
    };

    const setLine = (index, key, value) => {
        form.value.service_lines[index] = { ...form.value.service_lines[index], [key]: value };
        const errorKey = `service_lines.${index}.${key}`;
        if (errors.value[errorKey]) {
            const next = { ...errors.value };
            delete next[errorKey];
            errors.value = next;
        }
    };

    const removeLine = (index) => {
        form.value.service_lines.splice(index, 1);
        if (!form.value.service_lines.length) form.value.service_lines.push(emptyLine());
    };

    const buildPayload = () => {
        const current = form.value;
        const payload = { type: current.type };
        if (current.type === 'service') {
            payload.service_lines = current.service_lines.map((line) => ({
                description: line.description.trim(),
                amount: String(line.amount).trim(),
            }));
        } else {
            payload.plan_id = current.plan_id;
            payload.billing_cycle = current.billing_cycle;
            payload.use_founder_price = current.use_founder_price === true;
        }
        const discount = String(current.discount ?? '').trim();
        if (discount !== '') payload.discount = discount;
        const notes = current.notes.trim();
        if (notes !== '') payload.notes = notes;
        return payload;
    };

    const submit = async () => {
        if (isSubmitting.value) return;
        isSubmitting.value = true;
        errors.value = {};
        try {
            const response = await centralApi.post(`/super-admin/tenants/${tenantId}/billing-invoices`, buildPayload());
            notifySuccess(response.data?.message || '');
            isOpen.value = false;
            await onIssued?.(response.data?.data);
        } catch (error) {
            const data = error.response?.data;
            if (error.stepUpCancelled) {
                notifyWarning(error.userMessage);
            } else if (error.response?.status === 409 && data?.error_code === 'billing.invoice_duplicate') {
                notifyWarning(
                    t('billing.console.issue.duplicate', { number: data?.details?.invoice_number || '' }),
                    ''
                );
            } else if (error.response?.status === 422 && data?.errors) {
                errors.value = data.errors;
                notifyError(t('billing.console.issue.failed'), error.userMessage || '');
            } else {
                notifyError(t('billing.console.issue.failed'), error.userMessage || '');
            }
        } finally {
            isSubmitting.value = false;
        }
    };

    return {
        isOpen,
        isSubmitting,
        form,
        errors,
        plans,
        plansLoading,
        isService,
        canAddLine,
        open,
        close,
        setField,
        addLine,
        setLine,
        removeLine,
        submit,
    };
}
