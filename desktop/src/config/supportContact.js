'use strict';

// Resolves the WhatsApp support link shown in the Help menu.
// Pure module: must not require electron.
// The number is never hardcoded in source. It comes from the
// SROOR_SUPPORT_WHATSAPP environment variable or, failing that, from the
// main-process-only `supportWhatsapp` setting (not writable by the renderer).
// Only digits (10-15, international format without "+") are accepted, so no
// arbitrary URL can ever reach shell.openExternal.

const WHATSAPP_NUMBER_PATTERN = /^\d{10,15}$/;

function normalizeWhatsappNumber(raw) {
    if (typeof raw !== 'string') return null;
    const value = raw.trim();
    return WHATSAPP_NUMBER_PATTERN.test(value) ? value : null;
}

function getSupportWhatsappUrl(envValue, storedValue) {
    const number = normalizeWhatsappNumber(envValue) || normalizeWhatsappNumber(storedValue);
    return number ? `https://wa.me/${number}` : null;
}

module.exports = { normalizeWhatsappNumber, getSupportWhatsappUrl };
