import { defineStore } from 'pinia';
import api from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => {
        let savedUser = null;
        let savedStore = null;
        try {
            savedUser = JSON.parse(localStorage.getItem('auth_user') || 'null');
            savedStore = JSON.parse(localStorage.getItem('auth_store') || 'null');
        } catch (e) {
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
        isSuperAdmin: (state) => !!state.user?.is_super_admin || state.roles.includes('super_admin') || state.permissions.includes('super_admin.access'),
        isAdmin: (state) => state.roles.includes('admin') || state.roles.includes('super_admin'),
        userName: (state) => state.user?.name || 'مستخدم',
        activeStoreName: (state) => state.currentStore?.name || 'الفرع الرئيسي',
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
                const payload = response.data?.data;

                if (payload && payload.token) {
                    this.token = payload.token;
                    this.user = payload.user;
                    this.roles = payload.user?.roles || [];
                    this.permissions = payload.user?.permissions || [];
                    this.currentStore = payload.store;
                    this.stores = payload.stores || [];

                    // Persist to storage
                    localStorage.setItem('auth_token', payload.token);
                    localStorage.setItem('auth_user', JSON.stringify(payload.user));
                    if (payload.store) {
                        localStorage.setItem('auth_store', JSON.stringify(payload.store));
                        localStorage.setItem('current_store_id', payload.store.id);
                    }

                    return response.data;
                }
                throw new Error(response.data?.message || 'فشل تسجيل الدخول');
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Quick login for workspace employees without password
         */
        async quickLogin(login) {
            this.isLoading = true;
            try {
                const response = await api.post('/auth/quick-login', {
                    login: login,
                    device_name: 'vue-spa-quick',
                });
                const payload = response.data?.data;

                if (payload && payload.token) {
                    this.token = payload.token;
                    this.user = payload.user;
                    this.roles = payload.user?.roles || [];
                    this.permissions = payload.user?.permissions || [];
                    this.currentStore = payload.store;
                    this.stores = payload.stores || [];

                    // Persist to storage
                    localStorage.setItem('auth_token', payload.token);
                    localStorage.setItem('auth_user', JSON.stringify(payload.user));
                    if (payload.store) {
                        localStorage.setItem('auth_store', JSON.stringify(payload.store));
                        localStorage.setItem('current_store_id', payload.store.id);
                    }

                    return response.data;
                }
                throw new Error(response.data?.message || 'فشل الدخول السريع');
            } finally {
                this.isLoading = false;
            }
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
            if (permissionName === 'super_admin.access') {
                return !!this.user?.is_super_admin || this.roles.includes('super_admin') || this.permissions.includes('super_admin.access');
            }
            if (this.roles.includes('admin') || this.roles.includes('super_admin')) return true;
            return this.permissions.includes(permissionName);
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
