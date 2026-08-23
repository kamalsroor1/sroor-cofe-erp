export function useFormatters() {
    const formatMoney = (val) => {
        const num = parseFloat(val) || 0;
        return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    const formatQty = (val) => {
        const num = parseFloat(val) || 0;
        return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    const formatPercent = (val, decimals = 2) => {
        const num = parseFloat(val) || 0;
        return num.toFixed(decimals) + '%';
    };
    return { formatMoney, formatQty, formatPercent };
}