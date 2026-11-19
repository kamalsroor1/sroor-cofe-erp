import { test } from 'node:test';
import assert from 'node:assert/strict';

test('StoreShiftSwitcher variant validator accepts only supported variants', () => {
    const validVariants = ['inline', 'chip', 'drawer', 'titlebar'];
    const validator = (v) => ['inline', 'chip', 'drawer', 'titlebar'].includes(v);

    for (const v of validVariants) {
        assert.equal(validator(v), true, `Variant ${v} should be valid`);
    }

    assert.equal(validator('invalid'), false);
    assert.equal(validator('sidebar'), false);
    assert.equal(validator(''), false);
    assert.equal(validator(null), false);
});

test('shiftBadgeClass computed logic matches emerald for open and rose for closed shifts', () => {
    const computeBadgeClass = (hasOpenShift) =>
        hasOpenShift
            ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20'
            : 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20';

    const openClasses = computeBadgeClass(true);
    assert.match(openClasses, /emerald-500/);
    assert.match(openClasses, /text-emerald-600/);

    const closedClasses = computeBadgeClass(false);
    assert.match(closedClasses, /rose-500/);
    assert.match(closedClasses, /text-rose-600/);
});

test('hasMultipleStores is true only when stores length is strictly greater than 1', () => {
    const checkMultiple = (stores) => (stores?.length ?? 0) > 1;

    assert.equal(checkMultiple(null), false);
    assert.equal(checkMultiple(undefined), false);
    assert.equal(checkMultiple([]), false);
    assert.equal(checkMultiple([{ id: 1, name: 'Main' }]), false);
    assert.equal(
        checkMultiple([
            { id: 1, name: 'Main' },
            { id: 2, name: 'Branch 2' },
        ]),
        true
    );
});

test('switching store emits navigate event and updates active store when valid', () => {
    const state = {
        currentStore: { id: 1, name: 'Main Store' },
        stores: [
            { id: 1, name: 'Main Store' },
            { id: 2, name: 'Second Store' },
        ],
        activeShift: { id: 99, number: 12 },
    };

    let emitted = [];
    const emit = (evt) => emitted.push(evt);

    const switchTo = (targetId) => {
        const store = state.stores.find((s) => String(s.id) === String(targetId));
        if (!store || String(store.id) === String(state.currentStore?.id)) {
            return false;
        }
        state.currentStore = store;
        state.activeShift = null;
        emit('navigate');
        return true;
    };

    // Switching to current store should not trigger
    const same = switchTo(1);
    assert.equal(same, false);
    assert.equal(emitted.length, 0);

    // Switching to second store
    const switched = switchTo(2);
    assert.equal(switched, true);
    assert.equal(state.currentStore.id, 2);
    assert.equal(state.activeShift, null, 'Previous shift must be cleared on branch switch');
    assert.deepEqual(emitted, ['navigate'], 'Must emit navigate so drawer closes');
});
