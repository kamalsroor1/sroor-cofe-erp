import { usePage } from '@inertiajs/vue3';

/**
 * Universal translation helper supporting both Vue 3 Pure SPA (Pinia) and Inertia.js
 * Usage: trans('pos.title') or $t('auth.login_button')
 */
export function trans(key, replace = {}) {
    if (!key || typeof key !== 'string') return '';

    let translations = {};

    // 1. Try global SPA translations from Pinia / API
    if (typeof window !== 'undefined' && window.spaTranslations && Object.keys(window.spaTranslations).length > 0) {
        translations = window.spaTranslations;
    } else {
        // 2. Fallback to Inertia usePage if running under Inertia context
        try {
            const page = usePage();
            if (page?.props?.translations) {
                translations = page.props.translations;
            }
        } catch (e) {
            // Not in Inertia context
        }
    }

    const parts = key.split('.');
    let value = translations;

    for (const part of parts) {
        if (value && typeof value === 'object' && value[part] !== undefined) {
            value = value[part];
        } else {
            return key; // Fallback to key if not found
        }
    }

    if (typeof value === 'string') {
        Object.keys(replace).forEach((placeholder) => {
            value = value.replace(`:${placeholder}`, replace[placeholder]);
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
