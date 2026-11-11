import { escapeHtml } from './escapeHtml.js';

/**
 * Thermal receipt markup. Every interpolated value is escaped: item names, company name and
 * invoice numbers are user-controlled and the string is rendered as HTML by the printer bridge.
 */
export function buildThermalHtml(inv, { companyName, currencyLabel, t, formatDate, formatDateTime }) {
    const itemsRows = (inv.items || [])
        .map(
            (item) => `
        <tr>
          <td style="text-align: right; padding: 2px 0;">${escapeHtml(item.item_name || item.name)}</td>
          <td style="text-align: center; padding: 2px 0;">${escapeHtml(parseFloat(item.quantity))}</td>
          <td style="text-align: left; padding: 2px 0;">${escapeHtml(parseFloat(item.total_price || 0).toFixed(2))}</td>
        </tr>
      `
        )
        .join('');

    const dateText = inv.invoice_date ? formatDate(inv.invoice_date) : formatDateTime(new Date());
    const netTotal = parseFloat(inv.net_total || inv.net_amount || 0).toFixed(2);

    return `
        <div style="font-family: sans-serif; font-size: 11px; text-align: center;">
          <h2 style="margin: 0 0 4px 0; font-size: 14px;">${escapeHtml(companyName)}</h2>
          <p style="margin: 0; font-size: 10px;">${escapeHtml(t('pos.thermal.invoice_number', { number: inv.invoice_number }))}</p>
          <p style="margin: 2px 0; font-size: 9px; color: #555;">${escapeHtml(dateText)}</p>
          <div style="border-top: 1px dashed #000; margin: 4px 0;"></div>
          <table style="width: 100%; font-size: 10px; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 1px solid #000;">
                <th style="text-align: right; padding-bottom: 2px;">${escapeHtml(t('pos.thermal.col_item'))}</th>
                <th style="text-align: center; padding-bottom: 2px;">${escapeHtml(t('pos.thermal.col_qty'))}</th>
                <th style="text-align: left; padding-bottom: 2px;">${escapeHtml(t('pos.thermal.col_total'))}</th>
              </tr>
            </thead>
            <tbody>
              ${itemsRows}
            </tbody>
          </table>
          <div style="border-top: 1px dashed #000; margin: 4px 0;"></div>
          <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: bold; margin: 4px 0;">
            <span>${escapeHtml(t('pos.thermal.net_total'))}</span>
            <span>${escapeHtml(netTotal)} ${escapeHtml(currencyLabel)}</span>
          </div>
          <div style="border-top: 1px dashed #000; margin: 4px 0;"></div>
          <p style="margin: 4px 0; font-size: 9px;">${escapeHtml(t('pos.thermal.thank_you'))}</p>
        </div>
      `;
}
