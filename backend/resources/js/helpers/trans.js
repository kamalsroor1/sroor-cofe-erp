/**
 * Universal translation helper for Vue 3 Pure SPA.
 * The server inlines the active locale's dictionary as window.spaTranslations (app.blade.php) and
 * /system/context refreshes it. Nothing is bundled: ensureTranslations() lazily imports only the
 * active locale if the page was served without an inlined dictionary.
 * Usage in template: $t('pos.title') or trans('auth.login_button')
 * Usage in script setup: const { t } = useTrans(); t('common.save');
 */
const localeLoaders = {
    ar: () => import('./defaultTranslations.ar.json'),
    en: () => import('./defaultTranslations.en.json'),
};

function activeLocale() {
    const stored = typeof localStorage !== 'undefined' ? localStorage.getItem('app_locale') : null;
    const candidate = stored || (typeof window !== 'undefined' && window.spaLocale) || 'ar';
    return candidate in localeLoaders ? candidate : 'ar';
}

export async function ensureTranslations() {
    if (typeof window === 'undefined') return;
    if (window.spaTranslations && Object.keys(window.spaTranslations).length > 0) return;
    try {
        const module = await localeLoaders[activeLocale()]();
        window.spaTranslations = module.default;
    } catch {
        window.spaTranslations = {};
    }
}

export function trans(key, replace = {}) {
    if (!key || typeof key !== 'string') return '';

    let value = typeof window !== 'undefined' && window.spaTranslations ? window.spaTranslations : {};

    for (const part of key.split('.')) {
        if (value && typeof value === 'object' && value[part] !== undefined) {
            value = value[part];
        } else {
            return key;
        }
    }

    if (typeof value === 'string') {
        Object.keys(replace).forEach((placeholder) => {
            value = value.replace(new RegExp(`:${placeholder}`, 'g'), replace[placeholder]);
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
