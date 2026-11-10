export const FAVORITES_LIMIT = 50;

/** GET /pos/items filters for a catalog tab (server-side) or a search term (the search ignores the tab). */
export function catalogParams(tab, search) {
    const term = (search || '').trim();
    if (term) return { search: term };
    if (tab === 'favorites') return { sort: 'pos_sales_count', per_page: FAVORITES_LIMIT };
    if (tab === 'newest') return { sort: 'newest' };
    if (tab === 'in_stock') return { only_in_stock: 1 };
    if (tab === 'low_stock') return { only_low_stock: 1 };
    if (tab === null || tab === undefined) return {};
    return { category_id: tab };
}

/** Items after a page arrives: page 1 replaces the list, later pages append rows not already shown. */
export function mergePage(current, rows, page) {
    if (page === 1) return rows;
    const known = new Set(current.map((item) => item.id));
    return [...current, ...rows.filter((item) => !known.has(item.id))];
}
