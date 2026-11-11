import { test } from 'node:test';
import assert from 'node:assert/strict';
import { useLoadError } from '../../resources/js/Composables/useLoadError.js';

test('useLoadError initializes with clean default state', () => {
    const { error, errorMessage, statusCode, canRetry } = useLoadError();

    assert.equal(error.value, false);
    assert.equal(errorMessage.value, '');
    assert.equal(statusCode.value, null);
    assert.equal(canRetry.value, true);
});

test('useLoadError sets canRetry to false when statusCode is 403 (Forbidden)', () => {
    const { error, errorMessage, statusCode, canRetry, setError } = useLoadError();

    const err403 = {
        response: {
            status: 403,
            data: { message: 'You do not have permission to view this resource.' },
        },
    };

    setError(err403);

    assert.equal(error.value, true);
    assert.equal(statusCode.value, 403);
    assert.equal(canRetry.value, false);
    assert.equal(errorMessage.value, 'You do not have permission to view this resource.');
});

test('useLoadError sets canRetry to true for 500 internal server error', () => {
    const { error, errorMessage, statusCode, canRetry, setError } = useLoadError();

    const err500 = {
        response: {
            status: 500,
            data: { message: 'Server error' },
        },
    };

    setError(err500);

    assert.equal(error.value, true);
    assert.equal(statusCode.value, 500);
    assert.equal(canRetry.value, true);
});

test('useLoadError sets canRetry to true for 404 not found error', () => {
    const { error, statusCode, canRetry, setError } = useLoadError();

    setError({ status: 404, message: 'Not found' });

    assert.equal(error.value, true);
    assert.equal(statusCode.value, 404);
    assert.equal(canRetry.value, true);
});

test('useLoadError sets canRetry to true for network errors without status code', () => {
    const { error, statusCode, canRetry, setError } = useLoadError();

    setError(new Error('Network error'));

    assert.equal(error.value, true);
    assert.equal(statusCode.value, null);
    assert.equal(canRetry.value, true);
});

test('clearError resets all error state including statusCode and canRetry', () => {
    const { error, errorMessage, statusCode, canRetry, setError, clearError } = useLoadError();

    setError({ response: { status: 403 } });
    assert.equal(canRetry.value, false);

    clearError();

    assert.equal(error.value, false);
    assert.equal(errorMessage.value, '');
    assert.equal(statusCode.value, null);
    assert.equal(canRetry.value, true);
});
