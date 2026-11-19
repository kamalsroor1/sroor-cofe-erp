// SHOP-04: local Code 128 / EAN-13 encoders and the label print job helpers.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    CODE128_PATTERNS,
    CODE128_STOP,
    code128Values,
    code128Checksum,
    encodeCode128,
    ean13CheckDigit,
    isValidEan13,
    ean13Modules,
    encodeEan13,
    resolveSymbology,
    encodeBarcode,
} from '../../resources/js/helpers/barcode.js';
import {
    buildLabels,
    clampCopies,
    normalizeLabelSettings,
    paginateLabels,
    pageCss,
    pageGeometry,
    labelLineFromItem,
    DEFAULT_LABEL_SETTINGS,
    MAX_COPIES,
    MAX_LABELS,
} from '../../resources/js/helpers/labelPrint.js';

const sumWidths = (widths) => widths.split('').reduce((s, w) => s + Number(w), 0);

test('code 128 table: 106 unique symbols of 11 modules with an even bar total, stop is 13 modules', () => {
    assert.equal(CODE128_PATTERNS.length, 106);
    assert.equal(new Set(CODE128_PATTERNS).size, 106);
    for (const pattern of CODE128_PATTERNS) {
        assert.equal(pattern.length, 6);
        assert.equal(sumWidths(pattern), 11, pattern);
        const bars = Number(pattern[0]) + Number(pattern[2]) + Number(pattern[4]);
        assert.equal(bars % 2, 0, `bar modules of ${pattern} are even`);
    }
    assert.equal(sumWidths(CODE128_STOP), 13);
});

test('code 128 set B reference values and checksum', () => {
    // "PJJ123C": start B (104), P=48 J=42 J=42, digits stay in set B (run of 3), C=35.
    assert.deepEqual(code128Values('PJJ123C'), [104, 48, 42, 42, 17, 18, 19, 35]);
    assert.equal(code128Checksum(code128Values('PJJ123C')), 55);
    // "Wikipedia": 104 + 55*1 + 73*2 + 75*3 + 73*4 + 80*5 + 69*6 + 68*7 + 73*8 + 65*9 = 3281 -> 3281 % 103 = 88
    assert.equal(encodeCode128('Wikipedia').checksum, 88);
});

test('code 128 packs digits in set C', () => {
    assert.deepEqual(code128Values('123456'), [105, 12, 34, 56]);
    // odd all-digit value: first digit in set B, the rest in set C
    assert.deepEqual(code128Values('12345'), [104, 17, 99, 23, 45]);
    // letters then a long digit run: switch B -> C -> B
    assert.deepEqual(code128Values('AB12345X'), [104, 33, 34, 99, 12, 34, 100, 21, 56]);
    assert.equal(code128Checksum([105, 12, 34, 56]), (105 + 12 + 34 * 2 + 56 * 3) % 103);
});

test('code 128 symbol width and pattern assembly', () => {
    const encoded = encodeCode128('123456');
    // quiet(10) + start + 3 data + checksum (5 x 11) + stop(13) + quiet(10)
    assert.equal(encoded.width, 10 + 5 * 11 + 13 + 10);
    // first bar is the 2-module bar of START C at the quiet zone edge
    assert.deepEqual(encoded.bars[0], { x: 10, w: 2 });
    // last bar is the 2-module termination bar of the stop symbol
    const last = encoded.bars[encoded.bars.length - 1];
    assert.equal(last.x + last.w, encoded.width - 10);
});

test('code 128 rejects empty and non-ASCII values', () => {
    assert.equal(encodeCode128(''), null);
    assert.equal(encodeCode128('قهوة'), null);
    assert.equal(encodeCode128('tab\tchar'), null);
});

test('ean-13 check digit and modules', () => {
    assert.equal(ean13CheckDigit('400638133393'), 1);
    assert.equal(ean13CheckDigit('590123412345'), 7);
    assert.ok(isValidEan13('4006381333931'));
    assert.ok(isValidEan13('5901234123457'));
    assert.equal(isValidEan13('5901234123458'), false);
    assert.equal(isValidEan13('123'), false);

    const modules = ean13Modules('5901234123457');
    assert.equal(modules.length, 95);
    assert.ok(modules.startsWith('101'));
    assert.ok(modules.endsWith('101'));
    assert.equal(modules.slice(45, 50), '01010');
    // first digit 5 -> parity LGGLLG; digit 9 in L = 0001011, digit 0 in G = 0100111
    assert.equal(modules.slice(3, 10), '0001011');
    assert.equal(modules.slice(10, 17), '0100111');
    // right half starts with digit 1 (8th digit), R(1) = 1100110
    assert.equal(modules.slice(50, 57), '1100110');

    const encoded = encodeEan13('5901234123457');
    assert.equal(encoded.width, 11 + 95 + 7);
    const barModules = encoded.bars.reduce((s, b) => s + b.w, 0);
    assert.equal(barModules, modules.split('').filter((b) => b === '1').length);
});

