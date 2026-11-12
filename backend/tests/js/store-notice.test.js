// ROLE-01: no-branch notice helpers. Run from backend/: node --test tests/js/store-notice.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    NO_STORE_ASSIGNED,
    isNoStoreAssigned,
    storeNoticeFromError,
    storeNoticeFromPayload,
} from '../../resources/js/helpers/storeNotice.js';

const forbidden = (error_code, message = 'msg') => ({ response: { status: 403, data: { error_code, message } } });

test('a login / me payload with the no-branch notice yields it, anything else yields null', () => {
    const notice = { error_code: NO_STORE_ASSIGNED, message: 'Ask the manager' };
    assert.deepEqual(storeNoticeFromPayload({ store_notice: notice }), notice);
    assert.equal(storeNoticeFromPayload({ store_notice: null }), null);
    assert.equal(storeNoticeFromPayload({}), null);
    assert.equal(storeNoticeFromPayload(undefined), null);
    assert.equal(storeNoticeFromPayload({ store_notice: { error_code: 'other', message: 'x' } }), null);
    assert.equal(storeNoticeFromPayload({ store_notice: { error_code: NO_STORE_ASSIGNED, message: '' } }), null);
});

test('only the 403 with the no_store_assigned code is the branch-less signal', () => {
    assert.equal(isNoStoreAssigned(forbidden(NO_STORE_ASSIGNED)), true);
    assert.equal(isNoStoreAssigned(forbidden('store_access_denied')), false);
    assert.equal(isNoStoreAssigned({ response: { status: 401, data: { error_code: NO_STORE_ASSIGNED } } }), false);
    assert.equal(isNoStoreAssigned(new Error('network')), false);
});

test('the notice of such a 403 carries the server message; other errors carry none', () => {
    assert.deepEqual(storeNoticeFromError(forbidden(NO_STORE_ASSIGNED, 'No branch')), {
        error_code: NO_STORE_ASSIGNED,
        message: 'No branch',
    });
    assert.equal(storeNoticeFromError(forbidden('store_access_denied')), null);
    assert.equal(storeNoticeFromError(forbidden(NO_STORE_ASSIGNED, '')), null);
});
