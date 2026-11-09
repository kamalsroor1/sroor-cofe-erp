// FE-12: no hardcoded user-facing Arabic text and no emoji used as icons in the SPA sources.
// All copy lives in backend/lang/{ar,en}/*.php and is read through $t()/t(); icons are lucide.
// Comments are ignored. POS files are owned by the POS lane and are listed explicitly below.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath, URL } from 'node:url';

const JS_ROOT = fileURLToPath(new URL('../../resources/js/', import.meta.url));

const ARABIC = /[؀-ۿ]/;
// Pictographs, dingbats, arrows, and geometric shapes.
const EMOJI =
    /[\u{1F000}-\u{1FFFF}\u{2190}-\u{21FF}\u{2300}-\u{23FF}\u{25A0}-\u{25FF}\u{2600}-\u{27BF}\u{2B00}-\u{2BFF}]/u;

// POS lane: not scanned here, tracked separately (see the FE-12 hand-off).
const POS_PATTERNS = [/^views\/POS\//, /^Components\/POS\//, /^Composables\/POS\//, /(^|\/)usePos[^/]*\.js$/];

// Files where Arabic / emoji characters are data by design.
const ALLOW_ARABIC = new Set([
    'helpers/digits.js', // Arabic-Indic digit tables used to normalise typed numbers
    'helpers/posCheckout.js', // POS number parsing (digit tables)
]);
const ALLOW_EMOJI = new Set([
    'Components/Common/DynamicIcon.vue', // maps emoji stored in user data (category icons) to lucide icons
]);

function sourceFiles(dir) {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const full = join(dir, entry.name);
        if (entry.isDirectory()) return sourceFiles(full);
        if (!/\.(js|vue)$/.test(entry.name) || entry.name.startsWith('defaultTranslations.')) return [];
        return [full];
    });
}

/** Drops HTML, block and line comments so only executable / rendered text is scanned. */
export function stripComments(source) {
    return source
        .replace(/<!--[\s\S]*?-->/g, (m) => m.replace(/[^\n]/g, ''))
        .replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, ''))
        .replace(/(^|[^:\\'"`\w])\/\/.*$/gm, '$1');
}

function scan(pattern, allow) {
    const offenders = [];
    for (const file of sourceFiles(JS_ROOT)) {
        const rel = relative(JS_ROOT, file).split(sep).join('/');
        if (allow.has(rel) || POS_PATTERNS.some((re) => re.test(rel))) continue;
        stripComments(readFileSync(file, 'utf8'))
            .split('\n')
            .forEach((line, idx) => {
                if (pattern.test(line)) offenders.push(`${rel}:${idx + 1}: ${line.trim().slice(0, 100)}`);
            });
    }
    return offenders;
}

test('the comment stripper keeps rendered text and drops comments', () => {
    const src = ['<!-- تعليق -->', '// تعليق', '/* تعليق */', '<p>نص</p>', "const u = 'http://x.test';"].join('\n');
    const kept = stripComments(src);
    assert.equal(kept.split('\n').filter((l) => ARABIC.test(l)).length, 1);
    assert.ok(kept.includes('http://x.test'));
});

test('no Arabic literals in Vue/JS sources outside POS (use lang files)', () => {
    const offenders = scan(ARABIC, ALLOW_ARABIC);
    assert.deepEqual(
        offenders,
        [],
        `Hardcoded Arabic text found. Move it to backend/lang/{ar,en}:\n${offenders.join('\n')}`
    );
});

test('no emoji or glyphs used as icons outside POS (use lucide-vue-next)', () => {
    const offenders = scan(EMOJI, ALLOW_EMOJI);
    assert.deepEqual(offenders, [], `Emoji used as an icon. Use a lucide icon:\n${offenders.join('\n')}`);
});
