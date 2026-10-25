#!/usr/bin/env node

/**
 * Clean narrative comments from TypeScript and TSX files while strictly preserving
 * compiler directives, linter directives, safety/invariant markers, control-flow hints,
 * JSDoc annotations, and license headers.
 */

import { parse } from '@typescript-eslint/parser';
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

/**
 * Check if a comment should be strictly preserved.
 *
 * @param {string} text - Full raw comment text including // or /* delimiters
 * @param {boolean} isBlock - Whether this comment is a block comment
 * @returns {boolean}
 */
export function isProtectedComment(text, isBlock) {
    const trimmed = text.trim();

    // 1. Triple-slash directives (e.g. /// <reference types="vite/client" />)
    if (/^\/\/\/\s*<[a-zA-Z_-]+/i.test(trimmed)) {
        return true;
    }

    // 2. TypeScript compiler directives
    if (/@ts-(expect-error|ignore|nocheck|check)\b/i.test(trimmed) || /@ts-[\w-]+/i.test(trimmed)) {
        return true;
    }

    // 3. Linter, test, and formatter directives
    if (/(eslint|prettier-ignore|istanbul ignore|c8 ignore|vitest-environment)/i.test(trimmed)) {
        return true;
    }
    if (/^\/\*\s*globals?\s+[a-zA-Z0-9_$,\s*]+\*\//i.test(trimmed)) {
        return true;
    }

    // 4. Safety & invariant markers
    if (/\b(SAFETY|INVARIANT|CRITICAL):/i.test(trimmed)) {
        return true;
    }

    // 5. Control flow hints
    if (/\b(no break|fallthrough)\b/i.test(trimmed)) {
        return true;
    }

    // 6. License and copyright headers
    if (/^\/\*!/i.test(trimmed)) {
        return true;
    }
    if (/@(license|copyright)\b/i.test(trimmed)) {
        return true;
    }
    if (/(SPDX-License-Identifier|SPDX-)/i.test(trimmed)) {
        return true;
    }
    if (/copyright\s+(\(c\)\s*)?\d{4}/i.test(trimmed) || /copyright\s+\(c\)/i.test(trimmed) || /\(c\)\s*\d{4}/i.test(trimmed)) {
        return true;
    }
    if (/\b(mit|apache|bsd|gnu|gpl|mpl)\s+license\b/i.test(trimmed)) {
        return true;
    }

    // 7. JSDoc annotations
    // Preserve any /** ... */ block comment that contains JSDoc annotations like @param, @returns, @type, etc.
    if (isBlock && trimmed.startsWith('/**')) {
        if (/(?:^|\s|\*|\{)@([a-zA-Z0-9_-]+)/.test(trimmed)) {
            return true;
        }
    }

    const protectedTags = [
        '@param',
        '@returns',
        '@return',
        '@type',
        '@typedef',
        '@throws',
        '@property',
        '@prop',
        '@deprecated',
        '@see',
        '@template',
        '@extends',
        '@implements',
        '@default',
        '@example',
        '@enum',
        '@callback',
    ];
    for (const tag of protectedTags) {
        if (trimmed.toLowerCase().includes(tag)) {
            return true;
        }
    }

    return false;
}

/**
 * Clean narrative comments from TypeScript/TSX source code.
 *
 * @param {string} code - Original source code
 * @param {string} [filePath='file.tsx'] - Source file path for parser context
 * @returns {[string, number]} [Cleaned code, removed comment count]
 */
export function cleanTsComments(code, filePath = 'file.tsx') {
    let ast;
    try {
        ast = parse(code, {
            comment: true,
            tokens: true,
            jsx: true,
            filePath,
            range: true,
            loc: true,
        });
    } catch (err) {
        throw new Error(`Parse error in original code: ${err.message}`);
    }

    const unprotectedComments = [];
    const protectedComments = [];

    for (const c of ast.comments) {
        const raw = code.slice(c.range[0], c.range[1]);
        if (isProtectedComment(raw, c.type === 'Block')) {
            protectedComments.push(c);
        } else {
            unprotectedComments.push(c);
        }
    }

    if (unprotectedComments.length === 0) {
        return [code, 0];
    }

    // Find empty JSX expression containers: {/* comment */}
    function walk(node, visitor) {
        if (!node || typeof node !== 'object') return;
        visitor(node);
        for (const k of Object.keys(node)) {
            if (k === 'parent') continue;
            const v = node[k];
            if (Array.isArray(v)) {
                v.forEach((c) => walk(c, visitor));
            } else if (v && typeof v === 'object') {
                walk(v, visitor);
            }
        }
    }

    const emptyJsxContainers = [];
    walk(ast, (n) => {
        if (n.type === 'JSXExpressionContainer' && n.expression.type === 'JSXEmptyExpression') {
            emptyJsxContainers.push(n);
        }
    });

    // Occupied items that must remain on their lines (tokens + protected comments)
    const occupied = [
        ...ast.tokens.map((t) => ({
            start: t.range[0],
            end: t.range[1],
            startLine: t.loc.start.line,
            endLine: t.loc.end.line,
        })),
        ...protectedComments.map((c) => ({
            start: c.range[0],
            end: c.range[1],
            startLine: c.loc.start.line,
            endLine: c.loc.end.line,
        })),
    ];

    const rangesToDelete = [];
    const handledComments = new Set();

    for (const container of emptyJsxContainers) {
        const cStart = container.range[0];
        const cEnd = container.range[1];
        const enclosedComments = ast.comments.filter((c) => c.range[0] >= cStart && c.range[1] <= cEnd);
        if (enclosedComments.length > 0) {
            const anyProtected = enclosedComments.some((c) => protectedComments.includes(c));
            if (!anyProtected) {
                enclosedComments.forEach((c) => handledComments.add(c));

                // Check if there are other tokens/protected comments on container's line
                const nonContainerOccupied = occupied.filter((o) => o.start < cStart || o.end > cEnd);
                const hasBefore = nonContainerOccupied.some((o) => o.endLine === container.loc.start.line && o.end <= cStart);
                const hasAfter = nonContainerOccupied.some((o) => o.startLine === container.loc.end.line && o.start >= cEnd);

                if (!hasBefore && !hasAfter) {
                    let lineStart = cStart;
                    while (lineStart > 0 && code[lineStart - 1] !== '\n' && code[lineStart - 1] !== '\r') {
                        lineStart--;
                    }
                    let lineEnd = cEnd;
                    while (lineEnd < code.length && code[lineEnd] !== '\n' && code[lineEnd] !== '\r') {
                        lineEnd++;
                    }
                    if (lineEnd < code.length && code[lineEnd] === '\r') lineEnd++;
                    if (lineEnd < code.length && code[lineEnd] === '\n') lineEnd++;
                    rangesToDelete.push([lineStart, lineEnd]);
                } else if (hasBefore && !hasAfter) {
                    let delStart = cStart;
                    while (delStart > 0 && (code[delStart - 1] === ' ' || code[delStart - 1] === '\t')) {
                        delStart--;
                    }
                    rangesToDelete.push([delStart, cEnd]);
                } else {
                    rangesToDelete.push([cStart, cEnd]);
                }
            }
        }
    }

    for (const c of unprotectedComments) {
        if (handledComments.has(c)) continue;

        const startPos = c.range[0];
        const endPos = c.range[1];

        const hasBefore = occupied.some((o) => o.endLine === c.loc.start.line && o.end <= startPos);
        const hasAfter = occupied.some((o) => o.startLine === c.loc.end.line && o.start >= endPos);

        if (!hasBefore && !hasAfter) {
            let lineStart = startPos;
            while (lineStart > 0 && code[lineStart - 1] !== '\n' && code[lineStart - 1] !== '\r') {
                lineStart--;
            }
            let lineEnd = endPos;
            while (lineEnd < code.length && code[lineEnd] !== '\n' && code[lineEnd] !== '\r') {
                lineEnd++;
            }
            if (lineEnd < code.length && code[lineEnd] === '\r') lineEnd++;
            if (lineEnd < code.length && code[lineEnd] === '\n') lineEnd++;
            rangesToDelete.push([lineStart, lineEnd]);
        } else if (hasBefore && !hasAfter) {
            let delStart = startPos;
            while (delStart > 0 && (code[delStart - 1] === ' ' || code[delStart - 1] === '\t')) {
                delStart--;
            }
            rangesToDelete.push([delStart, endPos]);
        } else {
            let delStart = startPos;
            let delEnd = endPos;
            if (delStart > 0 && /[([{,} ]/.test(code[delStart - 1]) && delEnd < code.length && code[delEnd] === ' ') {
                delEnd++;
            } else if (delStart > 0 && code[delStart - 1] === ' ' && delEnd < code.length && /[)\]},; ]/.test(code[delEnd])) {
                delStart--;
            }
            rangesToDelete.push([delStart, delEnd]);
        }
    }

    if (rangesToDelete.length === 0) {
        return [code, 0];
    }

    rangesToDelete.sort((a, b) => a[0] - b[0]);
    const merged = [];
    for (const r of rangesToDelete) {
        if (merged.length === 0) {
            merged.push(r);
        } else {
            const last = merged[merged.length - 1];
            if (r[0] <= last[1]) {
                last[1] = Math.max(last[1], r[1]);
            } else {
                merged.push(r);
            }
        }
    }

    merged.sort((a, b) => b[0] - a[0]);
    let out = code;
    for (const [s, e] of merged) {
        out = out.slice(0, s) + out.slice(e);
    }

    out = out.replace(/[ \t]+$/gm, '');
    out = out.replace(/(\r?\n){3,}/g, '\n\n');

    if (code.endsWith('\n') && !out.endsWith('\n')) {
        out += '\n';
    }

    try {
        parse(out, {
            comment: true,
            tokens: true,
            jsx: true,
            filePath,
            range: true,
            loc: true,
        });
    } catch (err) {
        throw new Error(`Cleaned code failed syntax validation: ${err.message}`);
    }

    return [out, unprotectedComments.length];
}

