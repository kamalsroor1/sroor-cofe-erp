import { defineStore } from 'pinia';
import centralApi, {
    CENTRAL_ERROR_CODES,
    CENTRAL_STORAGE_KEYS,
    setTransientCentralToken,
} from '../Services/centralApi';
import { trans } from '../helpers/trans';

/**
 * Platform-operator session (IDEN-1.9). Separate from the tenant `auth` store:
 * its own storage keys, its own client (centralApi), its own login flow.
 *
 * Sign-in outcomes of POST /super-admin/auth/login:
 *  - `challenge`: 2FA confirmed => single-use challenge id, exchanged with a TOTP or
 *    recovery code at /two-factor-challenge;
 *  - `setup`: 2FA not set up yet => a `central:2fa-setup` token kept in MEMORY only, usable
 *    on /two-factor/enable + /two-factor/confirm (confirm returns the full token + recovery codes);
 *  - `authenticated`: full `central:*` token (persisted, capped at SESSION_MAX_MINUTES);
 *  - `password_reset_required`: migrated account that must reset its password first.
 */

export const SESSION_MAX_MINUTES = 240;
export const IDLE_TIMEOUT_MINUTES = 15;

const DEVICE_NAME = 'platform-console';
const AUTH = '/super-admin/auth';

let stepUpWaiter = null;

function readJson(key) {
    try {
        return JSON.parse(localStorage.getItem(key) || 'null');
    } catch {
        return null;
    }
}

function readStoredSession() {
    try {
        return {
            token: localStorage.getItem(CENTRAL_STORAGE_KEYS.token),
            user: readJson(CENTRAL_STORAGE_KEYS.user),
            expiresAt: localStorage.getItem(CENTRAL_STORAGE_KEYS.expiresAt),
            startedAt: Number(localStorage.getItem(CENTRAL_STORAGE_KEYS.startedAt)) || null,
        };
    } catch {
        return { token: null, user: null, expiresAt: null, startedAt: null };
    }
}

/**
 * Client-side session failures carry a stable `code` (super.central_auth.session_errors.*)
 * and a translated `userMessage`, the same field centralApi sets on HTTP errors, so callers
 * keep showing `e.userMessage`.
 */
export const CENTRAL_SESSION_ERRORS = Object.freeze({
    noPendingChallenge: 'no_pending_challenge',
    emptySignInResponse: 'empty_sign_in_response',
    missingToken: 'missing_token',
    emptyProfile: 'empty_profile',
});

export function centralSessionError(code) {
    const key = `super.central_auth.session_errors.${code}`;
    const error = new Error(key);
    error.code = key;
    error.userMessage = trans(key);
    return error;
}

function isPasswordResetRequired(payload) {
    return payload?.password_reset_required === true;
}

