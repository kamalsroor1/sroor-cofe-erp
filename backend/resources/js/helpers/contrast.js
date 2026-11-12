// WCAG 2.1 contrast maths, shared by the unit tests and the Playwright contrast audit (COLOR-01).
// Colours are [r, g, b] (0-255) or [r, g, b, a] (a 0-1).

export const NORMAL_TEXT_MIN = 4.5;
export const LARGE_TEXT_MIN = 3;

export function parseHex(hex) {
    const raw = String(hex).trim().replace('#', '');
    const full =
        raw.length === 3
            ? raw
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : raw;

    if (!/^[0-9a-fA-F]{6}$/.test(full)) {
        throw new Error(`Invalid hex colour: ${hex}`);
    }

    return [parseInt(full.slice(0, 2), 16), parseInt(full.slice(2, 4), 16), parseInt(full.slice(4, 6), 16)];
}

function channel(value) {
    const c = value / 255;

    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
}

export function relativeLuminance([r, g, b]) {
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

/** Composite `fg` (with its alpha) over an opaque `bg`; returns an opaque [r, g, b]. */
export function composite(fg, bg) {
    const a = fg[3] ?? 1;

    return [0, 1, 2].map((i) => fg[i] * a + bg[i] * (1 - a));
}

export function contrastRatio(fg, bg) {
    const opaqueFg = (fg[3] ?? 1) < 1 ? composite(fg, bg) : fg;
    const l1 = relativeLuminance(opaqueFg);
    const l2 = relativeLuminance(bg);

    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
}

/** Flatten a stack of translucent layers (bottom first) onto an opaque base. */
export function flattenLayers(layers, base = [255, 255, 255]) {
    return layers.reduce((under, layer) => composite(layer, under), base);
}

/** WCAG "large text": at least 24px, or at least 18.66px (14pt) and bold. */
export function isLargeText(fontSizePx, fontWeight) {
    return fontSizePx >= 24 || (fontSizePx >= 18.66 && Number(fontWeight) >= 700);
}

export function requiredRatio(fontSizePx, fontWeight) {
    return isLargeText(fontSizePx, fontWeight) ? LARGE_TEXT_MIN : NORMAL_TEXT_MIN;
}

/** Text colour to put ON a fill: whichever of white / near-black (#020617) has the higher WCAG ratio. */
export function bestOnColor(bg) {
    const white = contrastRatio([255, 255, 255], bg);
    const dark = contrastRatio([2, 6, 23], bg);

    return white >= dark ? { color: '#ffffff', ratio: white } : { color: '#020617', ratio: dark };
}
