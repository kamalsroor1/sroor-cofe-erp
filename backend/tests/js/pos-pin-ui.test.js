import { test } from 'node:test';
import assert from 'node:assert/strict';

function validatePosPin(pin, confirmation) {
    const trimmed = (pin || '').trim();
    const trimmedConfirmation = (confirmation || '').trim();

    if (!trimmed) {
        return { valid: false, error: 'pin_required' };
    }
    if (!/^\d{4,6}$/.test(trimmed)) {
        return { valid: false, error: 'pos_pin_format' };
    }
    if (trimmed !== trimmedConfirmation) {
        return { valid: false, error: 'pos_pin_mismatch' };
    }
    return { valid: true, pin: trimmed };
}

function buildUserPinPayload(pin) {
    return { pin: pin ? String(pin).trim() : null };
}

function buildProfilePinPayload(pin, currentPassword) {
    return {
        pin: pin ? String(pin).trim() : null,
        current_password: currentPassword ? String(currentPassword).trim() : '',
    };
}

test('validatePosPin accepts valid 4 to 6 digit numeric PINs when confirmed', () => {
    assert.deepEqual(validatePosPin('1234', '1234'), { valid: true, pin: '1234' });
    assert.deepEqual(validatePosPin('12345', '12345'), { valid: true, pin: '12345' });
    assert.deepEqual(validatePosPin('123456', '123456'), { valid: true, pin: '123456' });
});

test('validatePosPin rejects PINs with fewer than 4 or more than 6 digits', () => {
    assert.equal(validatePosPin('12', '12').valid, false);
    assert.equal(validatePosPin('12', '12').error, 'pos_pin_format');
    assert.equal(validatePosPin('123', '123').valid, false);
    assert.equal(validatePosPin('1234567', '1234567').valid, false);
});

test('validatePosPin rejects non-digit characters', () => {
    assert.equal(validatePosPin('12a4', '12a4').valid, false);
    assert.equal(validatePosPin('abcd', 'abcd').valid, false);
    assert.equal(validatePosPin('12 45', '12 45').valid, false);
});

test('validatePosPin rejects mismatched confirmation', () => {
    const res = validatePosPin('1234', '5678');
    assert.equal(res.valid, false);
    assert.equal(res.error, 'pos_pin_mismatch');
});

test('buildUserPinPayload formats pin correctly or sends null to clear', () => {
    assert.deepEqual(buildUserPinPayload('123456'), { pin: '123456' });
    assert.deepEqual(buildUserPinPayload(null), { pin: null });
    assert.deepEqual(buildUserPinPayload(''), { pin: null });
});

test('buildProfilePinPayload includes current_password and formatted pin', () => {
    assert.deepEqual(buildProfilePinPayload('4321', 'secretPass'), {
        pin: '4321',
        current_password: 'secretPass',
    });
    assert.deepEqual(buildProfilePinPayload(null, 'secretPass'), {
        pin: null,
        current_password: 'secretPass',
    });
});
