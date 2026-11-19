import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { getSwalThemeColors } from '../../resources/js/helpers/alertTheme.js';
import { formatMoneyAmount } from '../../resources/js/helpers/formatters.js';

function mockDoc(isDark) {
    return {
        documentElement: {
            classList: {
                contains: (cls) => cls === 'dark' && isDark,
            },
        },
    };
}

function collectVueFiles(dir, files = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            if (full.includes(`${path.sep}POS`)) continue;
            collectVueFiles(full, files);
        } else if (entry.name.endsWith('.vue')) {
            if (entry.name === 'DynamicIcon.vue') continue;
            files.push(full);
        }
    }
    return files;
}

describe('UI-04 quick polish checks', () => {
    it('returns distinct light and dark SweetAlert2 colors based on html.dark', () => {
        const light = getSwalThemeColors(mockDoc(false));
        const dark = getSwalThemeColors(mockDoc(true));

        assert.notEqual(light.background, dark.background);
        assert.notEqual(light.color, dark.color);
        assert.equal(light.background, '#ffffff');
        assert.equal(dark.background, '#090e1a');
        assert.equal(light.confirmButtonColor, 'var(--color-primary)');
        assert.equal(dark.confirmButtonColor, 'var(--color-primary)');
    });

    it('formats SuperAdmin money figures consistently with formatters.js', () => {
        assert.equal(formatMoneyAmount(1234.5, { decimals: 2, fixed: true }), '1,234.50');
        assert.equal(formatMoneyAmount(0, { decimals: 2, fixed: true }), '0.00');
    });

    it('has no rendered UI emojis in non-POS Vue components', () => {
        const jsRoot = path.resolve(import.meta.dirname, '../../resources/js');
        const emojiRe = /[\u{1F300}-\u{1FAFF}\u2600-\u27BF]/u;
        const hits = [];

        for (const file of collectVueFiles(jsRoot)) {
            const lines = fs.readFileSync(file, 'utf8').split('\n');
            lines.forEach((line, idx) => {
                const trimmed = line.trim();
                if (
                    trimmed.startsWith('<!--') ||
                    trimmed.startsWith('//') ||
                    trimmed.startsWith('*') ||
                    trimmed.startsWith('/*')
                ) {
                    return;
                }
                if (emojiRe.test(trimmed)) {
                    hits.push(`${path.relative(jsRoot, file)}:${idx + 1}: ${trimmed}`);
                }
            });
        }

        assert.deepEqual(hits, []);
    });
});
