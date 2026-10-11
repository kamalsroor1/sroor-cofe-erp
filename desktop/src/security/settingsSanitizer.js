'use strict';

// Allow-list sanitizer for settings coming from the renderer via
// `config:save-settings`. Pure module: must not require electron.
// kioskMode, windowBounds, centralUrl and updateState are main-process-only and
// are never accepted from the page.

const urlPolicy = require('./urlPolicy');

const MAX_PRINTER_NAME_LENGTH = 128;
const PAPER_WIDTHS = new Set(['58mm', '80mm']);
const THEMES = new Set(['dark', 'light']);
// APP-5: the drawer pin is a per-device choice; only these exact numbers are accepted.
const DRAWER_PINS = new Set([2, 5]);

function has(input, key) {
    return Object.prototype.hasOwnProperty.call(input, key);
}

function sanitizeSettings(input, options) {
    if (input === null || typeof input !== 'object' || Array.isArray(input)) {
        return { ok: false, error: 'invalid_settings' };
    }

    const settings = {};

    if (has(input, 'serverUrl')) {
        const raw = input.serverUrl;
        if (raw === '') {
            settings.serverUrl = '';
        } else {
            const safe = urlPolicy.sanitizeServerUrl(raw, options);
            if (!safe) return { ok: false, error: 'invalid_settings' };
            settings.serverUrl = safe;
        }
    }

    if (has(input, 'tenantId')) {
        const raw = input.tenantId;
        if (raw === '') {
            settings.tenantId = '';
        } else {
            const slug = urlPolicy.normalizeTenantSlug(raw);
            if (!slug) return { ok: false, error: 'invalid_settings' };
            settings.tenantId = slug;
        }
    }

    if (
        has(input, 'thermalPrinterName') &&
        typeof input.thermalPrinterName === 'string' &&
        input.thermalPrinterName.length <= MAX_PRINTER_NAME_LENGTH
    ) {
        settings.thermalPrinterName = input.thermalPrinterName;
    }

    if (has(input, 'paperWidth') && PAPER_WIDTHS.has(input.paperWidth)) {
        settings.paperWidth = input.paperWidth;
    }

    if (has(input, 'autoOpenDrawerOnCash') && typeof input.autoOpenDrawerOnCash === 'boolean') {
        settings.autoOpenDrawerOnCash = input.autoOpenDrawerOnCash;
    }

    if (has(input, 'theme') && THEMES.has(input.theme)) {
        settings.theme = input.theme;
    }

    if (has(input, 'cashDrawerPin')) {
        if (!DRAWER_PINS.has(input.cashDrawerPin)) return { ok: false, error: 'invalid_settings' };
        settings.cashDrawerPin = input.cashDrawerPin;
    }

    return { ok: true, settings };
}

module.exports = { sanitizeSettings };
