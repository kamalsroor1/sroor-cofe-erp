import { SUPPORTED_LOCALES } from './trans';

const RTL_LOCALES = ['ar'];

export const isSupportedLocale = (locale) => SUPPORTED_LOCALES.includes(locale);

export const directionFor = (locale) => (RTL_LOCALES.includes(locale) ? 'rtl' : 'ltr');

/** Reflect the active locale on <html> so CSS logical properties, native widgets and a11y follow it. */
export function applyDocumentLocale(locale) {
    if (typeof document === 'undefined') return;
    const root = document.documentElement;
    root.setAttribute('lang', locale);
    root.setAttribute('dir', directionFor(locale));
}
