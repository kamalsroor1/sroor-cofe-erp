export function useFormatters() {
    const formatMoney = (val, forceDecimals = null) => {
        const num = parseFloat(val) || 0;
        if (forceDecimals !== null) {
            return num.toLocaleString('en-US', { minimumFractionDigits: forceDecimals, maximumFractionDigits: forceDecimals });
        }
        const hasFraction = (num % 1 !== 0);
        return num.toLocaleString('en-US', {
            minimumFractionDigits: hasFraction ? 2 : 0,
            maximumFractionDigits: hasFraction ? 2 : 0,
        });
    };
    const formatQty = (val) => {
        const num = parseFloat(val) || 0;
        const hasFraction = (num % 1 !== 0);
        return num.toLocaleString('en-US', {
            minimumFractionDigits: hasFraction ? 2 : 0,
            maximumFractionDigits: hasFraction ? 2 : 0,
        });
    };
    const formatPercent = (val, decimals = 2) => {
        const num = parseFloat(val) || 0;
        return num.toFixed(decimals) + '%';
    };
    return { formatMoney, formatQty, formatPercent };
}