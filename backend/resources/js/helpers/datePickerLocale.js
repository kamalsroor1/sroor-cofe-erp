import { Arabic } from 'flatpickr/dist/l10n/ar.js';

/**
 * The flatpickr locale of the date pickers: Arabic in RTL, flatpickr's built-in English ('default') in LTR.
 * One helper so DatePicker and BaseDatePicker can never disagree.
 */
export function datePickerLocale(isRtl) {
    return isRtl ? Arabic : 'default';
}
