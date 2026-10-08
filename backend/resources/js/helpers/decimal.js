/**
 * Exact decimal math for money and quantities (scale 3), mirroring the
 * server's bcmath behaviour. Values are held internally as BigInt
 * thousandths ("milli"); every public result is a '0.000'-format string.
 *
 * - No parseFloat / toFixed / float multiplication anywhere.
 * - Every operation truncates toward zero at scale 3, like bcmath.
 * - Unparseable or empty input counts as zero, so half-typed form fields
 *   never throw while the cashier is typing.
 */

const SCALE = 3;
const ONE = 1000n;

const NUMBER_PATTERN = /^([+-]?)(\d*)(?:\.(\d*))?(?:e([+-]?\d+))?$/i;

/**
 * Parse a decimal string (optionally in exponent notation) into thousandths,
 * truncating any digits beyond scale 3 toward zero.
 */
function parseToMilli(raw) {
    const match = NUMBER_PATTERN.exec(raw.trim());
    if (!match) return 0n;

    const [, sign, intPart = '', fracPart = '', expPart] = match;
    if (intPart === '' && fracPart === '') return 0n;

    let digits = intPart + fracPart;
    let pointPos = intPart.length + (expPart ? Number(expPart) : 0);

    if (pointPos < 0) {
        digits = '0'.repeat(-pointPos) + digits;
        pointPos = 0;
    }

    const wholeDigits = digits.slice(0, pointPos).padEnd(pointPos, '0');
    const fracDigits = digits.slice(pointPos, pointPos + SCALE).padEnd(SCALE, '0');
    const milli = BigInt((wholeDigits || '0') + fracDigits);

    return sign === '-' ? -milli : milli;
}

/**
 * Convert a string, number or bigint (already in thousandths) to BigInt thousandths.
 * Numbers go through a single String(n) conversion and are never multiplied.
 */
export function toMilli(value) {
    if (typeof value === 'bigint') return value;
    if (typeof value === 'number') {
        if (!Number.isFinite(value)) return 0n;
        return parseToMilli(String(value));
    }
    if (typeof value === 'string') return parseToMilli(value);
    return 0n;
}

/** Format BigInt thousandths as a '123.450' string. */
export function fromMilli(milli) {
    const negative = milli < 0n;
    const abs = negative ? -milli : milli;
    const whole = abs / ONE;
    const frac = (abs % ONE).toString().padStart(SCALE, '0');
    return `${negative ? '-' : ''}${whole}.${frac}`;
}

/** Normalize any input to a '0.000'-format string (truncated at scale 3). */
export function normalize(value) {
    return fromMilli(toMilli(value));
}

export function dAdd(a, b) {
    return fromMilli(toMilli(a) + toMilli(b));
}

export function dSub(a, b) {
    return fromMilli(toMilli(a) - toMilli(b));
}

/** Multiply, truncating toward zero at scale 3 — same as bcmul(a, b, 3). */
export function dMul(a, b) {
    return fromMilli((toMilli(a) * toMilli(b)) / ONE);
}

/**
 * Percentage of an amount, mirroring the server exactly:
 * bcdiv(bcmul(amount, pct, 4), '100', 3).
 */
export function dPercent(amount, pct) {
    // Product is at scale 6; truncate to scale 4 (bcmul .., 4).
    const atScale4 = (toMilli(amount) * toMilli(pct)) / 100n;
    // Divide by 100 and truncate to scale 3 (bcdiv .., '100', 3).
    return fromMilli(atScale4 / 1000n);
}

/** Compare: -1 if a < b, 0 if equal, 1 if a > b. */
export function dCmp(a, b) {
    const x = toMilli(a);
    const y = toMilli(b);
    if (x === y) return 0;
    return x < y ? -1 : 1;
}

export function dMin(a, b) {
    return dCmp(a, b) <= 0 ? normalize(a) : normalize(b);
}

/** Clamp negative values to '0.000'. */
export function dMax0(value) {
    const milli = toMilli(value);
    return fromMilli(milli < 0n ? 0n : milli);
}

export function dSum(values) {
    return fromMilli((values ?? []).reduce((total, value) => total + toMilli(value), 0n));
}

export function isPositive(value) {
    return toMilli(value) > 0n;
}

export function isZero(value) {
    return toMilli(value) === 0n;
}
