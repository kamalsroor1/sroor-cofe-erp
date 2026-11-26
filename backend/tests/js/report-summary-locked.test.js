// Card #216 (demo trial 2026-11-26): a plan without reports.advanced gets a P&L summary without
// total_cogs / margin_percentage / net_profit; the sales tab showed them as 0 instead of locked.
// Run: node --test tests/js/report-summary-locked.test.js   (part of `npm run test:js`)
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { isStrippedFigure } from '../../resources/js/helpers/reportSummary.js';

const basicPlanSummary = { total_sales: '12530.500', gross_profit: '4425.500', total_expenses: '300.000' };

test('a key the server stripped is reported as locked, not zero', () => {
    assert.equal(isStrippedFigure(basicPlanSummary, 'total_cogs'), true);
    assert.equal(isStrippedFigure(basicPlanSummary, 'margin_percentage'), true);
    assert.equal(isStrippedFigure(basicPlanSummary, 'net_profit'), true);
});

test('a present key is shown even when its value is zero', () => {
    const pro = { ...basicPlanSummary, total_cogs: '0.000', net_profit: '0.000' };
    assert.equal(isStrippedFigure(pro, 'total_cogs'), false);
    assert.equal(isStrippedFigure(pro, 'net_profit'), false);
    assert.equal(isStrippedFigure(basicPlanSummary, 'gross_profit'), false);
});

test('an empty or missing summary is not treated as locked', () => {
    assert.equal(isStrippedFigure({}, 'total_cogs'), false);
    assert.equal(isStrippedFigure(null, 'total_cogs'), false);
    assert.equal(isStrippedFigure(undefined, 'net_profit'), false);
});
