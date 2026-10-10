/**
 * Platform hosts, as rendered by the server into the SPA shell (resources/views/app.blade.php):
 *
 *   <meta name="central-domains" content="a.com,b.test">  every page (config tenancy.central_domains,
 *                                                          admin hosts excluded)
 *   <meta name="admin-domains" content="admin.a.com">      platform console only (config central.admin_domains)
 *
 * The SPA never hardcodes a platform domain: production (baraa-solutions.com) and the local
 * setup (sroor.test) differ only by server configuration.
 */

/** Loopback hosts: development servers, never a public workspace hub. */
const LOOPBACK_HOSTS = Object.freeze(['localhost', '127.0.0.1', '::1', '[::1]']);

/** "a.com, B.test ,," => ['a.com', 'b.test'] */
export function parseHostList(value) {
    if (typeof value !== 'string') return [];

    return [
        ...new Set(
            value
                .split(',')
                .map((host) => host.trim().toLowerCase())
                .filter(Boolean)
        ),
    ];
}

function readMetaHosts(name, doc) {
    if (!doc || typeof doc.querySelector !== 'function') return [];

    return parseHostList(doc.querySelector(`meta[name="${name}"]`)?.getAttribute('content'));
}

function currentHost() {
    return typeof window !== 'undefined' ? window.location.hostname : '';
}

/** Central (workspace-hub) hosts configured on the server. */
export function getCentralDomains(doc = globalThis.document) {
    return readMetaHosts('central-domains', doc);
}

/** Platform-console hosts (only rendered on the console itself). */
export function getAdminDomains(doc = globalThis.document) {
    return readMetaHosts('admin-domains', doc);
}

/** Is `host` one of the server's central hosts? */
export function isCentralHost(host = currentHost(), doc = globalThis.document) {
    const normalized = String(host ?? '')
        .trim()
        .toLowerCase();

    return normalized !== '' && getCentralDomains(doc).includes(normalized);
}

/** Is `host` a loopback development host? */
export function isLoopbackHost(host = currentHost()) {
    return LOOPBACK_HOSTS.includes(
        String(host ?? '')
            .trim()
            .toLowerCase()
    );
}
