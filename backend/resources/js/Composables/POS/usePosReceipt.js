import api from '../../Services/api';
import { trans } from '../../helpers/trans';
import { buildThermalHtml as buildReceiptHtml } from '../../helpers/posReceiptHtml';
import { currentCurrencyLabel } from '../useFormatters';

/**
 * Prints the last created invoice: silent thermal print on desktop, browser print page otherwise.
 */
export function usePosReceipt({
    lastCreatedInvoice,
    paymentType,
    isDesktop,
    printThermalReceipt,
    openCashDrawer,
    playDrawerSound,
    appConfigStore,
    formatDate,
    formatDateTime,
}) {
    const buildThermalHtml = (inv) =>
        buildReceiptHtml(inv, {
            companyName: appConfigStore.companyName || appConfigStore.platformName,
            currencyLabel: currentCurrencyLabel(),
            t: trans,
            formatDate,
            formatDateTime,
        });

    // SET-01: `copies` comes from the sale settings; a click handler passing an event means one copy.
    const printLastInvoice = async (copies = 1) => {
        if (!lastCreatedInvoice.value?.id) return;
        const count = Number.isInteger(copies) && copies >= 1 && copies <= 3 ? copies : 1;

        if (isDesktop.value) {
            try {
                const res = await api.get(`/invoices/${lastCreatedInvoice.value.id}`);
                const inv = res.data?.data || lastCreatedInvoice.value;

                const html = buildThermalHtml(inv);
                for (let i = 0; i < count; i++) await printThermalReceipt(html);
                if (inv.payment_type === 'cash' || paymentType.value === 'cash') {
                    playDrawerSound();
                    await openCashDrawer();
                }
                return;
            } catch (err) {
                console.warn('[DesktopPOS] Silent print fallback to browser popup:', err);
            }
        }

        // Fallback for Web Browser
        for (let i = 0; i < count; i++) {
            window.open(`/invoices/${lastCreatedInvoice.value.id}/print?autoprint=true`, '_blank', 'width=800,height=600');
        }
    };

    // Reprint of any past invoice: never kicks the cash drawer (no money moves).
    const reprintInvoice = async (invoiceId) => {
        if (!invoiceId) return;

        if (isDesktop.value) {
            try {
                const res = await api.get(`/invoices/${invoiceId}`);
                await printThermalReceipt(buildThermalHtml(res.data?.data || {}));
                return;
            } catch (err) {
                console.warn('[DesktopPOS] Silent reprint fallback to browser popup:', err);
            }
        }

        window.open(`/invoices/${invoiceId}/print?autoprint=true`, '_blank', 'width=800,height=600');
    };

    return { printLastInvoice, reprintInvoice };
}
