// SELL-01: forced password change helpers and the WhatsApp hand-over text.
// Run from backend/: node --test tests/js/password-change.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    PASSWORD_CHANGE_REQUIRED,
    isPasswordChangeRequired,
    isThrottled,
    mustChangeFromPayload,
    passwordChangeFieldErrors,
    safeInternalRedirect,
} from '../../resources/js/helpers/passwordChange.js';
import { buildWhatsAppUrl, fillPlaceholders, normalizeWhatsAppPhone } from '../../resources/js/helpers/whatsappLink.js';

const forbidden = (error_code) => ({ response: { status: 403, data: { success: false, error_code } } });

test('only the 403 with the password-change error code is a forced-change signal', () => {
    assert.equal(isPasswordChangeRequired(forbidden(PASSWORD_CHANGE_REQUIRED)), true);
    assert.equal(isPasswordChangeRequired(forbidden('store_access_denied')), false);
    assert.equal(
        isPasswordChangeRequired({ response: { status: 401, data: { error_code: PASSWORD_CHANGE_REQUIRED } } }),
        false
    );
    assert.equal(isPasswordChangeRequired(new Error('network')), false);
});

test('the flag is read from the top level or from the user of a login / me payload', () => {
    assert.equal(mustChangeFromPayload({ must_change_password: true, user: {} }), true);
    assert.equal(mustChangeFromPayload({ user: { must_change_password: true } }), true);
    assert.equal(mustChangeFromPayload({ user: { must_change_password: false } }), false);
    assert.equal(mustChangeFromPayload({ user: {} }), false);
    assert.equal(mustChangeFromPayload(undefined), false);
});

test('safeInternalRedirect keeps same-origin paths only and never loops back to the gate', () => {
    assert.equal(safeInternalRedirect('/pos'), '/pos');
    assert.equal(safeInternalRedirect('/invoices?page=2#x'), '/invoices?page=2#x');
    assert.equal(safeInternalRedirect('https://evil.example'), null);
    assert.equal(safeInternalRedirect('//evil.example'), null);
    assert.equal(safeInternalRedirect('/\\evil.example'), null);
    assert.equal(safeInternalRedirect('/login'), null);
    assert.equal(safeInternalRedirect('/change-password'), null);
    assert.equal(safeInternalRedirect(undefined), null);
    assert.equal(safeInternalRedirect(['/pos']), null);
});

test('field errors of a 422 are mapped per field (first message) and 429 is recognised', () => {
    const error = {
        response: {
            status: 422,
            data: { errors: { current_password: ['wrong'], password: ['short', 'other'] } },
        },
    };
    assert.deepEqual(passwordChangeFieldErrors(error), {
        current_password: 'wrong',
        password: 'short',
        password_confirmation: '',
    });
    assert.equal(isThrottled({ response: { status: 429 } }), true);
    assert.equal(isThrottled(error), false);
});

test('Egyptian local numbers get the country code and symbols are stripped', () => {
    assert.equal(normalizeWhatsAppPhone('010 1234 5678'), '201012345678');
    assert.equal(normalizeWhatsAppPhone('+20 101 234 5678'), '201012345678');
    assert.equal(normalizeWhatsAppPhone('00201012345678'), '201012345678');
    assert.equal(normalizeWhatsAppPhone(''), '');
    assert.equal(normalizeWhatsAppPhone(null), '');
});

test('the WhatsApp link encodes the message and falls back to the chooser without a phone', () => {
    const text = 'Login: a@b.c\nPassword: x&y=1';
    const url = buildWhatsAppUrl('01012345678', text);
    assert.ok(url.startsWith('https://wa.me/201012345678?text='));
    assert.equal(decodeURIComponent(url.split('text=')[1]), text);
    assert.ok(buildWhatsAppUrl('', text).startsWith('https://wa.me/?text='));
});

test('placeholders are replaced everywhere, longest names first-safe', () => {
    assert.equal(fillPlaceholders(':login / :password / :login', { login: 'a', password: 'p$1' }), 'a / p$1 / a');
});
