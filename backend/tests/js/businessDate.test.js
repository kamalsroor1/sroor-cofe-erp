// FE-06: tenant-clock calendar dates (mirrors backend TenantClock::businessDate()).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    addDays,
    businessToday,
    endOfMonth,
    endOfYear,
    startOfMonth,
    startOfWeek,
    startOfYear,
    todayInTenantTz,
    toTenantDateString,
} from '../../resources/js/helpers/businessDate.js';

test('toTenantDateString uses the tenant zone, not UTC', () => {
    const instant = new Date('2026-10-10T22:30:00Z'); // 01:30 on the 11th in Cairo (UTC+3 in DST)
    assert.equal(toTenantDateString(instant, 'UTC'), '2026-10-10');
    assert.equal(toTenantDateString(instant, 'Africa/Cairo'), '2026-10-11');
    assert.equal(todayInTenantTz('Africa/Cairo', instant), '2026-10-11');
    assert.equal(toTenantDateString('not a date', 'UTC'), '');
});

test('businessToday: before the cutoff still belongs to yesterday', () => {
    const at0200Cairo = new Date('2026-10-10T23:00:00Z'); // 02:00 on the 11th (UTC+3)
    const tz = 'Africa/Cairo';
    assert.equal(businessToday({ timeZone: tz, cutoff: '04:00', now: at0200Cairo }), '2026-10-10');
    assert.equal(businessToday({ timeZone: tz, cutoff: '00:00', now: at0200Cairo }), '2026-10-11');
    assert.equal(businessToday({ timeZone: tz, cutoff: '02:00', now: at0200Cairo }), '2026-10-11');
    assert.equal(businessToday({ timeZone: tz, now: at0200Cairo }), '2026-10-11');
    assert.equal(businessToday({ timeZone: tz, cutoff: '04:00', now: new Date('2026-10-11T01:00:00Z') }), '2026-10-11');
});

test('businessToday crosses a month boundary', () => {
    const now = new Date('2026-11-01T00:30:00Z');
    assert.equal(businessToday({ timeZone: 'UTC', cutoff: '03:00', now }), '2026-10-31');
});

test('addDays is pure calendar math', () => {
    assert.equal(addDays('2026-03-01', -1), '2026-02-28');
    assert.equal(addDays('2028-03-01', -1), '2028-02-29');
    assert.equal(addDays('2026-12-31', 1), '2027-01-01');
    assert.equal(addDays('2026-10-25', 7), '2026-11-01');
    assert.equal(addDays('garbage', 1), '');
});

test('month/year/week boundaries', () => {
    assert.equal(startOfMonth('2026-10-17'), '2026-10-01');
    assert.equal(endOfMonth('2026-02-10'), '2026-02-28');
    assert.equal(endOfMonth('2028-02-10'), '2028-02-29');
    assert.equal(startOfYear('2026-10-17'), '2026-01-01');
    assert.equal(endOfYear('2026-10-17'), '2026-12-31');
    assert.equal(startOfWeek('2026-11-10', 1), '2026-11-09'); // Tuesday -> Monday
    assert.equal(startOfWeek('2026-11-08', 1), '2026-11-02'); // Sunday -> previous Monday
    assert.equal(startOfWeek('2026-11-10', 6), '2026-11-07'); // Saturday-first
});
