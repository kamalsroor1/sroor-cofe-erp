'use strict';

// Origin policy for every URL the Electron main process loads into a window
// that has the preload bridge. Pure module: must not require electron so it
// can be unit-tested with node:test.

const APP_BASE_DOMAIN = 'baraa-solutions.com';
const CENTRAL_ORIGIN = `https://${APP_BASE_DOMAIN}`;
const CONNECT_URL = `${CENTRAL_ORIGIN}/connect`;
const FALLBACK_TENANT_URL = `https://2m.${APP_BASE_DOMAIN}`;

const SLUG_PATTERN = /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/;
const KEPT_PATH_PATTERN = /^\/(?:connect|login|workspace)\/?$/;
const DEV_HOSTS = new Set(['localhost', '127.0.0.1']);
const DEV_SUFFIX = '.sroor.test';

function normalizeTenantSlug(value) {
    if (typeof value !== 'string') return null;
    const slug = value.trim().toLowerCase();
    return SLUG_PATTERN.test(slug) ? slug : null;
}

function parseUrl(value) {
    if (typeof value !== 'string' || value === '') return null;
    try {
        return new URL(value);
    } catch {
        return null;
    }
}

function isDevUrl(url) {
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return false;
    if (url.username || url.password) return false;
    return DEV_HOSTS.has(url.hostname) || url.hostname.endsWith(DEV_SUFFIX);
}

function isAppHostname(hostname) {
    if (hostname === APP_BASE_DOMAIN || hostname === `www.${APP_BASE_DOMAIN}`) return true;
    const suffix = `.${APP_BASE_DOMAIN}`;
    if (!hostname.endsWith(suffix)) return false;
    const label = hostname.slice(0, -suffix.length);
    return SLUG_PATTERN.test(label);
}

// The optional second argument may be an options object ({ allowDev }).
// Any other value (e.g. a tenant code) is ignored.
function isAllowedAppUrl(value, options) {
    const url = parseUrl(value);
    if (!url) return false;

    const allowDev = Boolean(options && typeof options === 'object' && options.allowDev);
    if (allowDev && isDevUrl(url)) return true;

    if (url.protocol !== 'https:') return false;
    if (url.username !== '' || url.password !== '') return false;
    if (url.port !== '' && url.port !== '443') return false;

    return isAppHostname(url.hostname);
}

function buildTenantOrigin(slug) {
    const normalized = normalizeTenantSlug(slug);
    if (!normalized) return null;
    const origin = new URL(`https://${normalized}.${APP_BASE_DOMAIN}`).origin;
    return isAllowedAppUrl(origin) ? origin : null;
}

function sanitizeServerUrl(value, options) {
    if (!isAllowedAppUrl(value, options)) return null;
    const url = new URL(value);
    return KEPT_PATH_PATTERN.test(url.pathname) ? url.origin + url.pathname : url.origin;
}

// Origin to build menu/tray/ping URLs from; never returns an unvetted value.
function getSafeServerOrigin(rawServerUrl, options) {
    const sanitized = sanitizeServerUrl(rawServerUrl, options);
    return sanitized ? new URL(sanitized).origin : FALLBACK_TENANT_URL;
}

function parseDeepLink(value) {
    const url = parseUrl(value);
    if (!url || url.protocol !== 'sroor:') return null;

    const action = url.host || url.pathname.replace(/^\/+|\/+$/g, '');
    if (action.toLowerCase() !== 'connect') return null;

    const tenant = url.searchParams.has('tenant') ? url.searchParams.get('tenant') : url.searchParams.get('code');
    return normalizeTenantSlug(tenant);
}

module.exports = {
    APP_BASE_DOMAIN,
    CENTRAL_ORIGIN,
    CONNECT_URL,
    FALLBACK_TENANT_URL,
    normalizeTenantSlug,
    isAllowedAppUrl,
    buildTenantOrigin,
    sanitizeServerUrl,
    getSafeServerOrigin,
    parseDeepLink,
};
