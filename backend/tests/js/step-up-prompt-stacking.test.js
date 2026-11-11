// The 2FA step-up prompt (central_auth.step_up_required) opens while another modal is already open
// (e.g. "approve signup request"). Every AppModal teleports to <body> at z-50, so the later-mounted
// modal covered the prompt and the action looked stuck on a spinner. The prompt must be `stacked`.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';

const read = (path) => readFileSync(fileURLToPath(new URL(`../../resources/js/${path}`, import.meta.url)), 'utf8');

test('AppModal raises a stacked modal above the default modal layer', () => {
    const modal = read('Components/Common/AppModal.vue');
    assert.match(modal, /stacked:\s*\{\s*type:\s*Boolean/);
    assert.match(modal, /:class="stacked \? 'z-\[60\]' : 'z-50'"/);
});

test('the step-up prompt is a stacked modal', () => {
    const prompt = read('Components/SuperAdmin/StepUpPrompt.vue');
    assert.match(prompt, /<AppModal[^>]*\bstacked\b/s);
});
