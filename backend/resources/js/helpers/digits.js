const ARABIC_INDIC = '٠١٢٣٤٥٦٧٨٩';
const EXTENDED_ARABIC_INDIC = '۰۱۲۳۴۵۶۷۸۹';

/**
 * Converts Arabic-Indic digits (٠-٩, ۰-۹) and the Arabic decimal/thousands marks to
 * Western digits, so cashiers on an Arabic keyboard can type quantities and prices.
 */
export function toWesternDigits(value) {
    return String(value ?? '')
        .replace(/[٠-٩]/g, (d) => String(ARABIC_INDIC.indexOf(d)))
        .replace(/[۰-۹]/g, (d) => String(EXTENDED_ARABIC_INDIC.indexOf(d)))
        .replace(/٫/g, '.')
        .replace(/٬/g, '');
}
