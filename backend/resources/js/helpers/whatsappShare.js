import { loadBundledTranslations } from './trans';
import { fillPlaceholders } from './whatsappLink';

let arabicDictionary = null;

/** Loads the Arabic dictionary ahead of time so the message can be built synchronously on click. */
export async function preloadArabicDictionary() {
    if (!arabicDictionary) arabicDictionary = await loadBundledTranslations('ar');
    return arabicDictionary;
}

/** Text for the shop owner, always in Arabic whatever the operator's UI language is. */
export function arabicMessage(key, replace = {}) {
    const value = key
        .split('.')
        .reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), arabicDictionary);
    return typeof value === 'string' ? fillPlaceholders(value, replace) : '';
}
