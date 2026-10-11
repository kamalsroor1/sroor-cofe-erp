import axios from 'axios';
import { trans } from '../helpers/trans';
import { notifyWarning } from '../helpers/alert';
import { isNetworkError } from '../helpers/connectivity';

/**
 * HTTP client of the platform (super-admin) console (IDEN-1.9).
 *
 * Deliberately separate from the tenant client (Services/api.js):
 *  - never sends X-Tenant or X-Store-Id, and never reads the tenant token;
 *  - Bearer comes from its own storage key (CENTRAL_STORAGE_KEYS.token);
 *  - only talks to /api/v1/super-admin/* (anything else is a wiring bug and is refused);
 *  - 401 or a setup-scoped token on a full route => onUnauthenticated (back to central login);
 *  - 403 central_auth.step_up_required => onStepUpRequired (StepUpPrompt), then one retry.
 *
 * The session handlers are registered by the router (central mode) so this module stays
 * free of Pinia / router imports.
 */

export const CENTRAL_STORAGE_KEYS = Object.freeze({
    token: 'central_auth_token',
    user: 'central_auth_user',
    expiresAt: 'central_auth_expires_at',
    startedAt: 'central_auth_started_at',
});

export const CENTRAL_ERROR_CODES = Object.freeze({
    setupRequired: 'central_auth.two_factor_setup_required',
    stepUpRequired: 'central_auth.step_up_required',
    alreadyConfirmed: 'central_auth.two_factor_already_confirmed',
    notEnabled: 'central_auth.two_factor_not_enabled',
    passwordResetRequired: 'central_auth.password_reset_required',
});

const CENTRAL_PATH_PREFIX = '/super-admin/';

/**
 * True when the server rendered the SPA for the platform console
 * (<meta name="app-context" content="central"> on the admin host only).
 */
export function isCentralAppContext() {
    if (typeof document === 'undefined') return false;
    const meta = document.querySelector('meta[name="app-context"]');
    return meta?.getAttribute('content') === 'central';
}

let sessionHandlers = {
    onUnauthenticated: null,
    onStepUpRequired: null,
};

/**
 * @param {{ onUnauthenticated?: Function, onStepUpRequired?: () => Promise<void> }} handlers
 */
export function setCentralSessionHandlers(handlers) {
    sessionHandlers = { ...sessionHandlers, ...handlers };
}

/** Token kept in memory during the 2FA setup step only (never persisted). */
let transientToken = null;

export function setTransientCentralToken(token) {
    transientToken = token || null;
}

function currentToken() {
    if (transientToken) return transientToken;
    try {
        return localStorage.getItem(CENTRAL_STORAGE_KEYS.token);
    } catch {
        return null;
    }
}

function currentLocale() {
    try {
        return localStorage.getItem('app_locale') || 'ar';
    } catch {
        return 'ar';
    }
}

const centralApi = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
    timeout: 30000,
});

centralApi.interceptors.request.use((config) => {
    const url = String(config.url || '');
    if (!url.startsWith(CENTRAL_PATH_PREFIX)) {
        return Promise.reject(new Error(`centralApi only serves ${CENTRAL_PATH_PREFIX}* (got "${url}")`));
    }

    const headers = config.headers;
    const token = currentToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    } else if (typeof headers.delete === 'function') {
        headers.delete('Authorization');
    } else {
        delete headers.Authorization;
    }

    // Belt and braces: tenant context headers must never reach the control plane.
    if (typeof headers.delete === 'function') {
        headers.delete('X-Tenant');
        headers.delete('X-Store-Id');
    } else {
        delete headers['X-Tenant'];
        delete headers['X-Store-Id'];
    }

    headers['X-Locale'] = currentLocale();

    return config;
});

function isAuthEndpoint(url) {
    return /^\/super-admin\/auth\/(login|two-factor-challenge|forgot-password|reset-password)\b/.test(url || '');
}

centralApi.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error.response?.status ?? null;
        const data = error.response?.data ?? null;
        const errorCode = data?.error_code ?? null;
        const config = error.config || {};
        const url = String(config.url || '');

        // Step-up: prompt for a fresh 2FA proof, then retry the original request once.
        if (
            status === 403 &&
            errorCode === CENTRAL_ERROR_CODES.stepUpRequired &&
            !config._centralStepUpRetried &&
            typeof sessionHandlers.onStepUpRequired === 'function'
        ) {
            try {
                await sessionHandlers.onStepUpRequired();
            } catch {
                error.stepUpCancelled = true;
                error.userMessage = trans('super.central_auth.step_up_cancelled');
                return Promise.reject(error);
            }
            return centralApi({ ...config, _centralStepUpRetried: true });
        }

        const sessionLost =
            (status === 401 && !isAuthEndpoint(url)) ||
            (status === 403 && errorCode === CENTRAL_ERROR_CODES.setupRequired && !transientToken);

        // config.silent: the caller (session check, logout) handles a lost session itself.
        if (sessionLost && !config.silent && typeof sessionHandlers.onUnauthenticated === 'function') {
            sessionHandlers.onUnauthenticated();
        }

        const message = data?.message || error.message || trans('common.unexpected_error');

        if (status === 403 && !errorCode && !config.silent) {
            notifyWarning(trans('common.permission_alert'), message);
        }

        if (status === 422 && data?.errors) {
            error.userMessage = Object.values(data.errors).flat()[0] || message;
        } else if (isNetworkError(error)) {
            error.userMessage = trans('connectivity.network_error');
        } else {
            error.userMessage = message;
        }

        return Promise.reject(error);
    }
);

export default centralApi;
export { centralApi };