test('symbology resolution', () => {
    assert.equal(resolveSymbology('5901234123457'), 'ean13');
    assert.equal(resolveSymbology('5901234123457', 'code128'), 'code128');
    assert.equal(resolveSymbology('5901234123458'), 'code128');
    assert.equal(resolveSymbology('ITEM-001', 'ean13'), 'code128');
    assert.equal(resolveSymbology(''), null);
    assert.equal(resolveSymbology('صنف'), null);
    assert.equal(encodeBarcode('  ITEM-7 ').symbology, 'code128');
});

test('label settings are normalised and copies clamped', () => {
    assert.deepEqual(normalizeLabelSettings(null), { ...DEFAULT_LABEL_SETTINGS });
    assert.deepEqual(normalizeLabelSettings({ preset: 'bogus', symbology: 'qr', show_name: 'yes', show_price: false }), {
        ...DEFAULT_LABEL_SETTINGS,
        show_price: false,
    });
    assert.equal(normalizeLabelSettings({ preset: 'a4_3x8' }).preset, 'a4_3x8');
    assert.equal(clampCopies('3'), 3);
    assert.equal(clampCopies(0), 1);
    assert.equal(clampCopies('abc'), 1);
    assert.equal(clampCopies(2.9), 2);
    assert.equal(clampCopies(99999), MAX_COPIES);
});

test('label line keeps only what a label needs', () => {
    const line = labelLineFromItem({ id: 4, name: 'Beans', code: ' 123 ', selling_price: '12.500', cost_price: '9' }, 3);
    assert.deepEqual(line, { id: 4, name: 'Beans', code: '123', selling_price: '12.500', copies: 3 });
});

test('two items x 3 copies -> 6 labels; items without a printable code are skipped', () => {
    const lines = [
        { id: 1, name: 'A', code: '5901234123457', selling_price: '10.000', copies: 3 },
        { id: 2, name: 'B', code: 'ITEM-2', selling_price: '5.250', copies: 3 },
        { id: 3, name: 'C', code: '', selling_price: '1.000', copies: 2 },
        { id: 4, name: 'D', code: 'كود', selling_price: '1.000', copies: 2 },
    ];
    const { labels, skipped, truncated } = buildLabels(lines, {});
    assert.equal(labels.length, 6);
    assert.equal(truncated, false);
    assert.deepEqual(
        skipped.map((s) => [s.id, s.reason]),
        [
            [3, 'no_code'],
            [4, 'unsupported_code'],
        ]
    );
    assert.equal(labels[0].symbology, 'ean13');
    assert.equal(labels[3].symbology, 'code128');
    assert.equal(new Set(labels.map((l) => l.key)).size, 6);
    assert.equal(labels[0].price, '10.000');
});

test('without the barcode, items with no code still get a label', () => {
    const { labels, skipped } = buildLabels([{ id: 3, name: 'C', code: '', selling_price: '1', copies: 2 }], {
        show_barcode: false,
    });
    assert.equal(labels.length, 2);
    assert.equal(skipped.length, 0);
});

test('a job is capped at MAX_LABELS', () => {
    const lines = Array.from({ length: 5 }, (_, i) => ({ id: i, name: 'X', code: `C${i}`, copies: MAX_COPIES }));
    const { labels, truncated } = buildLabels(lines, {});
    assert.equal(labels.length, MAX_LABELS);
    assert.equal(truncated, true);
});

test('pagination and page size per preset', () => {
    const labels = Array.from({ length: 30 }, (_, i) => ({ key: String(i) }));
    assert.equal(paginateLabels(labels, 'roll_38x25').length, 30);
    assert.deepEqual(
        paginateLabels(labels, 'a4_3x8').map((p) => p.length),
        [24, 6]
    );
    assert.equal(pageCss('roll_50x30'), '@page { size: 50mm 30mm; margin: 0; }');
    assert.equal(pageCss('a4_5x13'), '@page { size: 210mm 297mm; margin: 0; }');
    const sheet = pageGeometry('a4_5x13');
    assert.equal(Number(sheet.padX.toFixed(2)), 9.75);
    assert.equal(Number(sheet.padY.toFixed(2)), 10.7);
});
