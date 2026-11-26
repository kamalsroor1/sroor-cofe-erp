// The peak-hours card printed the raw ":hour" because it called the key without params.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { trans } from '../../resources/js/helpers/trans.js';

const here = dirname(fileURLToPath(import.meta.url));
const card = readFileSync(resolve(here, '../../resources/js/Components/Dashboard/DashboardPeakHours.vue'), 'utf8');

test('peak hour badge passes the hour param', () => {
    assert.match(card, /peak_hour_badge'\s*,\s*\{\s*hour:/);
});

for (const lang of ['ar', 'en']) {
    test(`dashboard peak_hour_badge (${lang}) has a single clean :hour placeholder`, () => {
        const php = readFileSync(resolve(here, `../../lang/${lang}/dashboard.php`), 'utf8');
        const m = php.match(/'peak_hour_badge'\s*=>\s*'([^']*)'/);
        assert.ok(m);
        assert.deepEqual(m[1].match(/:\w+/g), [':hour']);
        assert.doesNotMatch(m[1], /:\w+:/);
        globalThis.window = { spaTranslations: { dashboard: { peak_hour_badge: m[1] } } };
        assert.ok(!trans('dashboard.peak_hour_badge', { hour: '1 ص' }).includes(':hour'));
        delete globalThis.window;
    });
}
