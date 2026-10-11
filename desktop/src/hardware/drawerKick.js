'use strict';

// Cash drawer kick. Pure: printers, raw delivery and the driver job are injected.
//   1. ESC/POS pulse sent RAW to the receipt printer (works with any DK-port drawer)
//   2. fallback: a tiny silent driver job, for queues that refuse RAW data and whose
//      driver is set to "open drawer after print"
// Never opens a dialog and never redirects the pulse to a different, unknown printer.

const { buildDrawerKick } = require('./escpos');
const { resolveTargetPrinter } = require('./printFallback');

function pickName(value) {
    return typeof value === 'string' && value.trim() !== '' ? value : '';
}

const DRAWER_PINS = Object.freeze([2, 5]);
const DEFAULT_DRAWER_PIN = 2;

/** Saved per-device pin; anything but exactly 2 or 5 falls back to pin 2. */
function normalizeDrawerPin(value) {
    return DRAWER_PINS.includes(value) ? value : DEFAULT_DRAWER_PIN;
}

// getKickOptions() is read on every kick, so a pin changed in the settings applies immediately.
function createDrawerKicker({
    listPrinters,
    getConfiguredPrinter,
    sendRaw,
    printDriverPulse,
    kickOptions,
    getKickOptions,
}) {
    function currentKickOptions() {
        const options = typeof getKickOptions === 'function' ? getKickOptions() : kickOptions;
        const base = options && typeof options === 'object' ? options : {};
        return { ...base, pin: normalizeDrawerPin(base.pin) };
    }

    async function kick(requestedPrinter) {
        try {
            const wanted = pickName(requestedPrinter) || pickName(getConfiguredPrinter());
            const target = resolveTargetPrinter(await listPrinters(), wanted);
            if (target.printerName === null) {
                return { success: false, error: target.reason };
            }

            // The system default has no name we can open raw; only the driver path can reach it.
            if (target.printerName !== '') {
                const raw = await sendRaw(target.printerName, buildDrawerKick(currentKickOptions()));
                if (raw && raw.success) {
                    return { success: true, method: 'escpos', printerName: target.printerName };
                }
            }

            const driver = await printDriverPulse(target.printerName);
            if (driver && driver.success) {
                return { success: true, method: 'driver', printerName: target.printerName };
            }
            return { success: false, error: 'drawer_failed' };
        } catch {
            return { success: false, error: 'drawer_failed' };
        }
    }

    return { kick };
}

module.exports = { createDrawerKicker, normalizeDrawerPin, DRAWER_PINS, DEFAULT_DRAWER_PIN };
