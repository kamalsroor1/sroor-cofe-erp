import { defineStore } from 'pinia';
import api from '../services/api';
import { trans } from '../helpers/trans';

export const useAuthStore = defineStore('auth', {
    state: () => {
        let savedUser;
        let savedStore;
        try {
            savedUser = JSON.parse(localStorage.getItem('auth_user') || 'null');
            savedStore = JSON.parse(localStorage.getItem('auth_store') || 'null');
        } catch {
            savedUser = null;
            savedStore = null;
        }

        return {
            user: savedUser,
            token: localStorage.getItem('auth_token') || null,
            currentStore: savedStore,
            stores: [],
            roles: savedUser?.roles || [],
            permissions: savedUser?.permissions || [],
            isLoading: false,
        };
    },

    getters: {
        isAuthenticated: (state) => !!state.token && !!state.user,
        // Platform super admin comes only from the backend central flag (PlatformSuperAdmin::check()).
        // Tenant role names / permissions must never grant it.
        isSuperAdmin: (state) => state.user?.is_super_admin === true,
        isAdmin: (state) => state.roles.includes('admin') || state.user?.is_super_admin === true,
        userName: (state) => state.user?.name || trans('common.default_user_name'),
        activeStoreName: (state) => state.currentStore?.name || trans('common.main_branch'),
        themePreference: (state) => state.user?.theme_preference || 'dark',
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
            this.user = payload.user;
            this.roles = payload.user?.roles || [];
            this.permissions = payload.user?.permissions || [];
            this.currentStore = payload.store;
            this.stores = payload.stores || [];

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
                    this.user = payload.user;
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
                    localStorage.setItem('auth_user', JSON.stringify(payload.user));
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
         * Switch active store context
         */
        switchStore(store) {
            this.currentStore = store;
            localStorage.setItem('auth_store', JSON.stringify(store));
            localStorage.setItem('current_store_id', store.id);
        },

        /**
         * Check if user possesses given permission
         */
        hasPermission(permissionName) {
            if (!this.user) return false;
            if (permissionName === 'super_admin.access' || permissionName === 'view_telescope') {
                return this.user?.is_super_admin === true;
            }
            if (this.roles.includes('admin') || this.user?.is_super_admin === true) return true;
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
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            localStorage.removeItem('auth_store');
            localStorage.removeItem('current_store_id');
        },
    },
});
