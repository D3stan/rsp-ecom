import { describe, expect, it } from 'vitest';
import { cleanTsComments, isProtectedComment } from '../../../../scripts/clean-comments-ts.js';

describe('clean-comments-ts', () => {
    describe('isProtectedComment', () => {
        it('preserves TypeScript directives and triple-slash references', () => {
            expect(isProtectedComment('// @ts-expect-error type mismatch', false)).toBe(true);
            expect(isProtectedComment('// @ts-ignore', false)).toBe(true);
            expect(isProtectedComment('// @ts-nocheck', false)).toBe(true);
            expect(isProtectedComment('// @ts-check', false)).toBe(true);
            expect(isProtectedComment('/// <reference types="vite/client" />', false)).toBe(true);
            expect(isProtectedComment('/// <reference path="./global.d.ts" />', false)).toBe(true);
        });

        it('preserves linter and formatter directives', () => {
            expect(isProtectedComment('/* eslint-disable */', true)).toBe(true);
            expect(isProtectedComment('/* eslint-enable */', true)).toBe(true);
            expect(isProtectedComment('// eslint-disable-next-line @typescript-eslint/no-explicit-any', false)).toBe(true);
            expect(isProtectedComment('// eslint-disable-line', false)).toBe(true);
            expect(isProtectedComment('/* global myGlobal */', true)).toBe(true);
            expect(isProtectedComment('/* globals foo, bar */', true)).toBe(true);
            expect(isProtectedComment('// prettier-ignore', false)).toBe(true);
            expect(isProtectedComment('/* istanbul ignore next */', true)).toBe(true);
            expect(isProtectedComment('/* c8 ignore start */', true)).toBe(true);
            expect(isProtectedComment('// vitest-environment jsdom', false)).toBe(true);
        });

        it('preserves safety, invariant, and critical markers', () => {
            expect(isProtectedComment('// SAFETY: buffer size is verified', false)).toBe(true);
            expect(isProtectedComment('// INVARIANT: count >= 0', false)).toBe(true);
            expect(isProtectedComment('// CRITICAL: must execute before render', false)).toBe(true);
            expect(isProtectedComment('/* SAFETY: lock acquired */', true)).toBe(true);
        });

        it('preserves control flow hints', () => {
            expect(isProtectedComment('// no break', false)).toBe(true);
            expect(isProtectedComment('/* fallthrough */', true)).toBe(true);
        });

        it('preserves license and copyright notices', () => {
            expect(isProtectedComment('// @license MIT', false)).toBe(true);
            expect(isProtectedComment('/* @copyright 2026 Acme Corp */', true)).toBe(true);
            expect(isProtectedComment('// SPDX-License-Identifier: MIT', false)).toBe(true);
            expect(isProtectedComment('// Copyright (c) 2026 Company Inc.', false)).toBe(true);
            expect(isProtectedComment('/* Copyright 2026 */', true)).toBe(true);
            expect(isProtectedComment('/*! Minifier-preserved license */', true)).toBe(true);
            expect(isProtectedComment('// Released under the MIT license', false)).toBe(true);
        });

        it('preserves JSDoc blocks with annotations', () => {
            const jsdoc = `/**\n * Calculate total.\n * @param {number} a\n * @returns {number}\n */`;
            expect(isProtectedComment(jsdoc, true)).toBe(true);

            const typedDoc = `/** @type {string} */`;
            expect(isProtectedComment(typedDoc, true)).toBe(true);

            const deprecatedDoc = `/** @deprecated Use newMethod instead */`;
            expect(isProtectedComment(deprecatedDoc, true)).toBe(true);
        });

        it('identifies narrative comments as unprotected', () => {
            expect(isProtectedComment('// Initialize the counter', false)).toBe(false);
            expect(isProtectedComment('// Return early if not found', false)).toBe(false);
            expect(isProtectedComment('/* Product image section */', true)).toBe(false);
            expect(isProtectedComment('/** Simple description with no tags */', true)).toBe(false);
            expect(isProtectedComment('/**\n * Pure narrative description\n * spanning multiple lines with no annotations.\n */', true)).toBe(false);
        });
    });

    describe('cleanTsComments', () => {
        it('cleans narrative comments while preserving protected directives', () => {
            const input = `/// <reference types="vite/client" />
// @license MIT
// @ts-expect-error test error
/* eslint-disable */
// SAFETY: memory is bounded
// INVARIANT: count >= 0
// CRITICAL: do not reorder
// no break

/**
 * @param {number} a
 * @returns {number}
 */
export function add(a: number): number {
    // initialize helper
    const x = 10; // trailing comment
    return a + x;
}

/**
 * Pure description docblock with no tags
 */
export const b = 2;
`;

            const [cleaned, count] = cleanTsComments(input, 'test.ts');
            expect(count).toBe(3);
            expect(cleaned).toContain('/// <reference types="vite/client" />');
            expect(cleaned).toContain('// @license MIT');
            expect(cleaned).toContain('// @ts-expect-error test error');
            expect(cleaned).toContain('/* eslint-disable */');
            expect(cleaned).toContain('// SAFETY: memory is bounded');
            expect(cleaned).toContain('// INVARIANT: count >= 0');
            expect(cleaned).toContain('// CRITICAL: do not reorder');
            expect(cleaned).toContain('// no break');
            expect(cleaned).toContain('@param {number} a');
            expect(cleaned).toContain('@returns {number}');

            expect(cleaned).not.toContain('// initialize helper');
            expect(cleaned).not.toContain('// trailing comment');
            expect(cleaned).not.toContain('Pure description docblock');
            expect(cleaned).toContain('const x = 10;\n');
        });

        it('removes standalone JSX comment expressions and collapses whitespace', () => {
            const input = `import React from 'react';

export function Component() {
    return (
        <div>
            {/* Search Bar section */}
            {/* eslint-disable-next-line */}
            <input type="text" />
            {/* Table section */}
            <table>
                <tbody>
                    <tr><td>Item</td></tr>
                </tbody>
            </table>
        </div>
    );
}
`;

            const [cleaned, count] = cleanTsComments(input, 'Component.tsx');
            expect(count).toBe(2);
            expect(cleaned).not.toContain('Search Bar section');
            expect(cleaned).not.toContain('Table section');
            expect(cleaned).toContain('{/* eslint-disable-next-line */}');
            expect(cleaned).not.toContain('{}');
        });

        it('cleans mid-line comments inside parameter lists and expressions', () => {
            const input = `function calculate(/* base value */ a: number, /* multiplier */ b: number): number {
    return a * b;
}
`;

            const [cleaned, count] = cleanTsComments(input, 'calc.ts');
            expect(count).toBe(2);
            expect(cleaned).toContain('function calculate(a: number, b: number): number');
        });

        it('returns unchanged code when there are no removable comments', () => {
            const input = `export const answer = 42;\n`;
            const [cleaned, count] = cleanTsComments(input, 'answer.ts');
            expect(count).toBe(0);
            expect(cleaned).toBe(input);
        });

        it('collapses consecutive blank lines to at most one empty line', () => {
            const input = `const a = 1;\n\n// narrative comment\n\nconst b = 2;\n`;
            const [cleaned, count] = cleanTsComments(input, 'test.ts');
            expect(count).toBe(1);
            expect(cleaned).toBe(`const a = 1;\n\nconst b = 2;\n`);
        });
    });
});
