import { defineStore } from 'pinia';
import api from '../Services/api';
import { trans } from '../helpers/trans';
import { isCentralAppContext } from '../Services/centralApi';
import { mustChangeFromPayload } from '../helpers/passwordChange';
import { NO_STORE_ASSIGNED, storeNoticeFromPayload } from '../helpers/storeNotice';

export const useAuthStore = defineStore('auth', {
    state: () => {
        // The platform console (central mode) never reads or uses a tenant session.
        if (isCentralAppContext()) {
            return {
                user: null,
                token: null,
                currentStore: null,
                stores: [],
                roles: [],
                permissions: [],
                storeNotice: null,
                isLoading: false,
            };
        }

        let savedUser;
        let savedStore;
        let savedNotice;
        try {
            savedUser = JSON.parse(localStorage.getItem('auth_user') || 'null');
            savedStore = JSON.parse(localStorage.getItem('auth_store') || 'null');
            savedNotice = JSON.parse(localStorage.getItem('auth_store_notice') || 'null');
        } catch {
            savedUser = null;
            savedStore = null;
            savedNotice = null;
        }

        return {
            user: savedUser,
            token: localStorage.getItem('auth_token') || null,
            currentStore: savedStore,
            stores: [],
            roles: savedUser?.roles || [],
            permissions: savedUser?.permissions || [],
            // ROLE-01: {error_code: 'no_store_assigned', message} while the account has no branch.
            storeNotice: savedNotice,
            // SELL-07: shop suspended for non-payment; the session may only reach /billing.
            subscriptionBlocked: localStorage.getItem('auth_payment_required') === '1',
            isLoading: false,
        };
    },

    getters: {
        // Tenant session only. Platform operators sign in separately (stores/centralAuth.js, IDEN-1.9).
        isAuthenticated: (state) => !!state.token && !!state.user,
        isAdmin: (state) => state.roles.includes('admin'),
        // ROLE-01: the account is assigned to no branch; the app shell is replaced by a blocking notice.
        hasNoStore: (state) => !!state.token && state.storeNotice?.error_code === NO_STORE_ASSIGNED,
        // SELL-01: the account must pick its own password before anything else works.
        mustChangePassword: (state) => !!state.token && state.user?.must_change_password === true,
        userName: (state) => state.user?.name || trans('common.default_user_name'),
        activeStoreName: (state) => state.currentStore?.name || trans('common.main_branch'),
        activeStoreId: (state) => state.currentStore?.id || '',
        themePreference: (state) => state.user?.theme_preference || 'dark',
        // Users who can act as a manager approver (SalePriceGuard permissions, or the admin role used for cancels).
        canActAsApprover: (state) =>
            !!state.user &&
            (state.roles.includes('admin') ||
                state.permissions.includes('pos.price.below_min') ||
                state.permissions.includes('pos.discount.override')),
    },

    actions: {
        /**
         * Authenticate user against Backend API
         */
        async login(credentials) {
            this.isLoading = true;
            try {
                const response = await api.post('/auth/login', credentials);
                return this.applyAuthPayload(response);
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Testing-only passwordless login (offered only when /auth/options allows it).
         */
        async quickLogin(userId, deviceName) {
            this.isLoading = true;
            try {
                const response = await api.post('/auth/quick-login', {
                    user_id: userId,
                    device_name: deviceName,
                });
                return this.applyAuthPayload(response);
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Shared session persistence for every login flow.
         */
        applyAuthPayload(response) {
            const payload = response.data?.data;

            if (!payload || !payload.token) {
                throw new Error(response.data?.message || trans('auth.failed'));
            }

            this.token = payload.token;
            this.user = { ...payload.user, must_change_password: mustChangeFromPayload(payload) };
            this.roles = payload.user?.roles || [];
            this.permissions = payload.user?.permissions || [];
            this.currentStore = payload.store;
            this.stores = payload.stores || [];
            this.setStoreNotice(storeNoticeFromPayload(payload));
            this.subscriptionBlocked = payload.subscription_blocked === true;
            if (this.subscriptionBlocked) localStorage.setItem('auth_payment_required', '1');
            else localStorage.removeItem('auth_payment_required');

            localStorage.setItem('auth_token', payload.token);
            localStorage.setItem('auth_user', JSON.stringify(payload.user));
            if (payload.store) {
                localStorage.setItem('auth_store', JSON.stringify(payload.store));
                localStorage.setItem('current_store_id', payload.store.id);
            }

            return response.data;
        },

        /**
         * Fetch and refresh current user profile, permissions, and active store
         */
        async fetchMe() {
            if (!this.token) return null;

            this.isLoading = true;
            try {
                const response = await api.get('/auth/me');
                const payload = response.data?.data;

                if (payload && payload.user) {
                    this.user = { ...payload.user, must_change_password: mustChangeFromPayload(payload) };
                    this.roles = payload.user.roles || [];
                    this.permissions = payload.user.permissions || [];
                    if (payload.store) {
                        this.currentStore = payload.store;
                        localStorage.setItem('auth_store', JSON.stringify(payload.store));
                        localStorage.setItem('current_store_id', payload.store.id);
                    }
                    if (payload.stores) {
                        this.stores = payload.stores;
                    }
                    this.setStoreNotice(storeNoticeFromPayload(payload));
                    localStorage.setItem('auth_user', JSON.stringify(this.user));
                }
                return payload;
            } catch (error) {
                if (error.response?.status === 401) {
                    this.clearSession();
                }
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * ROLE-01: remember (or clear, with null) the no-branch notice from a login / me payload or a 403.
         */
        setStoreNotice(notice) {
            this.storeNotice = notice || null;
            if (this.storeNotice) {
                localStorage.setItem('auth_store_notice', JSON.stringify(this.storeNotice));
            } else {
                localStorage.removeItem('auth_store_notice');
            }
        },

        /**
         * SELL-01: the API answered 403 auth.password_change_required (flag set while the session was open).
         */
        markPasswordChangeRequired() {
            if (!this.user || this.user.must_change_password === true) return;
            this.user = { ...this.user, must_change_password: true };
            localStorage.setItem('auth_user', JSON.stringify(this.user));
        },

        /**
         * SELL-01: replace the signed-in user's own password. The current token stays valid; the server
         * clears the flag and returns the fresh user. Passwords are never kept in the store.
         */
        async changePassword({ current_password, password, password_confirmation }) {
            const response = await api.post('/auth/change-password', {
                current_password,
                password,
                password_confirmation,
            });
            const fresh = response.data?.data?.user;
            this.user = { ...this.user, ...fresh, must_change_password: false };
            if (fresh?.roles) this.roles = fresh.roles;
            if (fresh?.permissions) this.permissions = fresh.permissions;
            localStorage.setItem('auth_user', JSON.stringify(this.user));
            return response.data;
        },

        /**
         * Switch active store context
         */
        switchStore(store) {
            this.currentStore = store;
            localStorage.setItem('auth_store', JSON.stringify(store));
            localStorage.setItem('current_store_id', store.id);
        },

        /**
         * Check if user possesses given permission (string, or array = any-of)
         */
        hasPermission(permissionName) {
            if (!this.user) return false;
            if (this.roles.includes('admin')) return true;
            if (Array.isArray(permissionName)) {
                return permissionName.some((name) => this.permissions.includes(name));
            }
            return this.permissions.includes(permissionName);
        },

        /**
         * Alias for hasPermission
         */
        can(permissionName) {
            return this.hasPermission(permissionName);
        },

        /**
         * Check if user possesses given role
         */
        hasRole(roleName) {
            if (!this.user) return false;
            return this.roles.includes(roleName);
        },

        /**
         * Logout user and revoke token
         */
        async logout() {
            this.isLoading = true;
            try {
                if (this.token) {
                    await api.post('/auth/logout');
                }
            } catch (e) {
                // Ignore errors during logout
            } finally {
                this.clearSession();
                this.isLoading = false;
            }
        },

        /**
         * Clear local auth state
         */
        clearSession() {
            this.user = null;
            this.token = null;
            this.currentStore = null;
            this.stores = [];
            this.roles = [];
            this.permissions = [];
            this.storeNotice = null;
            localStorage.removeItem('auth_store_notice');
            this.subscriptionBlocked = false;
            localStorage.removeItem('auth_payment_required');
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            localStorage.removeItem('auth_store');
            localStorage.removeItem('current_store_id');
        },
    },
});
