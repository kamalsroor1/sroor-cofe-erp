import { defaultArabicTranslations } from './defaultTranslations';

/**
 * Universal translation helper for Vue 3 Pure SPA (Pinia Store & window.spaTranslations)
 * Usage in template: $t('pos.title') or trans('auth.login_button')
 * Usage in script setup: const { t } = useTrans(); t('common.save');
 */
export function trans(key, replace = {}) {
    if (!key || typeof key !== 'string') return '';

    let translations = {};

    // 1. Get global SPA translations from window / Pinia store
    if (typeof window !== 'undefined' && window.spaTranslations && Object.keys(window.spaTranslations).length > 0) {
        translations = window.spaTranslations;
    }

    const parts = key.split('.');
    let value = translations;

    for (const part of parts) {
        if (value && typeof value === 'object' && value[part] !== undefined) {
            value = value[part];
        } else {
            value = undefined;
            break;
        }
    }

    // 2. Fallback to bundled static default Arabic translations
    if (value === undefined) {
        let fallbackVal = defaultArabicTranslations;
        for (const part of parts) {
            if (fallbackVal && typeof fallbackVal === 'object' && fallbackVal[part] !== undefined) {
                fallbackVal = fallbackVal[part];
            } else {
                fallbackVal = undefined;
                break;
            }
        }
        if (fallbackVal !== undefined) {
            value = fallbackVal;
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
