// FE-06: calendar dates for "today" / presets / new-document defaults are computed on the
// tenant clock, never with toISOString() (UTC). Mirrors backend App\Support\TenantClock:
// the business date is the tenant wall date, one day earlier while the wall time is before
// the business-day cutoff. Pure functions: Composables/useBusinessDate.js binds them to the store.
import { resolveTimeZone } from './formatters.js';

const DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})/;
const CUTOFF_PATTERN = /^(\d{1,2}):(\d{2})/;

/** Tenant-tz calendar date (YYYY-MM-DD) of an instant. Falls back to the runtime zone. */
export function toTenantDateString(date = new Date(), timeZone) {
    const instant = date instanceof Date ? date : new Date(date);
    if (Number.isNaN(instant.getTime())) return '';
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: resolveTimeZone(timeZone),
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(instant);
}

/** Calendar "today" on the tenant clock, ignoring the business-day cutoff. */
export function todayInTenantTz(timeZone, now = new Date()) {
    return toTenantDateString(now, timeZone);
}

function minutesOfDayInZone(instant, timeZone) {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: resolveTimeZone(timeZone),
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(instant);
    const get = (type) => Number(parts.find((p) => p.type === type)?.value ?? 0);
    return get('hour') * 60 + get('minute');
}

function cutoffMinutes(cutoff) {
    const match = CUTOFF_PATTERN.exec(String(cutoff ?? ''));
    return match ? Number(match[1]) * 60 + Number(match[2]) : 0;
}

/**
 * Current business date (YYYY-MM-DD): with cutoff 04:00, 02:00 still belongs to yesterday.
 * @param {{ timeZone?: string, cutoff?: string, now?: Date }} [options]
 */
export function businessToday({ timeZone, cutoff, now = new Date() } = {}) {
    const today = toTenantDateString(now, timeZone);
    return minutesOfDayInZone(now, timeZone) < cutoffMinutes(cutoff) ? addDays(today, -1) : today;
}

function parseParts(dateStr) {
    const match = DATE_PATTERN.exec(String(dateStr ?? ''));
    return match ? [Number(match[1]), Number(match[2]), Number(match[3])] : null;
}

function format(y, m, d) {
    return `${String(y).padStart(4, '0')}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

/** Add n days to a YYYY-MM-DD string with pure calendar math (no timezone drift). */
export function addDays(dateStr, n) {
    const parts = parseParts(dateStr);
    if (!parts) return '';
    const date = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2] + Number(n)));
    return format(date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate());
}

/** First day of the month of a YYYY-MM-DD string. */
export function startOfMonth(dateStr) {
    const parts = parseParts(dateStr);
    return parts ? format(parts[0], parts[1], 1) : '';
}

/** Last day of the month of a YYYY-MM-DD string. */
export function endOfMonth(dateStr) {
    const parts = parseParts(dateStr);
    return parts ? format(parts[0], parts[1], new Date(Date.UTC(parts[0], parts[1], 0)).getUTCDate()) : '';
}

/** Last day of the year of a YYYY-MM-DD string. */
export function endOfYear(dateStr) {
    const parts = parseParts(dateStr);
    return parts ? format(parts[0], 12, 31) : '';
}

/** First day of the year of a YYYY-MM-DD string. */
export function startOfYear(dateStr) {
    const parts = parseParts(dateStr);
    return parts ? format(parts[0], 1, 1) : '';
}

/** Start of the week containing a YYYY-MM-DD string. weekStartDay: 0 = Sunday ... 6 = Saturday. */
export function startOfWeek(dateStr, weekStartDay = 6) {
    const parts = parseParts(dateStr);
    if (!parts) return '';
    const dow = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2])).getUTCDay();
    return addDays(dateStr, -((dow - weekStartDay + 7) % 7));
}
