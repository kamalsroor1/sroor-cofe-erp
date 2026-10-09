// SETG-13: resources/js/helpers/decimal.js produces, character for character, the
// strings in the shared vectors file that tests/Unit/Money/RoundingParityTest.php runs
// against app/Support/Money/Decimal.php.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import { normalize, dAdd, dSub, dMul, dPercent, dCmp } from '../../resources/js/helpers/decimal.js';

const vectorsPath = fileURLToPath(new URL('../Fixtures/rounding-vectors.json', import.meta.url));
const { vectors } = JSON.parse(readFileSync(vectorsPath, 'utf8'));

const ops = {
    normalize: (a) => normalize(a),
    add: (a, b) => dAdd(a, b),
    sub: (a, b) => dSub(a, b),
    mul: (a, b) => dMul(a, b),
    percent: (a, b) => dPercent(a, b),
    cmp: (a, b) => dCmp(a, b),
};

test('the shared vectors include ties and negatives', () => {
    const args = vectors.flatMap((vector) => vector.args);
    assert.ok(args.includes('0.0005'));
    assert.ok(args.includes('-0.0005'));
    assert.ok(vectors.length >= 40);
});

for (const [index, vector] of vectors.entries()) {
    test(`#${index} ${vector.op}(${vector.args.join(', ')}) = ${vector.expected}`, () => {
        const run = ops[vector.op];
        assert.ok(run, `unknown op ${vector.op}`);
        assert.equal(run(...vector.args), vector.expected);
    });
}

test('numbers are converted through String(), never float-multiplied', () => {
    assert.equal(dMul(0.25, 550.5), '137.625');
    assert.equal(dAdd(0.1, 0.2), '0.300');
    assert.equal(normalize(1e-7), '0.000');
});
