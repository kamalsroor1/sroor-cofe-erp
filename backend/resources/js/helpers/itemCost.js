// BUG-02: stock value and profit are computed on the item's weighted average cost.
// `cost_price` is the legacy key for the last purchase price. Both are null when the
// user lacks items.view_cost, so null must stay null and never turn into 0.
const hasValue = (v) => v !== null && v !== undefined && v !== '' && Number.isFinite(Number(v));

export const hasCostValue = hasValue;

/** Unit cost the valuation uses: average_cost, falling back to cost_price when it is empty or zero. */
export function effectiveAverageCost(item) {
    if (!item) return null;
    if (hasValue(item.average_cost) && Number(item.average_cost) !== 0) return item.average_cost;
    return hasValue(item.cost_price) ? item.cost_price : null;
}

/** Last purchase price, preferring the explicit key over the legacy cost_price. */
export function lastPurchasePrice(item) {
    if (!item) return null;
    if (hasValue(item.last_purchase_price)) return item.last_purchase_price;
    return hasValue(item.cost_price) ? item.cost_price : null;
}
