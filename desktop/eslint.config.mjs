import js from '@eslint/js';
import prettierConfig from 'eslint-config-prettier';
import globals from 'globals';

export default [
    {
        ignores: ['node_modules/**', 'dist/**', 'build/**'],
    },

    js.configs.recommended,

    // Main process, src/ modules and tests: Node + CommonJS.
    {
        files: ['**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'commonjs',
            globals: {
                ...globals.node,
            },
        },
        rules: {
            'no-unused-vars': ['error', { caughtErrors: 'none', argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            'no-empty': ['error', { allowEmptyCatch: true }],
        },
    },

    // Preload runs in the renderer context (DOM available) with Node's require.
    {
        files: ['preload.js'],
        languageOptions: {
            globals: {
                ...globals.browser,
            },
        },
    },

    // Prettier owns formatting: turns off every stylistic rule above. Keep it last.
    prettierConfig,
];
