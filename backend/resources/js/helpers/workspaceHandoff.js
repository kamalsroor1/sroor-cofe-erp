/**
 * MOB-06: the Android app (and any browser) opens the platform hub (central host), asks for the
 * shop code, then hands over to the shop's own subdomain. The hub remembers the last shop so the
 * next launch goes straight there; "switch shop" comes back to the hub with ?switch=1.
 *
 * Pure helpers (no Vue, no window access unless passed in) so they are unit-testable.
 */
import { getCentralDomains, isLoopbackHost } from './platformHosts.js';

/** localStorage key on the hub origin holding the last shop picked on this device. */
export const LAST_SHOP_KEY = 'last_shop';

/** The first server-configured central host that is a real public host (never loopback). */
export function publicCentralHost(doc = globalThis.document) {
    return getCentralDomains(doc).find((host) => !isLoopbackHost(host)) ?? null;
}

/**
 * Login URL on the shop's own host, from the resolver's server_url. Only https (or a loopback dev
 * host) is accepted, and only an origin is used: never a path, credentials or a port smuggled in.
 */
export function shopLoginUrl(serverUrl) {
    let url;
    try {
        url = new URL(String(serverUrl ?? ''));
    } catch {
        return null;
    }

    const secure = url.protocol === 'https:' || (url.protocol === 'http:' && isLoopbackHost(url.hostname));
    if (!secure || url.username || url.password) return null;

    return `${url.origin}/login`;
}

/** The hub's connect screen, used to switch shop from a shop subdomain. */
export function centralConnectUrl(doc = globalThis.document, { switchShop = true } = {}) {
    const host = publicCentralHost(doc);
    if (!host) return null;

    return `https://${host}/connect${switchShop ? '?switch=1' : ''}`;
}

/** Remember the shop picked on the hub (code + display name only, nothing sensitive). */
export function rememberShop(storage, tenant) {
    const code = String(tenant?.slug || tenant?.tenant_id || '').trim();
    if (!storage || !code) return;

    try {
        storage.setItem(LAST_SHOP_KEY, JSON.stringify({ code, name: String(tenant?.name ?? '') }));
    } catch {
        // storage full / blocked: remembering is a convenience only
    }
}

/** The last shop picked on this device, or null. */
export function readRememberedShop(storage) {
    if (!storage) return null;

    try {
        const parsed = JSON.parse(storage.getItem(LAST_SHOP_KEY) ?? 'null');
        const code = typeof parsed?.code === 'string' ? parsed.code.trim() : '';

        return code ? { code, name: typeof parsed.name === 'string' ? parsed.name : '' } : null;
    } catch {
        return null;
    }
}

/** Forget the remembered shop (e.g. it no longer resolves). */
export function forgetShop(storage) {
    try {
        storage?.removeItem(LAST_SHOP_KEY);
    } catch {
        // ignore
    }
}
