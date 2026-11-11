// RFC 6238 TOTP (SHA-1, 6 digits, 30 s), the parameters Fortify / Google2FA use for the
// platform console 2FA. node:crypto only: no npm dependency for a dozen lines of HMAC.
import { createHmac } from 'node:crypto';

const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
export const TOTP_PERIOD_SECONDS = 30;

/** RFC 4648 base32 (no padding, case-insensitive) -> bytes. */
export function base32Decode(input) {
    const clean = String(input).replace(/=+$/u, '').replace(/\s+/gu, '').toUpperCase();
    let bits = 0;
    let value = 0;
    const bytes = [];
    for (const char of clean) {
        const index = BASE32_ALPHABET.indexOf(char);
        if (index === -1) throw new Error(`invalid base32 character "${char}"`);
        value = (value << 5) | index;
        bits += 5;
        if (bits >= 8) {
            bytes.push((value >>> (bits - 8)) & 0xff);
            bits -= 8;
        }
    }
    return Buffer.from(bytes);
}

/** HOTP (RFC 4226) for one counter value. */
export function hotp(secretBase32, counter, digits = 6) {
    const buffer = Buffer.alloc(8);
    buffer.writeBigUInt64BE(BigInt(counter));
    const hmac = createHmac('sha1', base32Decode(secretBase32)).update(buffer).digest();
    const offset = hmac[hmac.length - 1] & 0x0f;
    const binary =
        ((hmac[offset] & 0x7f) << 24) |
        ((hmac[offset + 1] & 0xff) << 16) |
        ((hmac[offset + 2] & 0xff) << 8) |
        (hmac[offset + 3] & 0xff);
    return String(binary % 10 ** digits).padStart(digits, '0');
}

export function currentTimeStep(nowMs = Date.now()) {
    return Math.floor(nowMs / 1000 / TOTP_PERIOD_SECONDS);
}

/**
 * Hands out TOTP codes that the server has not seen yet.
 *
 * Fortify refuses a code a second time inside its window (replay cache), and the console accepts
 * window = 1 (the previous, current and next 30 s step). So each call returns the code of a time
 * step not used before, preferring the current step, then the next and the previous one. When all
 * three are spent it waits (polling, no fixed sleep) for the clock to reach a fresh step.
 */
export function createTotpIssuer(secretBase32) {
    const usedSteps = new Set();

    return {
        async next() {
            for (;;) {
                const now = currentTimeStep();
                const step = [now, now + 1, now - 1].find((candidate) => !usedSteps.has(candidate));
                if (step !== undefined) {
                    usedSteps.add(step);
                    return hotp(secretBase32, step);
                }
                await new Promise((resolve) => setTimeout(resolve, 500));
            }
        },
    };
}
