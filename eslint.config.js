import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import i18next from 'eslint-plugin-i18next';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import typescript from 'typescript-eslint';

/** @type {import('eslint').Linter.Config[]} */
export default [
    js.configs.recommended,
    ...typescript.configs.recommended,
    {
        ...react.configs.flat.recommended,
        ...react.configs.flat['jsx-runtime'], // Required for React 17+
        languageOptions: {
            globals: {
                ...globals.browser,
            },
        },
        rules: {
            'react/react-in-jsx-scope': 'off',
            'react/prop-types': 'off',
            'react/no-unescaped-entities': 'off',
        },
        settings: {
            react: {
                version: 'detect',
            },
        },
    },
    {
        plugins: {
            'react-hooks': reactHooks,
        },
        rules: {
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',
        },
    },
    // i18n guard for the storefront: no hardcoded user-visible strings in customer-facing
    // pages and components. Admin screens are intentionally excluded (WS-I scope); every
    // flagged string must go through t()/useTranslation() with keys in all 5 locales.
    {
        files: ['resources/js/pages/**/*.tsx', 'resources/js/components/**/*.tsx'],
        ignores: [
            // Admin screens are out of WS-I scope (see the overview); admin-sidebar lives in the
            // components root but is admin-only chrome.
            'resources/js/pages/Admin/**',
            'resources/js/components/admin/**',
            'resources/js/components/admin-sidebar.tsx',
            // Dead dev-only demos: not routed and not imported anywhere (storefront never renders them).
            'resources/js/pages/ring-masked-image-test.tsx',
            'resources/js/components/RingMaskedImageDemo.tsx',
        ],
        plugins: {
            i18next,
        },
        rules: {
            'i18next/no-literal-string': [
                'error',
                {
                    mode: 'jsx-only',
                    // Only check user-visible JSX text and the accessibility/label attributes;
                    // technical attributes (className, type, etc.) stay excluded by default.
                    'jsx-attributes': {
                        include: ['alt', 'title', 'placeholder', 'aria-label'],
                    },
                    // Preserve the plugin defaults and add project helpers whose string arguments
                    // are technical field keys / class names, not user-visible copy.
                    callees: {
                        exclude: [
                            'i18n(ext)?',
                            't',
                            'require',
                            'addEventListener',
                            'removeEventListener',
                            'postMessage',
                            'getElementById',
                            'dispatch',
                            'commit',
                            'includes',
                            'indexOf',
                            'endsWith',
                            'startsWith',
                            'invalidProps',
                            'stars',
                        ],
                    },
                },
            ],
        },
    },
    {
        ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'tailwind.config.js'],
    },
    prettier, // Turn off all rules that might conflict with Prettier
];
