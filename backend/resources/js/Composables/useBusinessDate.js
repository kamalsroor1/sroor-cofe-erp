import { getActivePinia } from 'pinia';
import { useAppConfigStore } from '../stores/appConfig';
import {
    businessToday as businessTodayFor,
    toTenantDateString as toTenantDateStringFor,
} from '../helpers/businessDate';

// Pure calendar math only; the clock-bound businessToday/toTenantDateString below take a plain `now`/`date`.
export { addDays, startOfMonth, endOfMonth, startOfYear, endOfYear, startOfWeek } from '../helpers/businessDate';

function readClock() {
    if (!getActivePinia()) return {};
    const system = useAppConfigStore().system;
    return { timeZone: system?.timezone, cutoff: system?.business_day_cutoff };
}

/** Current business date (YYYY-MM-DD) from the tenant timezone and cutoff in /system/context. */
export function businessToday(now = new Date()) {
    return businessTodayFor({ ...readClock(), now });
}

/** YYYY-MM-DD of an instant on the tenant clock. */
export function toTenantDateString(date = new Date()) {
    return toTenantDateStringFor(date, readClock().timeZone);
}

export function useBusinessDate() {
    return { businessToday, toTenantDateString };
}
