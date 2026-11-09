import api from '../../Services/api';
import { trans } from '../../helpers/trans';

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
    const buildThermalHtml = (inv) => {
        const itemsRows = (inv.items || [])
            .map(
                (item) => `
        <tr>
          <td style="text-align: right; padding: 2px 0;">${item.item_name || item.name}</td>
          <td style="text-align: center; padding: 2px 0;">${parseFloat(item.quantity)}</td>
          <td style="text-align: left; padding: 2px 0;">${parseFloat(item.total_price || 0).toFixed(2)}</td>
        </tr>
      `
            )
            .join('');

        return `
        <div style="font-family: sans-serif; font-size: 11px; text-align: center;">
          <h2 style="margin: 0 0 4px 0; font-size: 14px;">${appConfigStore.companyName || appConfigStore.platformName}</h2>
          <p style="margin: 0; font-size: 10px;">${trans('pos.thermal.invoice_number', { number: inv.invoice_number })}</p>
          <p style="margin: 2px 0; font-size: 9px; color: #555;">${inv.invoice_date ? formatDate(inv.invoice_date) : formatDateTime(new Date())}</p>
          <div style="border-top: 1px dashed #000; margin: 4px 0;"></div>
          <table style="width: 100%; font-size: 10px; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 1px solid #000;">
                <th style="text-align: right; padding-bottom: 2px;">${trans('pos.thermal.col_item')}</th>
                <th style="text-align: center; padding-bottom: 2px;">${trans('pos.thermal.col_qty')}</th>
                <th style="text-align: left; padding-bottom: 2px;">${trans('pos.thermal.col_total')}</th>
              </tr>
            </thead>
            <tbody>
              ${itemsRows}
            </tbody>
          </table>
          <div style="border-top: 1px dashed #000; margin: 4px 0;"></div>
          <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: bold; margin: 4px 0;">
            <span>${trans('pos.thermal.net_total')}</span>
            <span>${parseFloat(inv.net_total || inv.net_amount || 0).toFixed(2)} ${trans('common.currency')}</span>
          </div>
          <div style="border-top: 1px dashed #000; margin: 4px 0;"></div>
          <p style="margin: 4px 0; font-size: 9px;">${trans('pos.thermal.thank_you')}</p>
        </div>
      `;
    };

    const printLastInvoice = async () => {
        if (!lastCreatedInvoice.value?.id) return;

        if (isDesktop.value) {
            try {
                const res = await api.get(`/invoices/${lastCreatedInvoice.value.id}`);
                const inv = res.data?.data || lastCreatedInvoice.value;

                await printThermalReceipt(buildThermalHtml(inv));
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
        window.open(`/invoices/${lastCreatedInvoice.value.id}/print?autoprint=true`, '_blank', 'width=800,height=600');
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
