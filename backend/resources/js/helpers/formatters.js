/**
 * SETG-6: the one place that turns dates, times and numbers into display text.
 *
 * - Western digits (0-9) in both languages: 'ar' -> 'ar-EG-u-nu-latn', 'en' -> 'en-GB'.
 * - Instants (Date objects, epoch numbers, ISO strings with a zone) are shown on the
 *   tenant clock: `timeZone` = system.timezone from /system/context. An empty or
 *   unknown zone falls back to the browser zone.
 * - Naive server strings ('2026-10-10 14:30', '2026-10-10') are already wall-clock
 *   time on the tenant clock, so they are shown exactly as written (no zone shift).
 * - Unparseable input returns '' — never 'Invalid Date' on a receipt.
 * - Money goes through formatMoneyAmount() (decimal.js, half-up, no floats/Intl).
 *
 * This is the only file allowed to pick a locale for toLocale*String / Intl
 * (eslint `no-restricted-syntax`). Callers inside components use useFormatters(),
 * which fills `locale` / `timeZone` / currency decimals from the appConfig store.
 */
import { dRoundTo, formatDecimal } from './decimal.js';

export const LOCALE_TAGS = Object.freeze({
    ar: 'ar-EG-u-nu-latn',
    en: 'en-GB',
});

const DEFAULT_LOCALE = 'ar';

/** 'ar' | 'ar-EG' | 'en' | 'en_US' … -> the BCP-47 tag used for display. */
export function resolveLocaleTag(locale) {
    const base = String(locale || '')
        .toLowerCase()
        .split(/[-_]/)[0];
    return LOCALE_TAGS[base] || LOCALE_TAGS[DEFAULT_LOCALE];
}

const zoneValidity = new Map();

/** The IANA zone if the runtime knows it, otherwise undefined (= browser zone). */
export function resolveTimeZone(zone) {
    if (typeof zone !== 'string' || zone.trim() === '') return undefined;
    const name = zone.trim();
    if (!zoneValidity.has(name)) {
        try {
            new Intl.DateTimeFormat('en-GB', { timeZone: name });
            zoneValidity.set(name, true);
        } catch {
            zoneValidity.set(name, false);
        }
    }
    return zoneValidity.get(name) ? name : undefined;
}

const NAIVE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?)?$/;

/**
 * @returns {{ date: Date, naive: boolean } | null}
 */
function toInstant(value) {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : { date: value, naive: false };
    }
    if (typeof value === 'number') {
        if (!Number.isFinite(value)) return null;
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : { date, naive: false };
    }
    if (typeof value !== 'string' || value.trim() === '') return null;

    const text = value.trim();
    const naive = NAIVE_PATTERN.exec(text);
    if (naive) {
        const [, y, mo, d, h = '0', mi = '0', s = '0'] = naive;
        const date = new Date(Date.UTC(Number(y), Number(mo) - 1, Number(d), Number(h), Number(mi), Number(s)));
        const roundTrips =
            date.getUTCFullYear() === Number(y) &&
            date.getUTCMonth() === Number(mo) - 1 &&
            date.getUTCDate() === Number(d) &&
            date.getUTCHours() === Number(h) &&
            date.getUTCMinutes() === Number(mi);
        return roundTrips ? { date, naive: true } : null;
    }

    const parsed = Date.parse(text);
    return Number.isNaN(parsed) ? null : { date: new Date(parsed), naive: false };
}

const formatterCache = new Map();

function cachedFormatter(Ctor, tag, options) {
    const key = `${Ctor.name}|${tag}|${JSON.stringify(options)}`;
    if (!formatterCache.has(key)) {
        formatterCache.set(key, new Ctor(tag, options));
    }
    return formatterCache.get(key);
}

const STYLE_KEYS = ['dateStyle', 'timeStyle'];

function formatInstant(value, defaults, options = {}) {
    const { locale, timeZone, ...intlOptions } = options || {};
    const instant = toInstant(value);
    if (!instant) return '';

    const usesStyle = STYLE_KEYS.some((key) => intlOptions[key] !== undefined);
    const fields = usesStyle ? intlOptions : { ...defaults, ...intlOptions };
    const zone = instant.naive ? 'UTC' : resolveTimeZone(timeZone);

    try {
        return cachedFormatter(Intl.DateTimeFormat, resolveLocaleTag(locale), { ...fields, timeZone: zone }).format(
            instant.date
        );
    } catch {
        return '';
    }
}

const DATE_DEFAULTS = Object.freeze({ year: 'numeric', month: '2-digit', day: '2-digit' });
const TIME_DEFAULTS = Object.freeze({ hour: '2-digit', minute: '2-digit' });

/**
 * @param {Date|string|number} value
 * @param {{ locale?: string, timeZone?: string } & Intl.DateTimeFormatOptions} [options]
 */
export function formatDate(value, options = {}) {
    return formatInstant(value, DATE_DEFAULTS, options);
}

/** Same arguments as formatDate(); hour + minute by default. */
export function formatTime(value, options = {}) {
    return formatInstant(value, TIME_DEFAULTS, options);
}

/** Same arguments as formatDate(); date + hour + minute by default. */
export function formatDateTime(value, options = {}) {
    return formatInstant(value, { ...DATE_DEFAULTS, ...TIME_DEFAULTS }, options);
}

const PLAIN_NUMBER = /^[+-]?(\d+\.?\d*|\.\d+)$/;

/**
 * Counts, percentages and other non-money numbers with Western digits.
 * Numeric strings are passed to Intl as strings (exact, no float rounding).
 *
 * @param {number|string} value
 * @param {{ locale?: string } & Intl.NumberFormatOptions} [options]
 */
export function formatNumber(value, options = {}) {
    const { locale, ...intlOptions } = options || {};
    let input;
    if (typeof value === 'number') {
        if (!Number.isFinite(value)) return '';
        input = value;
    } else if (typeof value === 'string' && PLAIN_NUMBER.test(value.trim())) {
        input = value.trim();
    } else {
        return '';
    }
    try {
        return cachedFormatter(Intl.NumberFormat, resolveLocaleTag(locale), intlOptions).format(input);
    } catch {
        return '';
    }
}

/** Currency display decimals (system.currency_decimals): an integer 0..3, default 2. */
export function resolveCurrencyDecimals(decimals) {
    const places = Number(decimals);
    return Number.isInteger(places) && places >= 0 && places <= 3 ? places : 2;
}

/**
 * Money display text with Western digits and ',' grouping, rounded half-up
 * (SETG-13) to the currency decimals. A whole amount drops the fraction ('150')
 * unless `fixed` is set; any other amount shows every currency decimal ('150.50').
 *
 * @param {string|number|bigint} value
 * @param {{ decimals?: number, fixed?: boolean }} [options] decimals: 0..6 (default 2)
 */
export function formatMoneyAmount(value, { decimals = 2, fixed = false } = {}) {
    const requested = Number(decimals);
    const places = Number.isInteger(requested) && requested >= 0 ? Math.min(requested, 6) : 2;
    const rounded = dRoundTo(value, places);
    if (!fixed && places > 0 && /\.0+$/.test(rounded)) {
        return formatDecimal(rounded, 0);
    }
    return formatDecimal(rounded, places);
}
