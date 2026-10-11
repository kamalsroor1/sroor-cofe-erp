import { getActivePinia } from 'pinia';
import { useAppConfigStore } from '../stores/appConfig';
import { dRoundTo } from '../helpers/decimal';
import {
    formatDate as formatDateValue,
    formatDateTime as formatDateTimeValue,
    formatMoneyAmount,
    formatNumber as formatNumberValue,
    formatTime as formatTimeValue,
    resolveCurrencyDecimals,
} from '../helpers/formatters';

/**
 * Tenant display context from /system/context: UI locale, tenant clock and currency
 * decimals. Read on every call so templates re-render when the store changes.
 */
export function readDisplayContext() {
    if (!getActivePinia()) {
        return { locale: undefined, timeZone: undefined, currencyDecimals: 2 };
    }
    const store = useAppConfigStore();
    return {
        locale: store.locale,
        timeZone: store.system?.timezone,
        currencyDecimals: resolveCurrencyDecimals(store.system?.currency_decimals),
    };
}

const QTY_DECIMALS = 2;

export function useFormatters() {
    const withContext = (options) => {
        const { locale, timeZone } = readDisplayContext();
        return { locale, timeZone, ...(options || {}) };
    };

    /** Money with the tenant currency decimals (SETG-13); `forceDecimals` always shows that many. */
    const formatMoney = (val, forceDecimals = null) => {
        if (forceDecimals !== null) {
            return formatMoneyAmount(val, { decimals: forceDecimals, fixed: true });
        }
        return formatMoneyAmount(val, { decimals: readDisplayContext().currencyDecimals });
    };

    const formatQty = (val) => formatMoneyAmount(val, { decimals: QTY_DECIMALS });

    const formatPercent = (val, decimals = 2) => `${dRoundTo(val, decimals)}%`;

    const formatDate = (value, options = {}) => formatDateValue(value, withContext(options));
    const formatTime = (value, options = {}) => formatTimeValue(value, withContext(options));
    const formatDateTime = (value, options = {}) => formatDateTimeValue(value, withContext(options));
    const formatNumber = (value, options = {}) =>
        formatNumberValue(value, { locale: readDisplayContext().locale, ...(options || {}) });

    return { formatMoney, formatQty, formatPercent, formatDate, formatTime, formatDateTime, formatNumber };
}
