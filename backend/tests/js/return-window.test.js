// SET-02: the return-window date maths used for the notice before submit (the server decides for real).
// Run: node --test tests/js/return-window.test.js   (part of `npm run test:js`)
import { test, describe } from 'node:test';
import assert from 'node:assert/strict';

import { normalizeReturnsMaxDays, returnWindowStatus } from '../../resources/js/helpers/returnWindow.js';
import { businessToday } from '../../resources/js/helpers/businessDate.js';

describe('normalizeReturnsMaxDays', () => {
    test('empty means no limit', () => {
        assert.equal(normalizeReturnsMaxDays(null), null);
        assert.equal(normalizeReturnsMaxDays(undefined), null);
        assert.equal(normalizeReturnsMaxDays(''), null);
        assert.equal(normalizeReturnsMaxDays(0), null);
        assert.equal(normalizeReturnsMaxDays('abc'), null);
        assert.equal(normalizeReturnsMaxDays(7), 7);
        assert.equal(normalizeReturnsMaxDays('14'), 14);
    });
});

describe('returnWindowStatus', () => {
    const status = (invoiceDate, today, maxDays) => returnWindowStatus({ invoiceDate, today, maxDays });

    test('no window configured: never limited, never expired', () => {
        const result = status('2020-01-01', '2026-11-12', null);
        assert.equal(result.limited, false);
        assert.equal(result.expired, false);
        assert.equal(result.lastDay, null);
    });

    test('an invoice exactly as old as the window is still inside it', () => {
        const result = status('2026-11-05', '2026-11-12', 7);
        assert.equal(result.limited, true);
        assert.equal(result.ageDays, 7);
        assert.equal(result.expired, false);
        assert.equal(result.daysLeft, 0);
        assert.equal(result.lastDay, '2026-11-12');
    });

    test('one day older is expired', () => {
        const result = status('2026-11-04', '2026-11-12', 7);
        assert.equal(result.ageDays, 8);
        assert.equal(result.expired, true);
        assert.equal(result.daysLeft, 0);
        assert.equal(result.lastDay, '2026-11-11');
    });

    test('a fresh invoice shows the last day and the days left', () => {
        const result = status('2026-11-12', '2026-11-12', 14);
        assert.equal(result.expired, false);
        assert.equal(result.daysLeft, 14);
        assert.equal(result.lastDay, '2026-11-26');
    });

    test('crosses month and year ends with calendar maths', () => {
        const result = status('2026-12-30', '2027-01-02', 3);
        assert.equal(result.ageDays, 3);
        assert.equal(result.expired, false);
        assert.equal(result.lastDay, '2027-01-02');
        assert.equal(status('2026-12-30', '2027-01-03', 3).expired, true);
    });

    test('leap day counts as a day', () => {
        const result = status('2028-02-28', '2028-03-01', 1);
        assert.equal(result.ageDays, 2);
        assert.equal(result.expired, true);
    });

    test('unreadable dates are treated as no window', () => {
        assert.equal(status('', '2026-11-12', 7).limited, false);
        assert.equal(status('2026-11-12', '', 7).limited, false);
    });

    test('works on the business date: before the cutoff, "today" is still yesterday', () => {
        const nowCairo0130 = new Date('2026-11-12T23:30:00Z'); // 01:30 on 2026-11-13 in Cairo (UTC+2)
        const today = businessToday({ timeZone: 'Africa/Cairo', cutoff: '04:00', now: nowCairo0130 });
        assert.equal(today, '2026-11-12');
        // Invoice from 2026-11-05, 7-day window: day 7 is still open until the 04:00 cutoff passes.
        assert.equal(status('2026-11-05', today, 7).expired, false);
        const afterCutoff = businessToday({
            timeZone: 'Africa/Cairo',
            cutoff: '04:00',
            now: new Date('2026-11-13T03:00:00Z'), // 05:00 in Cairo
        });
        assert.equal(afterCutoff, '2026-11-13');
        assert.equal(status('2026-11-05', afterCutoff, 7).expired, true);
    });
});
