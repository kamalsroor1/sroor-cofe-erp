import { ref, computed, onMounted } from 'vue';
import centralApi from '../Services/centralApi';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';
import { confirmDialog, notifyError, notifySuccess, notifyWarning } from '../helpers/alert';
import { useTrans } from './useTrans';

/**
 * Billing of one tenant in the console (Api\Central\TenantBillingInvoiceController, BillingInvoiceController):
 *   GET  /super-admin/tenants/{id}/billing-invoices   -> data[] invoices + top-level `subscription`
 *   POST /super-admin/billing-invoices/{id}/void
 * Issuing lives in useIssueInvoice. Step-up on writes is handled by centralApi.
 */

const DAY_MS = 86_400_000;

export function useTenantBilling(tenantId) {
    const { t } = useTrans();
    const centralAuth = useCentralAuthStore();

    const invoices = ref([]);
    const subscription = ref(null);
    const isLoading = ref(true);
    const loadError = ref('');
    const voidingId = ref(null);

    const canView = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.billingView));
    const canManage = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.billingManage));

    /** Whole days until the subscription ends (negative once ended); null without an end date. */
    const daysLeft = computed(() => {
        const endsAt = subscription.value?.ends_at || subscription.value?.subscription_ends_at;
        if (!endsAt) return null;
        return Math.ceil((new Date(endsAt).getTime() - Date.now()) / DAY_MS);
    });

    const fetchBilling = async () => {
        if (!canView.value) return;
        isLoading.value = true;
        loadError.value = '';
        try {
            const response = await centralApi.get(`/super-admin/tenants/${tenantId}/billing-invoices`);
            invoices.value = response.data?.data || [];
            subscription.value = response.data?.subscription || null;
        } catch (error) {
            loadError.value = error.userMessage || t('billing.console.tenant.load_failed');
        } finally {
            isLoading.value = false;
        }
    };

    const voidInvoice = async (invoice) => {
        if (!canManage.value || voidingId.value) return;
        const confirmed = await confirmDialog({
            title: t('billing.console.tenant.void_title', { number: invoice.number }),
            text: t('billing.console.tenant.void_text'),
            confirmButtonText: t('billing.console.tenant.void_confirm'),
            isDanger: true,
        });
        if (!confirmed) return;
        voidingId.value = invoice.id;
        try {
            const response = await centralApi.post(`/super-admin/billing-invoices/${invoice.id}/void`);
            notifySuccess(response.data?.message || '');
            await fetchBilling();
        } catch (error) {
            if (error.stepUpCancelled) notifyWarning(error.userMessage);
            else notifyError(t('billing.console.tenant.void_failed'), error.userMessage || '');
        } finally {
            voidingId.value = null;
        }
    };

    onMounted(fetchBilling);

    return {
        invoices,
        subscription,
        isLoading,
        loadError,
        voidingId,
        canView,
        canManage,
        daysLeft,
        fetchBilling,
        voidInvoice,
    };
}
