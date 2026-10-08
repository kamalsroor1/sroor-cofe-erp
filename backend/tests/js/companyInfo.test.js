// SETG-7: the A4 invoice header must print the tenant's real legal data or nothing —
// never placeholder phone / commercial-register / tax numbers.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildCompanyInfo } from '../../resources/js/helpers/companyInfo.js';

const FAKE_PLACEHOLDERS = ['01012345678', '123456', '987-654-321'];

test('empty settings produce empty legal fields, never placeholders', () => {
    const info = buildCompanyInfo({ name: 'Shop', system: {}, tenant: null });

    assert.equal(info.name, 'Shop');
    assert.equal(info.subtitle, '');
    assert.equal(info.phone, '');
    assert.equal(info.address, '');
    assert.equal(info.commercialRegister, '');
    assert.equal(info.taxNumber, '');
    for (const value of Object.values(info)) {
        assert.ok(!FAKE_PLACEHOLDERS.includes(value), `placeholder leaked: ${value}`);
    }
});

test('missing arguments are tolerated', () => {
    const info = buildCompanyInfo();

    assert.equal(info.name, '');
    assert.equal(info.phone, '');
    assert.equal(info.taxNumber, '');
});

test('tenant settings values are used', () => {
    const info = buildCompanyInfo({
        name: 'Shop',
        system: {
            company_subtitle: 'Retail',
            company_phone: ' 01000000500 ',
            company_address: 'Cairo',
            commercial_register: 'CR-77',
            tax_registration_no: '100-200-300',
        },
        tenant: { phone: '01000000999', address: 'Giza' },
    });

    assert.equal(info.subtitle, 'Retail');
    assert.equal(info.phone, '01000000500');
    assert.equal(info.address, 'Cairo');
    assert.equal(info.commercialRegister, 'CR-77');
    assert.equal(info.taxNumber, '100-200-300');
});

test('tenant account phone/address are the fallback when settings are empty', () => {
    const info = buildCompanyInfo({
        name: 'Shop',
        system: { company_phone: '', company_address: '   ' },
        tenant: { phone: '01000000999', address: 'Giza' },
    });

    assert.equal(info.phone, '01000000999');
    assert.equal(info.address, 'Giza');
});

test('non-string values are ignored', () => {
    const info = buildCompanyInfo({
        name: 'Shop',
        system: { commercial_register: 123, tax_registration_no: null },
        tenant: { phone: { x: 1 } },
    });

    assert.equal(info.commercialRegister, '');
    assert.equal(info.taxNumber, '');
    assert.equal(info.phone, '');
});