/**
 * Collect all target files from path arguments.
 *
 * @param {string[]} paths - Directory or file paths to scan
 * @param {string[]} extensions - File extensions to match in directories (e.g. ['ts', 'tsx'])
 * @returns {string[]} Sorted array of unique file paths
 */
export function collectFiles(paths, extensions) {
    const files = [];
    const extLookup = new Set(extensions.map((e) => (e.startsWith('.') ? e.slice(1) : e).toLowerCase()));
    const skippedDirs = new Set(['vendor', 'node_modules', '.git', 'storage', 'bootstrap/cache', '.worktrees']);

    function scanDir(dir) {
        let entries;
        try {
            entries = fs.readdirSync(dir, { withFileTypes: true });
        } catch {
            return;
        }

        for (const entry of entries) {
            const name = entry.name;
            const fullPath = path.join(dir, name);

            if (entry.isDirectory()) {
                if (!skippedDirs.has(name)) {
                    scanDir(fullPath);
                }
            } else if (entry.isFile()) {
                const ext = path.extname(name).slice(1).toLowerCase();
                if (extLookup.has(ext)) {
                    files.push(path.resolve(fullPath));
                }
            }
        }
    }

    for (const targetPath of paths) {
        if (!fs.existsSync(targetPath)) {
            process.stderr.write(`Path does not exist: ${targetPath}\n`);
            continue;
        }

        const stat = fs.statSync(targetPath);
        if (stat.isFile()) {
            files.push(path.resolve(targetPath));
        } else if (stat.isDirectory()) {
            scanDir(targetPath);
        }
    }

    return Array.from(new Set(files)).sort();
}

