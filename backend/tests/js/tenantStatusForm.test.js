// IDEN-3.3: super-admin "change tenant status" form vs ToggleTenantStatusRequest/Action.
// Run from backend/: node --test tests/js/tenantStatusForm.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    TENANT_STATUS_TARGETS,
    TENANT_SUSPENSION_REASONS,
    buildTenantStatusPayload,
    emptyTenantStatusForm,
    mapTenantStatusErrors,
    validateTenantStatusForm,
} from '../../resources/js/helpers/tenantStatusForm.js';

test('super-admin targets exclude active (billing only) and pending (invalid)', () => {
    assert.deepEqual([...TENANT_STATUS_TARGETS], ['trial', 'read_only', 'suspended', 'cancelled']);
    assert.deepEqual([...TENANT_SUSPENSION_REASONS], ['non_payment', 'violation', 'customer_request', 'other']);
});

test('the form starts on the current status only when it is a valid target', () => {
    assert.equal(emptyTenantStatusForm('trial').status, 'trial');
    assert.equal(emptyTenantStatusForm('active').status, '');
    assert.equal(emptyTenantStatusForm(null).status, '');
    assert.deepEqual(emptyTenantStatusForm('suspended'), {
        status: 'suspended',
        extend_days: 0,
        reason: '',
        note: '',
    });
});

test('suspension requires a reason; cancellation does not', () => {
    assert.deepEqual(validateTenantStatusForm({ status: 'suspended', reason: '' }), {
        reason: 'subscription.errors.suspension_reason_required',
    });
    assert.deepEqual(validateTenantStatusForm({ status: 'suspended', reason: 'violation' }), {});
    assert.deepEqual(validateTenantStatusForm({ status: 'cancelled', reason: '' }), {});
    assert.deepEqual(validateTenantStatusForm({ status: 'active' }), {
        status: 'super.tenant_status.status_required',
    });
});

test('extend_days is validated only for trial', () => {
    assert.deepEqual(validateTenantStatusForm({ status: 'trial', extend_days: '-1' }), {
        extend_days: 'super.tenant_status.extend_days_invalid',
    });
    assert.deepEqual(validateTenantStatusForm({ status: 'trial', extend_days: '3651' }), {
        extend_days: 'super.tenant_status.extend_days_invalid',
    });
    assert.deepEqual(validateTenantStatusForm({ status: 'trial', extend_days: '30' }), {});
    assert.deepEqual(validateTenantStatusForm({ status: 'read_only', extend_days: '-5' }), {});
});

test('payload sends extend_days only with trial and reason only with suspended/cancelled', () => {
    assert.deepEqual(buildTenantStatusPayload({ status: 'trial', extend_days: '14', reason: 'other', note: '  ' }), {
        status: 'trial',
        extend_days: 14,
    });
    assert.deepEqual(
        buildTenantStatusPayload({ status: 'suspended', extend_days: 30, reason: 'non_payment', note: ' late ' }),
        { status: 'suspended', reason: 'non_payment', note: 'late' }
    );
    assert.deepEqual(
        buildTenantStatusPayload({ status: 'read_only', extend_days: 30, reason: 'violation', note: '' }),
        {
            status: 'read_only',
        }
    );
    assert.deepEqual(buildTenantStatusPayload({ status: 'cancelled', reason: '', note: '' }), { status: 'cancelled' });
});

const httpError = (status, data) => ({ response: { status, data } });

test('422 validation errors land under their fields', () => {
    const mapped = mapTenantStatusErrors(
        httpError(422, {
            message: 'invalid',
            errors: { reason: ['Pick a reason'], extend_days: ['Too many'], other: ['ignored'] },
        })
    );
    assert.deepEqual(mapped, { reason: 'Pick a reason', extend_days: 'Too many' });
});

test('lifecycle error codes map to the right field', () => {
    assert.deepEqual(
        mapTenantStatusErrors(
            httpError(422, { message: 'Choose', error_code: 'subscription.suspension_reason_required' })
        ),
        { reason: 'Choose' }
    );
    assert.deepEqual(
        mapTenantStatusErrors(
            httpError(409, {
                message: 'No change',
                error_code: 'subscription.invalid_transition',
                details: { from: 'trial', to: 'trial', actor: 'super_admin' },
            })
        ),
        { extend_days: 'No change' }
    );
    assert.deepEqual(
        mapTenantStatusErrors(
            httpError(409, {
                message: 'Cannot activate',
                error_code: 'subscription.invalid_transition',
                details: { from: 'suspended', to: 'active', actor: 'super_admin' },
            })
        ),
        { status: 'Cannot activate' }
    );
    assert.deepEqual(
        mapTenantStatusErrors(httpError(409, { message: 'Changed', error_code: 'subscription.status_conflict' })),
        { status: 'Changed' }
    );
});

test('errors outside the form are left to the caller (toast)', () => {
    assert.deepEqual(mapTenantStatusErrors(httpError(500, { message: 'boom' })), {});
    assert.deepEqual(
        mapTenantStatusErrors(httpError(403, { message: 'no', error_code: 'central_auth.step_up_required' })),
        {}
    );
    assert.deepEqual(mapTenantStatusErrors({ message: 'Network Error' }), {});
});
