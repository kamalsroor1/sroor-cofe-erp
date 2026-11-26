import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

/**
 * Which invoice screen is open on /billing: the pay / status screen (pending invoices) or the read-only
 * details. `?invoice=<id>` (the temporary-access banner link) opens an invoice once the list has loaded.
 * @param {import('vue').Ref<Array>} invoices
 */
export function useBillingInvoiceScreens(invoices) {
    const route = useRoute();
    const router = useRouter();
    const detailsId = ref(null);
    const payId = ref(null);

    const detailsInvoice = computed(() => invoices.value.find((i) => i.id === detailsId.value) || null);
    const payInvoice = computed(() => invoices.value.find((i) => i.id === payId.value) || null);

    const open = (invoice) => {
        if (invoice.status.value === 'pending') payId.value = invoice.id;
        else detailsId.value = invoice.id;
    };

    const openById = (id) => {
        const invoice = invoices.value.find((i) => i.id === id);
        if (invoice) open(invoice);
    };

    const openDetails = (invoice) => {
        payId.value = null;
        detailsId.value = invoice.id;
    };

    const openPay = (invoice) => {
        detailsId.value = null;
        payId.value = invoice.id;
    };

    const openFromQuery = () => {
        const id = Number(route.query.invoice);
        if (!id) return;
        openById(id);
        router.replace({ query: { ...route.query, invoice: undefined } });
    };

    return { detailsId, payId, detailsInvoice, payInvoice, openById, openDetails, openPay, openFromQuery };
}
