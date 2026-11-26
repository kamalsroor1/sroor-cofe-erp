// Row number for the "#" column: robust to missing/partial pagination meta.
const positive = (v, fallback) => {
    const n = Number(v);
    return Number.isFinite(n) && n >= 1 ? Math.floor(n) : fallback;
};

export function rowNumber(index, pagination) {
    const i = Number.isFinite(Number(index)) ? Number(index) : 0;
    const page = positive(pagination?.current_page ?? pagination?.currentPage, 1);
    const perPage = positive(pagination?.per_page ?? pagination?.perPage, 0);
    return i + 1 + (page - 1) * perPage;
}
