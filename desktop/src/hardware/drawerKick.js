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

function createDrawerKicker({ listPrinters, getConfiguredPrinter, sendRaw, printDriverPulse, kickOptions }) {
    async function kick(requestedPrinter) {
        try {
            const wanted = pickName(requestedPrinter) || pickName(getConfiguredPrinter());
            const target = resolveTargetPrinter(await listPrinters(), wanted);
            if (target.printerName === null) {
                return { success: false, error: target.reason };
            }

            // The system default has no name we can open raw; only the driver path can reach it.
            if (target.printerName !== '') {
                const raw = await sendRaw(target.printerName, buildDrawerKick(kickOptions));
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

module.exports = { createDrawerKicker };
