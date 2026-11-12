import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import {
    bestOnColor,
    composite,
    contrastRatio,
    flattenLayers,
    isLargeText,
    parseHex,
    requiredRatio,
} from '../../resources/js/helpers/contrast.js';
import { PRESET_PALETTES, getContrastTextColor } from '../../resources/js/Composables/useTheme.js';

const ratio = (fg, bg) => contrastRatio(parseHex(fg), parseHex(bg));

test('reference ratios from the WCAG 2.1 definition', () => {
    assert.equal(ratio('#000000', '#ffffff').toFixed(2), '21.00');
    assert.equal(ratio('#ffffff', '#ffffff').toFixed(2), '1.00');
    // #767676 is the classic lightest grey that still passes 4.5:1 on white
    assert.equal(ratio('#767676', '#ffffff').toFixed(2), '4.54');
    assert.ok(ratio('#777777', '#ffffff') < 4.5);
    // the colours the COLOR-01 audit flagged on white
    assert.ok(ratio('#10b981', '#ffffff') < 3, 'emerald-500 text');
    assert.ok(ratio('#94a3b8', '#ffffff') < 3, 'slate-400 text');
});

test('alpha layers are flattened onto the base before measuring', () => {
    assert.deepEqual(composite([0, 0, 0, 0.5], [255, 255, 255]), [127.5, 127.5, 127.5]);
    assert.deepEqual(flattenLayers([[16, 185, 129, 0.1]], [255, 255, 255]).map(Math.round), [231, 248, 242]);
    // translucent text is measured as it renders
    assert.ok(contrastRatio([0, 0, 0, 0.5], [255, 255, 255]) < contrastRatio([0, 0, 0, 1], [255, 255, 255]));
});

test('large text needs 3:1, everything else 4.5:1', () => {
    assert.equal(isLargeText(24, 400), true);
    assert.equal(isLargeText(18.66, 700), true);
    assert.equal(isLargeText(18.66, 400), false);
    assert.equal(isLargeText(14, 700), false);
    assert.equal(requiredRatio(14, 400), 4.5);
    assert.equal(requiredRatio(30, 400), 3);
});

// Read the real tokens out of app.css so the table below cannot drift from what ships.
const css = readFileSync(fileURLToPath(new URL('../../resources/css/app.css', import.meta.url)), 'utf8');
const tokensOf = (selector) => {
    const blocks = [...css.matchAll(new RegExp(`(?:^|\\n)${selector} \\{([^}]*)\\}`, 'g'))].map((m) => m[1]);
    return Object.fromEntries(
        blocks.flatMap((block) => [...block.matchAll(/--([\w-]+):\s*(#[0-9a-fA-F]{6})/g)].map((m) => [m[1], m[2]]))
    );
};
const light = tokensOf(':root');
const dark = { ...light, ...tokensOf('html\\.dark') };

const SURFACES = {
    light: { white: '#ffffff', 'slate-50': '#f8fafc', 'slate-100': '#f1f5f9' },
    dark: { 'slate-950': '#020617', 'slate-900': '#0f172a', 'slate-800': '#1e293b' },
};
const TEXT_TOKENS = [
    'color-text-muted',
    'color-text-subtle',
    'color-success-text',
    'color-danger-text',
    'color-warning-text',
    'color-info-text',
    'color-indigo-text',
    'color-purple-text',
    'color-pink-text',
];

for (const [mode, tokens] of [
    ['light', light],
    ['dark', dark],
]) {
    for (const name of TEXT_TOKENS) {
        test(`${name} is >= 4.5:1 on every ${mode} surface`, () => {
            assert.ok(tokens[name], `${name} missing from app.css`);
            for (const [surface, hex] of Object.entries(SURFACES[mode])) {
                assert.ok(
                    ratio(tokens[name], hex) >= 4.5,
                    `${name} ${tokens[name]} on ${surface}: ${ratio(tokens[name], hex).toFixed(2)}`
                );
            }
        });
    }
}

test('semantic status tokens stay readable on their own 10% tint (badge)', () => {
    const tints = {
        'color-success-text': '#10b981',
        'color-danger-text': '#f43f5e',
        'color-warning-text': '#f59e0b',
        'color-info-text': '#06b6d4',
    };
    for (const [name, tint] of Object.entries(tints)) {
        const bg = flattenLayers([[...parseHex(tint), 0.1]], [255, 255, 255]);
        assert.ok(contrastRatio(parseHex(light[name]), bg) >= 4.5, `${name} on its tint`);
    }
});

test('getContrastTextColor uses WCAG luminance, not YIQ', () => {
    // YIQ chose white here (2.5:1); the WCAG pick is the near-black ink (6.6:1).
    assert.equal(getContrastTextColor('#10b981'), '#020617');
    assert.equal(getContrastTextColor('#0f6e5e'), '#ffffff');
    assert.equal(bestOnColor(parseHex('#ffffff')).color, '#020617');
});

// Presets: the fill that css/app.css pairs with --color-primary-text, and the primary-ink used for text.
const presetBlock = (name) => css.match(new RegExp(`\\[data-theme-color='${name}'\\] \\{([^}]*)\\}`))?.[1] ?? '';
const prop = (block, key) => block.match(new RegExp(`--${key}:\\s*(#[0-9a-fA-F]{6})`))?.[1];

for (const [name, palette] of Object.entries(PRESET_PALETTES)) {
    test(`theme ${name}: text on the fill, and ink as text, are readable`, () => {
        const block = presetBlock(name);
        const onPrimary = prop(block, 'color-primary-text');
        const ink = prop(block, 'color-primary-ink');
        const fill = parseHex(palette.hex);
        const floor = 4.5;

        assert.equal(onPrimary, palette.text, `${name}: app.css and PRESET_PALETTES disagree on the label colour`);
        assert.equal(palette.text, bestOnColor(fill).color, `${name}: label is not the higher-contrast choice`);
        assert.ok(contrastRatio(parseHex(onPrimary), fill) >= floor, `${name} text on fill`);
        // ink on white and on its own 15% tint (bg-theme-light badges)
        const tint = flattenLayers([[...fill, 0.15]], [255, 255, 255]);
        assert.ok(ratio(ink, '#ffffff') >= 4.5, `${name} ink on white`);
        assert.ok(contrastRatio(parseHex(ink), tint) >= 4.5, `${name} ink on tint`);
    });

    test(`theme ${name}: dark-mode ink is readable on slate surfaces`, () => {
        const darkBlocks = [
            ...css.matchAll(new RegExp(`html\\.dark\\[data-theme-color='${name}'\\][^{]*\\{([^}]*)\\}`, 'g')),
        ].map((m) => m[1]);
        const darkInk = darkBlocks.find((block) => block.includes('--color-primary-ink'));
        const ink = prop(darkInk ?? '', 'color-primary-ink');
        assert.ok(ink, `${name} dark ink missing`);
        for (const hex of Object.values(SURFACES.dark)) {
            assert.ok(ratio(ink, hex) >= 4.5, `${name} dark ink ${ink} on ${hex}`);
        }
    });
}
