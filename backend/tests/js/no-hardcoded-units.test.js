// SETG-10: the SPA never ships its own unit list. Units come from
// system.inventory_units (tenant, via useUnits()) or from the central API (console).
// Flags, in resources/js: an array literal with 2+ unit names, a comma-separated unit
// string, a `|| 'unit'` / `?? 'unit'` fallback, and a `unit: 'unit'` / `unit = 'unit'` default.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath, URL } from 'node:url';

const JS_ROOT = fileURLToPath(new URL('../../resources/js/', import.meta.url));

// Unit names from the platform catalog / tenant defaults (TenantSettings, GetPlatformSystemUnitsAction).
const UNIT_NAMES = [
    'قطعة',
    'علبة',
    'كرتونة',
    'كجم',
    'جرام',
    'جم',
    'شيكارة',
    'طرد',
    'دستة',
    'باكت',
    'حبة',
    'لتر',
    'مل',
    'متر',
    'طقم',
    'زوج',
    'باليتة',
    'صندوق',
    'رول',
    'برميل',
    'شريحة',
    'جوال',
];

// TEMPORARY allowlist — files this lane may not edit. Remove an entry once its file is fixed.
const ALLOWLIST = new Map([]);

const unitAlternation = UNIT_NAMES.join('|');
const QUOTED_UNIT = new RegExp(`(['"\`])(?:${unitAlternation})\\1`, 'g');
const FALLBACK = new RegExp(`(?:\\|\\||\\?\\?)\\s*(['"\`])(?:${unitAlternation})\\1`);
const UNIT_DEFAULT = new RegExp(`\\bunit\\s*(?::|=(?!=))\\s*(['"\`])(?:${unitAlternation})\\1`);
const QUOTED_STRING = /(['"`])((?:(?!\1)[^\n\\])*)\1/g;
const ARRAY_LITERAL = /\[[^[\]]*\]/g;

function sourceFiles(dir) {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const full = join(dir, entry.name);
        if (entry.isDirectory()) return sourceFiles(full);
        if (!/\.(js|vue)$/.test(entry.name) || entry.name.startsWith('defaultTranslations.')) return [];
        return [full];
    });
}

const stripComments = (source) => source.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');

export function findUnitLiterals(source) {
    const code = stripComments(source);
    const problems = [];

    for (const [list] of code.matchAll(ARRAY_LITERAL)) {
        const count = list.match(QUOTED_UNIT)?.length ?? 0;
        if (count >= 2) problems.push(`unit list ${list.slice(0, 60).replace(/\s+/g, ' ')}…`);
    }
    for (const [, , text] of code.matchAll(QUOTED_STRING)) {
        if (!text.includes(',')) continue;
        const units = text.split(',').filter((part) => UNIT_NAMES.includes(part.trim()));
        if (units.length >= 2) problems.push(`unit CSV '${text.slice(0, 60)}'`);
    }
    if (FALLBACK.test(code)) problems.push(`unit fallback ${code.match(FALLBACK)[0]}`);
    if (UNIT_DEFAULT.test(code)) problems.push(`unit default ${code.match(UNIT_DEFAULT)[0]}`);

    return problems;
}

test('the detector catches lists, CSV strings, fallbacks and defaults', () => {
    assert.equal(findUnitLiterals("const u = ['كجم', 'قطعة'];").length, 1);
    assert.equal(findUnitLiterals("const u = 'قطعة,علبة,كرتونة';").length, 1);
    assert.equal(findUnitLiterals("{{ item.unit || 'قطعة' }}").length, 1);
    assert.equal(findUnitLiterals("form.unit = 'كجم';").length, 1);
    assert.equal(findUnitLiterals("const f = { unit: 'كجم' };").length, 1);
    assert.deepEqual(findUnitLiterals("if (line.unit === 'كجم') {}"), []);
    assert.deepEqual(findUnitLiterals("// e.g. ['كجم', 'قطعة']"), []);
    assert.deepEqual(findUnitLiterals('const u = useUnits().defaultUnit;'), []);
});

test('no hardcoded unit lists or unit fallbacks in resources/js', () => {
    const offenders = [];
    for (const file of sourceFiles(JS_ROOT)) {
        const rel = relative(JS_ROOT, file).split(sep).join('/');
        if (ALLOWLIST.has(rel)) continue;
        for (const problem of findUnitLiterals(readFileSync(file, 'utf8'))) {
            offenders.push(`${rel}: ${problem}`);
        }
    }
    assert.deepEqual(offenders, [], `use useUnits() / the server catalog instead:\n${offenders.join('\n')}`);
});
