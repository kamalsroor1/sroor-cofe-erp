// `:to` must not eat the start of `:total` (the pager read "50tal" in Arabic).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { trans } from '../../resources/js/helpers/trans.js';

test('placeholders sharing a prefix are all replaced', () => {
    globalThis.window = { spaTranslations: { common: { showing: 'Showing :from to :to of :total' } } };
    assert.equal(trans('common.showing', { from: 26, to: 50, total: 224 }), 'Showing 26 to 50 of 224');
    delete globalThis.window;
});
