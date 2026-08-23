/**
 * Composable for formatting currency and financial decimals
 */
export function useMoney() {
    const formatMoney = (amount, decimals = null) => {
        const num = Number(amount || 0);
        if (decimals !== null) {
            return num.toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            });
        }
        const hasFraction = (num % 1 !== 0);
        return num.toLocaleString('en-US', {
            minimumFractionDigits: hasFraction ? 2 : 0,
            maximumFractionDigits: hasFraction ? 2 : 0,
        });
    };

    const formatQty = (qty, decimals = null) => {
        const num = Number(qty || 0);
        if (decimals !== null) {
            return num.toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            });
        }
        const hasFraction = (num % 1 !== 0);
        return num.toLocaleString('en-US', {
            minimumFractionDigits: hasFraction ? 2 : 0,
            maximumFractionDigits: hasFraction ? 2 : 0,
        });
    };

    return {
        formatMoney,
        formatQty,
    };
}
