/**
 * SHOP-04: local barcode encoders for item labels (no CDN, no package).
 *
 * Both encoders return the symbol as a list of bars in module units, so the label renders it as a
 * crisp SVG at any print size. Quiet zones are part of the returned width.
 *
 *  - Code 128 (set B for printable ASCII, set C for runs of digits) with the mod-103 checksum.
 *  - EAN-13 for 12/13-digit numeric codes (the 13th digit is the mod-10 check digit).
 */

/** Bar/space widths of every Code 128 symbol value 0..105 (3 bars + 3 spaces, 11 modules each). */
// prettier-ignore
export const CODE128_PATTERNS = Object.freeze([
    '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
    '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
    '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
    '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
    '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
    '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
    '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
    '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
    '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
    '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
    '114131', '311141', '411131', '211412', '211214', '211232',
]);
/** Stop symbol: 4 bars + 3 spaces, 13 modules. */
export const CODE128_STOP = '2331112';

const START_B = 104;
const START_C = 105;
const CODE_B = 100;
const CODE_C = 99;
const QUIET_CODE128 = 10;

const isDigit = (ch) => ch >= '0' && ch <= '9';

/** Code 128 symbol values (start + data, without checksum and stop) for `text`, or null if not encodable. */
export function code128Values(text) {
    const value = String(text ?? '');
    if (value === '' || !/^[\x20-\x7E]+$/.test(value)) return null;

    // Digit runs of 4+ (or the whole value when it is 2+ digits) pack two digits per symbol in set C.
    const digitRunAt = (i) => {
        let j = i;
        while (j < value.length && isDigit(value[j])) j++;
        return j - i;
    };

    const values = [];
    let set = null;
    let i = 0;
    while (i < value.length) {
        const run = digitRunAt(i);
        const wholeValueDigits = i === 0 && run === value.length && run >= 2;
        if (run >= 4 || wholeValueDigits) {
            const pairs = Math.floor(run / 2);
            // An odd run starting the value keeps its first digit in set B so set C covers the rest.
            if (run % 2 === 1 && set === null) {
                values.push(START_B, value.charCodeAt(i) - 32);
                set = 'B';
                i++;
            }
            if (set === null) values.push(START_C);
            else if (set !== 'C') values.push(CODE_C);
            set = 'C';
            for (let p = 0; p < pairs; p++) {
                values.push(Number(value.substr(i, 2)));
                i += 2;
            }
            continue;
        }
        if (set === null) values.push(START_B);
        else if (set !== 'B') values.push(CODE_B);
        set = 'B';
        values.push(value.charCodeAt(i) - 32);
        i++;
    }
    return values;
}

/** Mod-103 checksum: start value + sum(value * position). */
export function code128Checksum(values) {
    return values.reduce((sum, v, idx) => sum + v * (idx === 0 ? 1 : idx), 0) % 103;
}

/** Turns a width string ("212222") into alternating bar/space runs starting with a bar at module `x`. */
function appendWidths(bars, widths, x) {
    let pos = x;
    for (let k = 0; k < widths.length; k++) {
        const w = Number(widths[k]);
        if (k % 2 === 0) bars.push({ x: pos, w });
        pos += w;
    }
    return pos;
}

/**
 * Encodes `text` as Code 128.
 * @returns {{ symbology: 'code128', values: number[], checksum: number, bars: {x:number,w:number}[], width: number } | null}
 */
export function encodeCode128(text) {
    const values = code128Values(text);
    if (!values) return null;
    const checksum = code128Checksum(values);
    const bars = [];
    let x = QUIET_CODE128;
    for (const v of [...values, checksum]) x = appendWidths(bars, CODE128_PATTERNS[v], x);
    x = appendWidths(bars, CODE128_STOP, x);
    return { symbology: 'code128', values, checksum, bars, width: x + QUIET_CODE128 };
}

// EAN-13 digit encodings (L = odd parity left, G = even parity left, R = right).
// prettier-ignore
const EAN_L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
const EAN_R = EAN_L.map((code) => code.replace(/./g, (bit) => (bit === '1' ? '0' : '1')));
const EAN_G = EAN_R.map((code) => code.split('').reverse().join(''));
// prettier-ignore
const EAN_PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];
const QUIET_EAN_LEFT = 11;
const QUIET_EAN_RIGHT = 7;

/** EAN-13 check digit of the first 12 digits (weights 1,3,1,3... from the left). */
export function ean13CheckDigit(first12) {
    const digits = String(first12);
    let sum = 0;
    for (let i = 0; i < 12; i++) sum += Number(digits[i]) * (i % 2 === 0 ? 1 : 3);
    return (10 - (sum % 10)) % 10;
}

/** True for a 13-digit value with a correct check digit. */
export function isValidEan13(text) {
    const value = String(text ?? '');
    return /^\d{13}$/.test(value) && ean13CheckDigit(value) === Number(value[12]);
}

/** The 95-module EAN-13 bit string (guards included) of a valid code. */
export function ean13Modules(text) {
    const value = String(text ?? '');
    if (!isValidEan13(value)) return null;
    const parity = EAN_PARITY[Number(value[0])];
    let bits = '101';
    for (let i = 1; i <= 6; i++) {
        const digit = Number(value[i]);
        bits += parity[i - 1] === 'L' ? EAN_L[digit] : EAN_G[digit];
    }
    bits += '01010';
    for (let i = 7; i <= 12; i++) bits += EAN_R[Number(value[i])];
    return bits + '101';
}

/**
 * Encodes a valid EAN-13 value.
 * @returns {{ symbology: 'ean13', modules: string, bars: {x:number,w:number}[], width: number } | null}
 */
export function encodeEan13(text) {
    const modules = ean13Modules(text);
    if (!modules) return null;
    const bars = [];
    let i = 0;
    while (i < modules.length) {
        if (modules[i] === '1') {
            const start = i;
            while (i < modules.length && modules[i] === '1') i++;
            bars.push({ x: QUIET_EAN_LEFT + start, w: i - start });
        } else {
            i++;
        }
    }
    return { symbology: 'ean13', modules, bars, width: QUIET_EAN_LEFT + modules.length + QUIET_EAN_RIGHT };
}

export const SYMBOLOGIES = Object.freeze(['auto', 'code128', 'ean13']);

/**
 * Symbology a code prints with: `auto` and `ean13` use EAN-13 for a valid 13-digit code and fall back
 * to Code 128 otherwise. Returns null when the code cannot be printed at all (empty or non-ASCII).
 */
export function resolveSymbology(code, preferred = 'auto') {
    const value = String(code ?? '').trim();
    if (value === '') return null;
    if (preferred !== 'code128' && isValidEan13(value)) return 'ean13';
    return code128Values(value) ? 'code128' : null;
}

/** Encodes `code` with the resolved symbology, or null when it cannot be printed. */
export function encodeBarcode(code, preferred = 'auto') {
    const value = String(code ?? '').trim();
    const symbology = resolveSymbology(value, preferred);
    if (symbology === 'ean13') return encodeEan13(value);
    if (symbology === 'code128') return encodeCode128(value);
    return null;
}
