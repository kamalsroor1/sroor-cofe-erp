/**
 * Exact decimal math for money and quantities (scale 3), with the same
 * rounding rule as the server (SETG-13, app/Support/Money/Decimal.php).
 * Values are held internally as BigInt thousandths ("milli"); every public
 * result is a '0.000'-format string.
 *
 * Rounding rule (identical in PHP and JS, checked by the shared vectors in
 * tests/Fixtures/rounding-vectors.json):
 * - HALF-UP, symmetric: a 5 in the 4th decimal rounds away from zero
 *   (0.0005 -> 0.001, -0.0005 -> -0.001).
 * - Operands are settled at scale 3 first, the operation is exact, the result
 *   is rounded half-up to scale 3.
 *
 * - No parseFloat / toFixed / float multiplication anywhere.
 * - Unparseable or empty input counts as zero, so half-typed form fields
 *   never throw while the cashier is typing.
 */

const SCALE = 3;
const ONE = 1000n;

const NUMBER_PATTERN = /^([+-]?)(\d*)(?:\.(\d*))?(?:e([+-]?\d+))?$/i;

/**
 * Integer division rounded half-up (away from zero on a tie). divisor > 0.
 */
function divRoundHalfUp(numerator, divisor) {
    const quotient = numerator / divisor;
    const remainder = numerator % divisor;
    const absRemainder = remainder < 0n ? -remainder : remainder;
    if (absRemainder * 2n >= divisor) {
        return numerator < 0n ? quotient - 1n : quotient + 1n;
    }
    return quotient;
}

/**
 * Parse a decimal string (optionally in exponent notation) into an integer at
 * `scale` decimals, rounding any digits beyond `scale` half-up.
 */
function parseScaled(raw, scale) {
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
    const fracDigits = digits.slice(pointPos, pointPos + scale).padEnd(scale, '0');
    const nextDigit = digits.charAt(pointPos + scale);
    let scaled = BigInt((wholeDigits || '0') + fracDigits);
    if (nextDigit !== '' && nextDigit >= '5') {
        scaled += 1n;
    }

    return sign === '-' ? -scaled : scaled;
}

/** Parse a decimal string into thousandths (half-up at scale 3). */
function parseToMilli(raw) {
    return parseScaled(raw, SCALE);
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

/** Normalize any input to a '0.000'-format string (half-up at scale 3). */
export function normalize(value) {
    return fromMilli(toMilli(value));
}

export function dAdd(a, b) {
    return fromMilli(toMilli(a) + toMilli(b));
}

export function dSub(a, b) {
    return fromMilli(toMilli(a) - toMilli(b));
}

/** Multiply, half-up at scale 3 — same as Decimal::mul() on the server. */
export function dMul(a, b) {
    return fromMilli(divRoundHalfUp(toMilli(a) * toMilli(b), ONE));
}

/**
 * Percentage of an amount, half-up at scale 3 — same as Decimal::percent():
 * amount x pct / 100, computed exactly before the single rounding.
 */
export function dPercent(amount, pct) {
    // milli x milli is at scale 6; / 100 for the percent, / 1000 back to milli.
    return fromMilli(divRoundHalfUp(toMilli(amount) * toMilli(pct), 100n * ONE));
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

const MAX_DISPLAY_DECIMALS = 6;

function clampDecimals(decimals) {
    const places = Number.parseInt(decimals, 10);
    if (!Number.isFinite(places) || places < 0) return 0;
    return Math.min(places, MAX_DISPLAY_DECIMALS);
}

/**
 * Integer at `places` decimals from any input, rounded half-up once from the full
 * input precision (no double rounding through scale 3). A bigint is thousandths.
 */
function toScaled(value, places) {
    if (typeof value === 'bigint') {
        if (places >= SCALE) return value * 10n ** BigInt(places - SCALE);
        return divRoundHalfUp(value, 10n ** BigInt(SCALE - places));
    }
    if (typeof value === 'number') {
        return Number.isFinite(value) ? parseScaled(String(value), places) : 0n;
    }
    if (typeof value === 'string') return parseScaled(value, places);
    return 0n;
}

function scaledToString(scaled, places) {
    const negative = scaled < 0n;
    const abs = (negative ? -scaled : scaled).toString().padStart(places + 1, '0');
    const whole = abs.slice(0, abs.length - places);
    const frac = abs.slice(abs.length - places);
    return `${negative ? '-' : ''}${whole}${places > 0 ? `.${frac}` : ''}`;
}

/**
 * SETG-13 display rounding: half-up (away from zero on a tie) to `decimals`
 * places, e.g. dRoundTo('2.345', 2) === '2.35', dRoundTo('-0.005', 2) === '-0.01'.
 * Returns a plain string (no grouping) with exactly `decimals` fraction digits.
 */
export function dRoundTo(value, decimals) {
    const places = clampDecimals(decimals);
    return scaledToString(toScaled(value, places), places);
}

/**
 * Display text for an amount: rounded with dRoundTo() and grouped by thousands
 * with Western digits ('1,234,567.50'). Never goes through floats or Intl.
 */
export function formatDecimal(value, decimals) {
    const rounded = dRoundTo(value, decimals);
    const negative = rounded.startsWith('-');
    const [whole, frac] = (negative ? rounded.slice(1) : rounded).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return `${negative ? '-' : ''}${grouped}${frac !== undefined ? `.${frac}` : ''}`;
}
