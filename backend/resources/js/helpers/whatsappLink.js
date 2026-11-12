// Digits-only international number for wa.me. Local Egyptian numbers (01xxxxxxxxx) get the 20 prefix.
export function normalizeWhatsAppPhone(phone, defaultCountryCode = '20') {
    let digits = String(phone ?? '').replace(/\D/g, '');
    if (!digits) return '';
    if (digits.startsWith('00')) digits = digits.slice(2);
    else if (digits.startsWith('0')) digits = defaultCountryCode + digits.slice(1);
    return digits;
}

export function buildWhatsAppUrl(phone, text) {
    const digits = normalizeWhatsAppPhone(phone);
    const query = `text=${encodeURIComponent(text)}`;
    return digits ? `https://wa.me/${digits}?${query}` : `https://wa.me/?${query}`;
}

export function fillPlaceholders(template, replace = {}) {
    return Object.keys(replace).reduce(
        (text, name) => text.split(`:${name}`).join(String(replace[name])),
        String(template ?? '')
    );
}
