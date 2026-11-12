// ROLE-01: one rule for launcher tiles and quick actions. An entry is shown only when the user may open the
// screen it points to (`to`) and every extra screen it depends on (`also`); `canAccessPath` is the route-table
// check from useRoutePermission, so the sidebar, the tiles and the router guard can never drift apart.

/** Paths an entry needs, in order. */
export const requiredPaths = (entry) => [entry.to, ...(entry.also || [])].filter((path) => typeof path === 'string');

/** Entries the user may open. */
export function filterAccessible(entries, canAccessPath) {
    return entries.filter((entry) => requiredPaths(entry).every((path) => canAccessPath(path)));
}