export function showHelp() {
    const help = `Usage: node scripts/clean-comments-ts.js [options] <path>...

Options:
  --write         Modify files in-place (default is dry-run mode)
  --dry-run       Run in dry-run mode without modifying files (default)
  --ext=<ext>     Comma-separated extensions to scan in directories (default: ts,tsx)
  -q, --quiet     Quiet output, only display summary
  -h, --help      Display this help message

Preserved Comments:
  - TypeScript directives: // @ts-expect-error, // @ts-ignore, // @ts-nocheck, // @ts-check, /// <reference ...>
  - Linter directives:     /* eslint-disable */, // eslint-disable-next-line, /* global */, prettier-ignore
  - Safety markers:        // SAFETY:, // INVARIANT:, // CRITICAL:
  - Control flow:          // no break, // fallthrough, /* fallthrough */
  - License & copyright:   @license, @copyright, Copyright (c), SPDX-
  - JSDoc annotations:     any /** ... */ block containing @param, @returns, @type, @typedef, @template, etc.

Removed Comments:
  - Narrative single-line comments (e.g. // wrapper component)
  - Narrative multi-line block comments (/* product image */)
  - Empty description docblocks without @ annotations or safety markers
  - Standalone JSX comment expressions ({/* narrative */})
`;
    process.stdout.write(help);
}