export const useCentralAuthStore = defineStore('centralAuth', {
    state: () => {
        const stored = readStoredSession();
        return {
            token: stored.token,
            user: stored.user,
            expiresAt: stored.expiresAt,
            startedAt: stored.startedAt,
            isVerified: false,
            challenge: null,
            setupUser: null,
            recoveryCodes: [],
            stepUpOpen: false,
            notice: null,
        };
    },

    getters: {
        sessionDeadline: (state) => {
            const deadlines = [];
            if (state.startedAt) deadlines.push(state.startedAt + SESSION_MAX_MINUTES * 60 * 1000);
            const serverExpiry = state.expiresAt ? Date.parse(state.expiresAt) : NaN;
            if (!Number.isNaN(serverExpiry)) deadlines.push(serverExpiry);
            return deadlines.length ? Math.min(...deadlines) : null;
        },
        // Expiry is time-based (not reactive): the router guard and useIdleLogout call isSessionExpired().
        isAuthenticated: (state) => !!state.token && !!state.user,
        userName: (state) => state.user?.name || '',
        permissions: (state) => state.user?.permissions || [],
    },

    actions: {
        isSessionExpired(now = Date.now()) {
            const deadline = this.sessionDeadline;
            return !this.startedAt || (deadline !== null && now >= deadline);
        },

        can(permission) {
            return this.permissions.includes(permission);
        },

        /** @returns {Promise<'challenge'|'setup'|'authenticated'|'password_reset_required'>} */
        async login({ email, password }) {
            this.clearSession();
            try {
                const response = await centralApi.post(`${AUTH}/login`, {
                    email,
                    password,
                    device_name: DEVICE_NAME,
                });
                return this.handleSignIn(response.data?.data);
            } catch (error) {
                if (error.response?.data?.error_code === CENTRAL_ERROR_CODES.passwordResetRequired) {
                    return 'password_reset_required';
                }
                throw error;
            }
        },

        async completeChallenge({ code = null, recoveryCode = null }) {
            if (!this.challenge?.id) throw centralSessionError(CENTRAL_SESSION_ERRORS.noPendingChallenge);
            const response = await centralApi.post(`${AUTH}/two-factor-challenge`, {
                challenge_id: this.challenge.id,
                ...(recoveryCode ? { recovery_code: recoveryCode } : { code }),
            });
            return this.handleSignIn(response.data?.data);
        },

        handleSignIn(payload) {
            if (!payload) throw centralSessionError(CENTRAL_SESSION_ERRORS.emptySignInResponse);

            if (isPasswordResetRequired(payload)) {
                return 'password_reset_required';
            }

            if (payload.two_factor_required) {
                this.challenge = { id: payload.challenge_id, expiresAt: payload.challenge_expires_at };
                return 'challenge';
            }

            if (payload.two_factor_setup_required) {
                setTransientCentralToken(payload.token);
                this.setupUser = payload.user || null;
                return 'setup';
            }

            this.applySession(payload);
            return 'authenticated';
        },

        /** @returns {Promise<{secret: string, otpauth_url: string, qr_code_svg: string}>} */
        async startTwoFactorSetup() {
            const response = await centralApi.post(`${AUTH}/two-factor/enable`);
            return response.data?.data;
        },

        async confirmTwoFactorSetup(code) {
            const response = await centralApi.post(`${AUTH}/two-factor/confirm`, { code });
            const payload = response.data?.data;
            setTransientCentralToken(null);
            this.setupUser = null;
            this.recoveryCodes = Array.isArray(payload?.recovery_codes) ? payload.recovery_codes : [];
            this.applySession(payload);
            return this.recoveryCodes;
        },

        acknowledgeRecoveryCodes() {
            this.recoveryCodes = [];
        },

        applySession(payload) {
            if (!payload?.token || !payload?.user) throw centralSessionError(CENTRAL_SESSION_ERRORS.missingToken);

            this.token = payload.token;
            this.user = payload.user;
            this.expiresAt = payload.expires_at || null;
            this.startedAt = Date.now();
            this.isVerified = true;
            this.challenge = null;
            this.notice = null;

            localStorage.setItem(CENTRAL_STORAGE_KEYS.token, this.token);
            localStorage.setItem(CENTRAL_STORAGE_KEYS.user, JSON.stringify(this.user));
            localStorage.setItem(CENTRAL_STORAGE_KEYS.startedAt, String(this.startedAt));
            if (this.expiresAt) localStorage.setItem(CENTRAL_STORAGE_KEYS.expiresAt, this.expiresAt);
            else localStorage.removeItem(CENTRAL_STORAGE_KEYS.expiresAt);
        },

        /** Validates the stored token once per page load (and refreshes the operator). */
        async ensureSession() {
            if (!this.token) return false;
            if (this.isSessionExpired()) {
                await this.logout('expired');
                return false;
            }
            if (this.isVerified) return true;

            try {
                const response = await centralApi.get(`${AUTH}/me`, { silent: true });
                const user = response.data?.data;
                if (!user) throw centralSessionError(CENTRAL_SESSION_ERRORS.emptyProfile);
                this.user = user;
                this.isVerified = true;
                localStorage.setItem(CENTRAL_STORAGE_KEYS.user, JSON.stringify(user));
                return true;
            } catch (error) {
                const status = error.response?.status;
                if (status === 401 || status === 403) {
                    this.clearSession();
                    return false;
                }
                throw error;
            }
        },

        /** Opens StepUpPrompt; resolves once POST /step-up succeeded, rejects when cancelled. */
        requestStepUp() {
            if (stepUpWaiter) return stepUpWaiter.promise;
            let resolve;
            let reject;
            const promise = new Promise((res, rej) => {
                resolve = res;
                reject = rej;
            });
            stepUpWaiter = { promise, resolve, reject };
            this.stepUpOpen = true;
            return promise;
        },

        async confirmStepUp({ code = null, recoveryCode = null }) {
            await centralApi.post(`${AUTH}/step-up`, recoveryCode ? { recovery_code: recoveryCode } : { code });
            this.stepUpOpen = false;
            const waiter = stepUpWaiter;
            stepUpWaiter = null;
            waiter?.resolve();
        },

        cancelStepUp() {
            this.stepUpOpen = false;
            const waiter = stepUpWaiter;
            stepUpWaiter = null;
            waiter?.reject(new Error('step-up cancelled'));
        },

        async forgotPassword(email) {
            const response = await centralApi.post(`${AUTH}/forgot-password`, { email });
            return response.data?.message || '';
        },

        async resetPassword({ token, email, password, passwordConfirmation }) {
            const response = await centralApi.post(`${AUTH}/reset-password`, {
                token,
                email,
                password,
                password_confirmation: passwordConfirmation,
            });
            return response.data?.message || '';
        },

        /**
         * @param {'idle'|'expired'|null} reason shown once on the central login screen
         */
        async logout(reason = null) {
            if (this.token) {
                try {
                    await centralApi.post(`${AUTH}/logout`, null, { silent: true });
                } catch {
                    // The token may already be revoked or expired; the local session is cleared anyway.
                }
            }
            this.clearSession();
            this.notice = reason;
        },

        clearSession() {
            if (stepUpWaiter) this.cancelStepUp();
            setTransientCentralToken(null);
            this.token = null;
            this.user = null;
            this.expiresAt = null;
            this.startedAt = null;
            this.isVerified = false;
            this.challenge = null;
            this.setupUser = null;
            this.recoveryCodes = [];
            this.stepUpOpen = false;
            try {
                localStorage.removeItem(CENTRAL_STORAGE_KEYS.token);
                localStorage.removeItem(CENTRAL_STORAGE_KEYS.user);
                localStorage.removeItem(CENTRAL_STORAGE_KEYS.expiresAt);
                localStorage.removeItem(CENTRAL_STORAGE_KEYS.startedAt);
            } catch {
                // Storage unavailable (private mode): the in-memory state is already cleared.
            }
        },
    },
});
