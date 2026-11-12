import { useFormatters } from './useFormatters';
import { useTrans } from './useTrans';

/**
 * Display helpers for "net of returns" figures. The server owns every total;
 * this only decides what to show (money values are decimal strings, so the
 * zero check never does arithmetic on them).
 */
export function useReturnsDisplay() {
    const { t } = useTrans();
    const { formatMoney, formatQty } = useFormatters();

    const hasAmount = (value) => value !== null && value !== undefined && Number(value) !== 0;

    const moneyDetail = (gross, returns) =>
        hasAmount(returns)
            ? t('reports.gross_minus_returns', { gross: formatMoney(gross), returns: formatMoney(returns) })
            : '';

    const qtyDetail = (returnedQty, unit = '') =>
        hasAmount(returnedQty)
            ? t('reports.returned_qty_detail', { qty: `${formatQty(returnedQty)} ${unit}`.trim() })
            : '';

    return { hasAmount, moneyDetail, qtyDetail };
}
