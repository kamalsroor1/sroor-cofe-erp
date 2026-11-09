import { defineStore } from 'pinia';
import api from '../Services/api';
import { applyThemeColor } from '../helpers/themeHelper';
import { trans, setTranslations, loadBundledTranslations } from '../helpers/trans';
import { applyDocumentLocale, directionFor, isSupportedLocale } from '../helpers/locale';
import { isCentralAppContext } from '../Services/centralApi';

function initialLocale() {
    const candidates = [localStorage.getItem('app_locale'), window.spaLocale];
    return candidates.find(isSupportedLocale) || 'ar';
}

export const useAppConfigStore = defineStore('appConfig', {
    state: () => ({
        system: {
            platform_name: '',
            company_name: '',
            company_subtitle: '',
            system_theme_color: 'amber',
            server_time: null,
        },
        branding: {
            logo_light: '/logo-light.png',
            logo_dark: '/logo-dark.png',
            logo: '/logo.png',
        },
        tenant: null,
        entitlements: null,
        activeShift: null,
        setupProgress: null,
        setupProgressBooted: false,
        notifications: [],
        locale: initialLocale(),
        translations: {},
        theme: localStorage.getItem('theme_preference') || 'dark',
        isLoaded: false,
    }),

    getters: {
        isDark: (state) => state.theme === 'dark',
        dir: (state) => directionFor(state.locale),
        isRtl: (state) => directionFor(state.locale) === 'rtl',
        platformName: (state) =>
            state.system.platform_name || state.system.company_name || trans('common.platform_name'),
        companyName: (state) => state.system.company_name || trans('auth.default_company_name'),
        companySubtitle: (state) => state.system.company_subtitle,
        currentShiftNumber: (state) => state.activeShift?.shift_number || null,
        hasOpenShift: (state) => !!state.activeShift,
        /**
         * Plan entitlement check. Allow while the context is not loaded or the tenant is not enforced
         * (legacy planless tenants); otherwise the canonical key must be in `features`.
         * Accepts a key or an any-of array.
         */
        hasFeature: (state) => (keyOrKeys) => {
            const keys = (Array.isArray(keyOrKeys) ? keyOrKeys : [keyOrKeys]).filter(Boolean);
            if (keys.length === 0) return true;
            const ent = state.entitlements;
            if (!ent || !ent.enforced) return true;
            const features = Array.isArray(ent.features) ? ent.features : [];
            return keys.some((key) => features.includes(key));
        },
        alertsCount: (state) => state.notifications.length,
    },

    actions: {
        /**
         * Fetch full bootstrap context from API
         */
        async fetchBootstrapContext() {
            // Tenant bootstrap (/system/context) never runs on the platform console.
            if (isCentralAppContext()) return null;
            try {
                // Echo the dictionary version the page already holds; the server then omits `translations`.
                const params = window.spaTranslationsHash
                    ? { translations_hash: window.spaTranslationsHash }
                    : undefined;
                const response = await api.get('/system/context', { params });
                const data = response.data?.data;

                if (data) {
                    if (data.system) this.system = data.system;
                    if (data.branding) this.branding = data.branding;
                    if (data.tenant) this.tenant = data.tenant;
                    if (data.entitlements) this.entitlements = data.entitlements;
                    this.activeShift = data.active_shift ?? null;
                    this.setupProgress = data.setup_progress ?? null;
                    this.setupProgressBooted = false;
                    if (data.notifications) this.notifications = data.notifications;
                    // The server resolves users.locale first, so its answer wins over the browser preference.
                    if (data.locale) this.applyLocale(data.locale);
                    if (data.translations) {
                        setTranslations(data.translations, data.locale || this.locale, data.translations_hash || '');
                        this.translations = data.translations;
                    } else {
                        if (window.spaTranslations) this.translations = window.spaTranslations;
                        if (data.translations_hash) window.spaTranslationsHash = data.translations_hash;
                    }
                    if (data.stores) {
                        const { useAuthStore } = await import('./auth');
                        const authStore = useAuthStore();
                        authStore.stores = data.stores;
                    }
                    if (data.active_store) {
                        const { useAuthStore } = await import('./auth');
                        const authStore = useAuthStore();
                        if (!authStore.currentStore) {
                            authStore.currentStore = data.active_store;
                        }
                    }
                    this.isLoaded = true;

                    // Apply theme color
                    if (data.system?.system_theme_color) {
                        applyThemeColor(data.system.system_theme_color);
                    }
                }
                return data;
            } catch (error) {
                console.error('Failed to load system context:', error);
                throw error;
            }
        },

        /**
         * Re-read only the onboarding checklist progress from its dedicated endpoint.
         */
        async refreshSetupProgress() {
            if (isCentralAppContext()) return;
            try {
                const response = await api.get('/system/setup-progress');
                this.setupProgress = response.data?.data ?? null;
            } catch {
                // Keep the last known progress; the checklist is a non-critical hint.
            }
        },

        /**
         * Make `locale` the active one: store state, browser preference and <html lang/dir>.
         * api.js reads app_locale for X-Locale, so every request after this call is in the new language.
         */
        applyLocale(locale) {
            if (!isSupportedLocale(locale)) return;
            this.locale = locale;
            localStorage.setItem('app_locale', locale);
            applyDocumentLocale(locale);
        },

        /**
         * Fetch the translation dictionary for `locale` and install it (window.spaTranslations, its
         * hash and the reactive version) so trans() and computed labels re-render. Never throws: when the
         * API is unreachable the dictionary bundled with the build is used instead.
         */
        async fetchTranslations(locale = this.locale) {
            if (!isSupportedLocale(locale)) locale = 'ar';
            // Central mode has no tenant API: use the dictionary bundled with the build.
            if (isCentralAppContext()) {
                setTranslations(await loadBundledTranslations(locale), locale);
                this.translations = window.spaTranslations;
                this.applyLocale(locale);
                return;
            }
            try {
                localStorage.setItem('app_locale', locale); // X-Locale of this very request
                const response = await api.get('/system/translations', { params: { locale } });
                if (response.data?.data) {
                    setTranslations(response.data.data, locale);
                    this.translations = response.data.data;
                    this.applyLocale(locale);
                }
            } catch (error) {
                // API unreachable or not tenant-resolved yet (connect screen): the build ships the same keys.
                console.error('Failed to load translations, using the bundled dictionary:', error);
                setTranslations(await loadBundledTranslations(locale), locale);
                this.translations = window.spaTranslations;
                this.applyLocale(locale);
            }
        },

        /**
         * User-initiated language switch. The new dictionary is installed first and always sticks
         * (fetchTranslations falls back to the bundled one, so it cannot fail). Then, for a signed-in
         * user, the choice is saved server-side (users.locale outranks X-Locale there) and the context
         * is re-read so server-built strings follow. Resolves to false when saving failed: the UI stays
         * in the new language and the context refresh is skipped so it cannot revert it.
         */
        async switchLocale(locale) {
            if (!isSupportedLocale(locale) || locale === this.locale) return true;
            await this.fetchTranslations(locale);
            const { useAuthStore } = await import('./auth');
            if (!useAuthStore().isAuthenticated || isCentralAppContext()) return true;
            try {
                await api.put('/profile/locale', { locale });
            } catch {
                return false;
            }
            await this.fetchBootstrapContext().catch(() => {});
            return true;
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
         * Change primary accent color
         */
        setThemeColor(color) {
            if (!color) return;
            this.system.system_theme_color = color;
            applyThemeColor(color);
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
