// GEN-014: BaseInput 422 validation error styling, error message placement, and aria-describedby wiring.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import * as Vue from 'vue';
import { parse, compileScript, compileTemplate } from 'vue/compiler-sfc';
import { renderToString } from 'vue/server-renderer';

const read = (path) => readFileSync(fileURLToPath(new URL(`../../resources/js/${path}`, import.meta.url)), 'utf8');

function compileBaseInput() {
    const source = read('Components/Form/BaseInput.vue');
    const { descriptor } = parse(source, { filename: 'BaseInput.vue' });
    const compiledScript = compileScript(descriptor, {
        id: 'base-input-test',
        inlineTemplate: false,
    });
    const template = compileTemplate({
        source: descriptor.template.content,
        filename: 'BaseInput.vue',
        id: 'base-input-test',
        compilerOptions: {
            bindingMetadata: compiledScript.bindings,
        },
    });

    const iconStub = () => Vue.h('svg', { 'aria-hidden': 'true' });
    const transformImports = (code) =>
        code
            .replace(/^\s*import\s*\{([^}]+)\}\s*from\s*['"]vue['"];?\s*$/gm, (_, names) => {
                const mapped = names
                    .split(',')
                    .map((s) => s.trim())
                    .filter(Boolean)
                    .map((s) => s.replace(/\s+as\s+/g, ': '))
                    .join(', ');
                return `const { ${mapped} } = Vue;`;
            })
            .replace(/^\s*import\s+[\s\S]*?\s+from\s+['"][^'"]+['"];?\s*$/gm, '');

    const scriptBody = transformImports(compiledScript.content).replace('export default /*@__PURE__*/', 'return ');
    const renderCode = transformImports(template.code).replace('export function render', 'return function render');

    const scriptFactory = new Function('Vue', 'Eye', 'EyeOff', 'AlertCircle', 'X', scriptBody);
    const renderFactory = new Function('Vue', renderCode);

    const comp = scriptFactory(Vue, iconStub, iconStub, iconStub, iconStub);
    comp.render = renderFactory(Vue);
    return comp;
}

test('BaseInput renders red border, error message below input, aria-invalid="true", and matching aria-describedby on 422 error', async () => {
    const BaseInput = compileBaseInput();
    const app = Vue.createSSRApp({
        render() {
            return Vue.h(BaseInput, {
                id: 'customer-phone',
                label: 'Phone',
                modelValue: 'abc',
                error: ['The phone must be a valid number.'],
            });
        },
    });
    app.config.globalProperties.$t = (k) => k;
    const html = await renderToString(app);

    assert.match(html, /id="customer-phone"/);
    assert.match(html, /aria-invalid="true"/);
    assert.match(html, /aria-describedby="customer-phone-error"/);
    assert.match(html, /border-rose-500/);
    assert.match(
        html,
        /<input[^>]*id="customer-phone"[^>]*>.*<p[^>]*id="customer-phone-error"[^>]*>.*?The phone must be a valid number\..*?<\/p>/s
    );
});

test('BaseInput renders hint and aria-invalid="false" when error is absent', async () => {
    const BaseInput = compileBaseInput();
    const app = Vue.createSSRApp({
        render() {
            return Vue.h(BaseInput, {
                id: 'customer-limit',
                label: 'Credit Limit',
                modelValue: '',
                hint: 'Leave empty for unlimited',
                error: null,
            });
        },
    });
    app.config.globalProperties.$t = (k) => k;
    const html = await renderToString(app);

    assert.match(html, /aria-invalid="false"/);
    assert.match(html, /aria-describedby="customer-limit-hint"/);
    assert.match(html, /border-slate-300/);
    assert.doesNotMatch(html, /customer-limit-error/);
});

test('CustomerFormModal wires server 422 errors to all form fields including phone and name', () => {
    const src = read('Components/Customers/CustomerFormModal.vue');
    for (const field of [
        'name',
        'phone',
        'address',
        'tax_number',
        'credit_limit',
        'price_tier',
        'opening_balance',
        'notes',
    ]) {
        assert.match(src, new RegExp(`:error="errors\\?\\.${field}\\?\\.\\[0\\] \\?\\? null"`));
    }
});

test('BaseSelect links trigger aria-describedby to error/hint id', () => {
    const src = read('Components/Form/BaseSelect.vue');
    assert.match(
        src,
        /:aria-describedby="hasError \? `\$\{selectId\}-error` : hint \? `\$\{selectId\}-hint` : undefined"/
    );
});
