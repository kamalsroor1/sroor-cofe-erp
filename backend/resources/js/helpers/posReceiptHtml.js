import { escapeHtml } from './escapeHtml.js';
import { isPositive } from './decimal.js';
import { formatMoneyAmount, resolveCurrencyDecimals } from './formatters.js';

const QTY_DECIMALS = 3;

/**
 * SHOP-01: true when cash physically changed hands in this sale, so the cash drawer may open.
 * Uses the saved payments when the invoice carries them (split sales), else the header method.
 */
export function saleIncludesCash(inv) {
    if (!inv || inv.payment_type === 'credit') return false;
    // Change handed back means the drawer opens, even when the server dropped a cash line equal to the change.
    if (isPositive(inv.change_amount)) return true;
    const payments = Array.isArray(inv.payments) ? inv.payments : [];
    if (payments.length > 0) {
        return payments.some((p) => (p.payment_method || p.method) === 'cash' && isPositive(p.amount));
    }
    return inv.payment_method === 'cash' && isPositive(inv.paid_amount);
}

/**
 * Thermal receipt markup. Every interpolated value is escaped: item names, company name and
 * invoice numbers are user-controlled and the string is rendered as HTML by the printer bridge.
 * Amounts are the server's decimal strings formatted exactly (half-up, no float math).
 */
export function buildThermalHtml(inv, { companyName, currencyLabel, currencyDecimals, t, formatDate, formatDateTime }) {
    const decimals = resolveCurrencyDecimals(currencyDecimals);
    const money = (value) => formatMoneyAmount(value ?? '0', { decimals, fixed: true });
    const qty = (value) => formatMoneyAmount(value ?? '0', { decimals: QTY_DECIMALS });

    const itemsRows = (inv.items || [])
        .map(
            (item) => `
        <tr>
          <td style="text-align: right; padding: 2px 0;">${escapeHtml(item.item_name || item.name)}</td>
          <td style="text-align: center; padding: 2px 0;">${escapeHtml(qty(item.quantity))}</td>
          <td style="text-align: left; padding: 2px 0;">${escapeHtml(money(item.total_price))}</td>
        </tr>
      `
        )
        .join('');

    const dateText = inv.invoice_date ? formatDate(inv.invoice_date) : formatDateTime(new Date());
    const netTotal = money(inv.net_total ?? inv.net_amount);

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
