'use strict';

// ESC/POS byte builders. Pure: no electron, no I/O.

const ESC = 0x1b;

function toPulseUnit(ms, fallback) {
    const value = Number(ms);
    if (!Number.isFinite(value)) return fallback;
    return Math.min(255, Math.max(1, Math.round(value / 2)));
}

/**
 * "Generate pulse" (ESC p m t1 t2): opens a cash drawer wired to the printer's DK port.
 * pin 2 -> m = 0 (most drawers), pin 5 -> m = 1. t1/t2 are in 2 ms units.
 * Default = 1B 70 00 19 FA (50 ms on, 500 ms off).
 */
function buildDrawerKick({ pin = 2, onMs = 50, offMs = 500 } = {}) {
    const connector = pin === 5 ? 0x01 : 0x00;
    return Buffer.from([ESC, 0x70, connector, toPulseUnit(onMs, 25), toPulseUnit(offMs, 250)]);
}

module.exports = { buildDrawerKick };
