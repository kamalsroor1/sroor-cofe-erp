/**
 * Universal translation helper for Vue 3 Pure SPA.
 * The server inlines the active locale's dictionary as window.spaTranslations (app.blade.php) and
 * /system/context refreshes it. Nothing is bundled: ensureTranslations() lazily imports only the
 * active locale if the page was served without an inlined dictionary.
 * Usage in template: $t('pos.title') or trans('auth.login_button')
 * Usage in script setup: const { t } = useTrans(); t('common.save');
 */
import { ref } from 'vue';

/**
 * Bumped whenever the dictionary is swapped (language switch). trans() reads it, so every render
 * function and computed that translates re-evaluates on its own: no remount, no reload.
 */
export const translationsVersion = ref(0);

const localeLoaders = {
    ar: () => import('./defaultTranslations.ar.json'),
    en: () => import('./defaultTranslations.en.json'),
};

export const SUPPORTED_LOCALES = Object.keys(localeLoaders);

function activeLocale() {
    const stored = typeof localStorage !== 'undefined' ? localStorage.getItem('app_locale') : null;
    const candidate = stored || (typeof window !== 'undefined' && window.spaLocale) || 'ar';
    return candidate in localeLoaders ? candidate : 'ar';
}

/** Replace the active dictionary (and its version) and notify every reactive translation reader. */
export function setTranslations(dictionary, locale, hash = '') {
    window.spaTranslations = dictionary || {};
    if (locale) window.spaLocale = locale;
    window.spaTranslationsHash = hash;
    translationsVersion.value += 1;
}

/** Dictionary shipped with the build, used when no tenant API is reachable (guest boot, platform console). */
export async function loadBundledTranslations(locale) {
    const module = await localeLoaders[locale in localeLoaders ? locale : 'ar']();
    return module.default;
}

export async function ensureTranslations() {
    if (typeof window === 'undefined') return;
    const wanted = activeLocale();
    const hasDictionary = window.spaTranslations && Object.keys(window.spaTranslations).length > 0;
    // The server inlines its own default locale; a saved browser preference may differ.
    if (hasDictionary && (window.spaLocale || 'ar') === wanted) return;
    try {
        setTranslations(await loadBundledTranslations(wanted), wanted);
    } catch {
        if (!hasDictionary) window.spaTranslations = {};
    }
}

export function trans(key, replace = {}) {
    if (!key || typeof key !== 'string') return '';

    // Reactive dependency only; the dictionary itself lives on window for non-Vue callers.
    void translationsVersion.value;

    let value = typeof window !== 'undefined' && window.spaTranslations ? window.spaTranslations : {};

    for (const part of key.split('.')) {
        if (value && typeof value === 'object' && value[part] !== undefined) {
            value = value[part];
        } else {
            return key;
        }
    }

    if (typeof value === 'string') {
        // Longest placeholder first (like Laravel): `:to` must not eat the start of `:total`.
        Object.keys(replace)
            .sort((a, b) => b.length - a.length)
            .forEach((placeholder) => {
                value = value.replace(new RegExp(`:${placeholder}`, 'g'), () => String(replace[placeholder]));
            });
        return value;
    }

    return key;
}

export function useTrans() {
    return {
        t: trans,
        trans,
    };
}
