import { defineStore } from 'pinia';
import api from '../services/api';

export const useAppConfigStore = defineStore('appConfig', {
    state: () => ({
        system: {
            company_name: 'سرور كوفي',
            company_subtitle: 'لتوريدات خامات مطاحن البن',
            system_theme_color: 'amber',
            server_time: null,
        },
        branding: {
            logo_light: '/logo-light.png',
            logo_dark: '/logo-dark.png',
            logo: '/logo.png',
        },
        tenant: null,
        activeShift: null,
        notifications: [],
        locale: localStorage.getItem('app_locale') || 'ar',
        translations: {},
        theme: localStorage.getItem('theme_preference') || 'dark',
        isLoaded: false,
    }),

    getters: {
        isDark: (state) => state.theme === 'dark',
        companyName: (state) => state.system.company_name,
        companySubtitle: (state) => state.system.company_subtitle,
        currentShiftNumber: (state) => state.activeShift?.shift_number || null,
        hasOpenShift: (state) => !!state.activeShift,
        alertsCount: (state) => state.notifications.length,
    },

    actions: {
        /**
         * Fetch full bootstrap context from API
         */
        async fetchBootstrapContext() {
            try {
                const response = await api.get('/system/context');
                const data = response.data?.data;

                if (data) {
                    if (data.system) this.system = data.system;
                    if (data.branding) this.branding = data.branding;
                    if (data.tenant) this.tenant = data.tenant;
                    if (data.active_shift) this.activeShift = data.active_shift;
                    if (data.notifications) this.notifications = data.notifications;
                    if (data.locale) this.locale = data.locale;
                    if (data.translations) this.translations = data.translations;
                    this.isLoaded = true;

                    // Apply theme color
                    if (data.system?.system_theme_color) {
                        document.documentElement.setAttribute('data-theme-color', data.system.system_theme_color);
                    }
                }
                return data;
            } catch (error) {
                console.error('Failed to load system context:', error);
                throw error;
            }
        },

        /**
         * Fetch translation dictionary for locale
         */
        async fetchTranslations(locale = 'ar') {
            try {
                const response = await api.get(`/system/translations?locale=${locale}`);
                if (response.data?.data) {
                    this.translations = response.data.data;
                    this.locale = locale;
                    localStorage.setItem('app_locale', locale);
                }
            } catch (error) {
                console.error('Failed to load translations:', error);
            }
        },

        /**
         * Change UI theme (dark / light)
         */
        setTheme(theme) {
            this.theme = theme;
            localStorage.setItem('theme_preference', theme);
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.classList.remove('light');
            } else {
                document.documentElement.classList.add('light');
                document.documentElement.classList.remove('dark');
            }
        },

        /**
         * Reactive translation helper
         */
        t(key, replace = {}) {
            if (!key || typeof key !== 'string') return '';

            const parts = key.split('.');
            let value = this.translations;

            for (const part of parts) {
                if (value && typeof value === 'object' && value[part] !== undefined) {
                    value = value[part];
                } else {
                    return key;
                }
            }

            if (typeof value === 'string') {
                Object.keys(replace).forEach((placeholder) => {
                    value = value.replace(`:${placeholder}`, replace[placeholder]);
                });
                return value;
            }

            return key;
        },
    },
});