// CLI Execution
const isDirectRun =
    process.argv[1] && (process.argv[1] === fileURLToPath(import.meta.url) || path.resolve(process.argv[1]) === fileURLToPath(import.meta.url));

if (isDirectRun) {
    const options = {
        write: false,
        ext: ['ts', 'tsx'],
        quiet: false,
        help: false,
    };
    const paths = [];

    const rawArgs = process.argv.slice(2);
    for (const arg of rawArgs) {
        if (arg === '--write') {
            options.write = true;
        } else if (arg === '--dry-run') {
            options.write = false;
        } else if (arg.startsWith('--ext=')) {
            const exts = arg
                .slice(6)
                .split(',')
                .map((e) => e.trim())
                .filter(Boolean);
            options.ext = exts;
        } else if (arg === '-q' || arg === '--quiet') {
            options.quiet = true;
        } else if (arg === '-h' || arg === '--help') {
            options.help = true;
        } else if (arg.startsWith('-')) {
            process.stderr.write(`Unknown option: ${arg}\n`);
            process.exit(1);
        } else {
            paths.push(arg);
        }
    }

    if (options.help) {
        showHelp();
        process.exit(0);
    }

    if (paths.length === 0) {
        process.stderr.write('Error: No target paths specified.\n\n');
        showHelp();
        process.exit(1);
    }

    const files = collectFiles(paths, options.ext);
    if (files.length === 0) {
        process.stderr.write('No matching files found.\n');
        process.exit(0);
    }

    const mode = options.write ? 'WRITE' : 'DRY-RUN';
    if (!options.quiet) {
        process.stdout.write(`Running in ${mode} mode on ${files.length} file(s)...\n\n`);
    }

    let processed = 0;
    let modified = 0;
    let totalRemoved = 0;
    let errors = 0;

    for (const file of files) {
        processed++;
        let originalCode;
        try {
            originalCode = fs.readFileSync(file, 'utf8');
        } catch (err) {
            process.stderr.write(`Error reading file: ${file}: ${err.message}\n`);
            errors++;
            continue;
        }

        let cleanedCode;
        let removedCount;
        try {
            [cleanedCode, removedCount] = cleanTsComments(originalCode, file);
        } catch (err) {
            process.stderr.write(`Error processing ${file}: ${err.message}\n`);
            errors++;
            continue;
        }

        if (removedCount > 0) {
            modified++;
            totalRemoved += removedCount;

            if (options.write) {
                fs.writeFileSync(file, cleanedCode, 'utf8');
                if (!options.quiet) {
                    process.stdout.write(`[MODIFIED] ${file} (removed ${removedCount} comments)\n`);
                }
            } else if (!options.quiet) {
                process.stdout.write(`[WOULD MODIFY] ${file} (would remove ${removedCount} comments)\n`);
            }
        } else if (!options.quiet) {
            process.stdout.write(`[UNCHANGED] ${file}\n`);
        }
    }

    process.stdout.write('\nSummary:\n');
    process.stdout.write(`  Mode: ${mode}\n`);
    process.stdout.write(`  Files checked: ${processed}\n`);
    process.stdout.write(`  Files ${options.write ? 'modified' : 'to modify'}: ${modified}\n`);
    process.stdout.write(`  Comments ${options.write ? 'removed' : 'removable'}: ${totalRemoved}\n`);

    if (errors > 0) {
        process.stdout.write(`  Errors: ${errors}\n`);
        process.exit(1);
    }

    process.exit(0);
}
