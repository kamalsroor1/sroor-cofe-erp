import { ref, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import centralApi from '../Services/centralApi';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';
import { useTrans } from './useTrans';

/**
 * Data of the printable subscription invoice (GET /super-admin/billing-invoices/{id}) plus the platform
 * identity for the letterhead (GET /super-admin/platform-settings, only for operators who may view it;
 * otherwise the sheet falls back to the console title).
 */
export function useBillingInvoicePrint() {
    const { t } = useTrans();
    const route = useRoute();
    const centralAuth = useCentralAuthStore();

    const invoice = ref(null);
    const brand = ref({ name: '', legalName: '', logo: '' });
    const isLoading = ref(true);
    const loadError = ref('');

    const loadBrand = async () => {
        if (!centralAuth.can(CENTRAL_PERMISSIONS.settingsView)) return;
        try {
            const response = await centralApi.get('/super-admin/platform-settings', { silent: true });
            const data = response.data?.data || {};
            brand.value = {
                name: data.name || '',
                legalName: data.legal_name || '',
                logo: data.assets?.logo_light?.url || '',
            };
        } catch {
            // the letterhead is optional; the invoice itself is what matters
        }
    };

    const fetchInvoice = async () => {
        isLoading.value = true;
        loadError.value = '';
        try {
            const [response] = await Promise.all([
                centralApi.get(`/super-admin/billing-invoices/${route.params.id}`),
                loadBrand(),
            ]);
            invoice.value = response.data?.data || null;
            if (invoice.value && route.query.print === '1') nextTick(() => window.print());
        } catch (error) {
            loadError.value = error.userMessage || t('billing.console.print.load_failed');
        } finally {
            isLoading.value = false;
        }
    };

    const printPage = () => window.print();

    onMounted(fetchInvoice);

    return { invoice, brand, isLoading, loadError, fetchInvoice, printPage };
}
