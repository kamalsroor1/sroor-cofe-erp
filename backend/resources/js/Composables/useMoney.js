import { formatMoneyAmount } from '../helpers/formatters';
import { readDisplayContext } from './useFormatters';

const QTY_DECIMALS = 2;

/**
 * Money and quantity display (SETG-13): half-up through helpers/decimal.js, the
 * tenant currency decimals from /system/context, Western digits. No floats.
 */
export function useMoney() {
    const formatMoney = (amount, decimals = null) => {
        if (decimals !== null) {
            return formatMoneyAmount(amount, { decimals, fixed: true });
        }
        return formatMoneyAmount(amount, { decimals: readDisplayContext().currencyDecimals });
    };

    const formatQty = (qty, decimals = null) => {
        if (decimals !== null) {
            return formatMoneyAmount(qty, { decimals, fixed: true });
        }
        return formatMoneyAmount(qty, { decimals: QTY_DECIMALS });
    };

    return {
        formatMoney,
        formatQty,
    };
}
