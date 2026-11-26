import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { URL } from 'node:url';

const read = (p) => readFileSync(new URL(`../../resources/js/${p}`, import.meta.url), 'utf8');

test('appConfig store exposes refreshActiveShift that re-reads the bootstrap context and never throws', () => {
    const src = read('stores/appConfig.js');
    const body = src.slice(src.indexOf('async refreshActiveShift()'));
    assert.match(body, /await this\.fetchBootstrapContext\(\)/);
    assert.match(body.slice(0, 200), /try \{[\s\S]*catch/);
});

for (const file of ['Composables/useDailyJournal.js', 'Composables/POS/usePosShift.js']) {
    test(`${file} refreshes the shared shift state after both open and close succeed`, () => {
        const src = read(file);
        assert.equal(src.match(/appConfigStore\.refreshActiveShift\(\)/g)?.length, 2);
    });
}
