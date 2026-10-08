import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import prettierConfig from 'eslint-config-prettier';
import globals from 'globals';

export default [
    {
        ignores: [
            'public/**',
            'vendor/**',
            'node_modules/**',
            'android/**',
            'storage/**',
            'bootstrap/cache/**',
            'test-results/**',
            'playwright-report/**',
            'resources/js/**/defaultTranslations.*',
        ],
    },

    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],

    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
            },
        },
    },

    {
        files: ['**/*.{js,vue}'],
        rules: {
            // `catch (e) {}` and swallowed errors are deliberate in the native/Electron bridges.
            'no-unused-vars': ['error', { caughtErrors: 'none', argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            'no-empty': ['error', { allowEmptyCatch: true }],
            // Purely stylistic (attribute order / placement) — would flood ~1.4k warnings and an
            // autofix would reorder attributes in every template. Prettier owns layout.
            'vue/attributes-order': 'off',
            'vue/first-attribute-linebreak': 'off',
            // Established single-word component names (Pagination, Skeleton) — renaming breaks imports.
            'vue/multi-word-component-names': 'off',
        },
    },

    {
        files: ['*.config.js', 'scripts/**/*.{js,mjs}', 'e2e/**/*.js', 'tests/e2e/**/*.js'],
        languageOptions: {
            globals: {
                ...globals.node,
            },
        },
    },

    // Prettier owns formatting: turns off every stylistic rule above. Keep it last.
    prettierConfig,
];
