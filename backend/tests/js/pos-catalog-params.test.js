import { test } from 'node:test';
import assert from 'node:assert/strict';
import { catalogParams, mergePage } from '../../resources/js/helpers/posCatalog.js';

test('tabs map to server filters', () => {
    assert.deepEqual(catalogParams('favorites', ''), { sort: 'pos_sales_count', per_page: 50 });
    assert.deepEqual(catalogParams('newest', ''), { sort: 'newest' });
    assert.deepEqual(catalogParams('in_stock', ''), { only_in_stock: 1 });
    assert.deepEqual(catalogParams('low_stock', ''), { only_low_stock: 1 });
    assert.deepEqual(catalogParams(null, ''), {});
    assert.deepEqual(catalogParams(7, ''), { category_id: 7 });
});

test('a search term wins over the tab and is trimmed', () => {
    assert.deepEqual(catalogParams('in_stock', '  tea '), { search: 'tea' });
});

test('mergePage replaces on page 1 and appends only unseen rows after', () => {
    const first = [{ id: 1 }, { id: 2 }];
    assert.deepEqual(mergePage([{ id: 9 }], first, 1), first);
    assert.deepEqual(mergePage(first, [{ id: 2 }, { id: 3 }], 2), [{ id: 1 }, { id: 2 }, { id: 3 }]);
});
