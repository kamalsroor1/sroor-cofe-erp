import api from '../Services/api';

export const REMOTE_PAGE_SIZE = 20;

const phoneSuffix = (row) => (row.phone ? ` (${row.phone})` : '');

const MAPPERS = {
    items: (row) => ({
        value: row.id,
        label: `${row.code ? `${row.name} (${row.code})` : row.name}${row.unit ? ` - ${row.unit}` : ''}`,
        raw: row,
    }),
    customers: (row) => ({ value: row.id, label: `${row.name}${phoneSuffix(row)}`, raw: row }),
    suppliers: (row) => ({
        value: row.id,
        label: row.company_name ? `${row.name} (${row.company_name})` : `${row.name}${phoneSuffix(row)}`,
        raw: row,
    }),
};

/**
 * Server-side search for pick-lists (BaseSelect `search-fn`). Never preload a whole table:
 * each keystroke (debounced by BaseSelect) asks the API for the first page of matches.
 *
 * @param {'items'|'customers'|'suppliers'} resource  API index endpoint, e.g. /items
 * @param {{ params?: object, map?: Function }} [options]  extra query params / custom option mapper
 */
export function useRemoteOptions(resource, { params = {}, map = MAPPERS[resource] } = {}) {
    const searchFn = async (query = '', { signal } = {}) => {
        const res = await api.get(`/${resource}`, {
            params: { ...params, search: query.trim() || undefined, per_page: REMOTE_PAGE_SIZE },
            signal,
        });
        return (res.data?.data || []).map(map);
    };

    /** Map one already-loaded record to an option, e.g. to show a prefilled selection. */
    const toOption = (row) => map(row);

    /** Load one record by id (prefill flows) and return it as an option, or null if it can't be read. */
    const fetchOption = async (id) => {
        try {
            const res = await api.get(`/${resource}/${id}`);
            return res.data?.data ? map(res.data.data) : null;
        } catch {
            return null;
        }
    };

    return { searchFn, toOption, fetchOption };
}
