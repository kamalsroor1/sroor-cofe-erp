// SETG-7: company/legal block printed on invoices (A4 header).
// Values come from the tenant settings exposed in /system/context (`system.*`), with the
// tenant account phone/address as fallback. A missing value is '' so the template hides
// the line — never a placeholder number on a legal document.

const clean = (value) => (typeof value === 'string' ? value.trim() : '');

const firstFilled = (...values) => values.map(clean).find((value) => value !== '') ?? '';

/**
 * @param {{ name?: string, system?: Record<string, unknown> | null, tenant?: Record<string, unknown> | null }} [source]
 */
export function buildCompanyInfo({ name = '', system = null, tenant = null } = {}) {
    const s = system || {};
    const t = tenant || {};

    return {
        name: clean(name),
        subtitle: clean(s.company_subtitle),
        phone: firstFilled(s.company_phone, t.phone),
        address: firstFilled(s.company_address, t.address),
        commercialRegister: clean(s.commercial_register),
        taxNumber: clean(s.tax_registration_no),
    };
}
