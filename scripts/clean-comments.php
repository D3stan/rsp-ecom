#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Clean narrative comments from PHP files while strictly preserving annotations,
 * safety/invariant markers, control-flow hints, linter directives, and license headers.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from the command line.\n");
    exit(1);
}

/**
 * Check if a comment should be strictly preserved.
 */
function isProtectedComment(string $text, int $tokenId): bool
{
    // Safety & invariant markers (case-insensitive)
    if (preg_match('/\b(SAFETY|INVARIANT|CRITICAL):/i', $text)) {
        return true;
    }

    // Control flow markers (case-insensitive)
    if (preg_match('/\b(no break|fallthrough)\b/i', $text)) {
        return true;
    }

    // Linter and directive comments
    if (preg_match('/(@ts-[\w\-]+|eslint-|phpcs:|pest:)/i', $text)) {
        return true;
    }

    // Attribution / license headers (case-insensitive)
    if (preg_match('/(\b(license|copyright)\b|\(c\)|SPDX-)/i', $text)) {
        return true;
    }

    // Protected tags
    $protectedTags = [
        '@param', '@return', '@var', '@throws',
        '@property', '@property-read', '@property-write',
        '@deprecated', '@see', '@method', '@mixin',
        '@template', '@extends', '@implements', '@use',
        '@phpstan-', '@psalm-', '@ts-',
    ];
    foreach ($protectedTags as $tag) {
        if (stripos($text, $tag) !== false) {
            return true;
        }
    }

    // For docblocks, preserve if it contains any valid @tag annotation
    if ($tokenId === T_DOC_COMMENT) {
        if (preg_match('/(?:\s|\*|\{)@([a-zA-Z0-9_\-]+)/', $text)) {
            return true;
        }
    }

    return false;
}

/**
 * Clean comments from PHP source code.
 *
 * @return array{0: string, 1: int} [cleaned code, removed count]
 */
function cleanPhpComments(string $code): array
{
    try {
        $tokens = PhpToken::tokenize($code, TOKEN_PARSE);
    } catch (ParseError $e) {
        throw new RuntimeException('Parse error in original code: '.$e->getMessage(), 0, $e);
    }

    $n = count($tokens);
    $rangesToDelete = [];
    $removedCount = 0;

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (! $t->is([T_COMMENT, T_DOC_COMMENT])) {
            continue;
        }

        if (isProtectedComment($t->text, $t->id)) {
            continue;
        }

        $removedCount++;
        $startPos = $t->pos;
        $endPos = $t->pos + strlen($t->text);

        // Check if there is non-whitespace code before this token on the same line
        $hasCodeBefore = false;
        for ($j = $i - 1; $j >= 0; $j--) {
            $prev = $tokens[$j];
            if ($prev->is(T_WHITESPACE)) {
                if (str_contains($prev->text, "\n")) {
                    break;
                }
            } elseif ($prev->is(T_OPEN_TAG)) {
                break;
            } else {
                $hasCodeBefore = true;
                break;
            }
        }

        // Check if there is non-whitespace code after this token on the same line
        $hasCodeAfter = false;
        for ($j = $i + 1; $j < $n; $j++) {
            $next = $tokens[$j];
            if ($next->is(T_WHITESPACE)) {
                if (str_contains($next->text, "\n")) {
                    break;
                }
            } elseif ($next->is(T_CLOSE_TAG)) {
                break;
            } else {
                $hasCodeAfter = true;
                break;
            }
        }

        if (! $hasCodeBefore && ! $hasCodeAfter) {
            // Standalone comment line: remove indentation, comment, and trailing newline
            $delStart = $startPos;
            while ($delStart > 0 && ($code[$delStart - 1] === ' ' || $code[$delStart - 1] === "\t")) {
                $delStart--;
            }
            $delEnd = $endPos;
            while ($delEnd < strlen($code) && ($code[$delEnd] === ' ' || $code[$delEnd] === "\t")) {
                $delEnd++;
            }
            if ($delEnd < strlen($code) && $code[$delEnd] === "\r") {
                $delEnd++;
            }
            if ($delEnd < strlen($code) && $code[$delEnd] === "\n") {
                $delEnd++;
            }
            $rangesToDelete[] = [$delStart, $delEnd];
        } elseif ($hasCodeBefore && ! $hasCodeAfter) {
            // Trailing inline comment: remove spaces before comment and the comment text
            $delStart = $startPos;
            while ($delStart > 0 && ($code[$delStart - 1] === ' ' || $code[$delStart - 1] === "\t")) {
                $delStart--;
            }
            $rangesToDelete[] = [$delStart, $endPos];
        } else {
            // Mid-line comment: remove the comment text
            $rangesToDelete[] = [$startPos, $endPos];
        }
    }

    if ($removedCount === 0) {
        return [$code, 0];
    }

    // Merge overlapping or adjacent ranges
    usort($rangesToDelete, fn ($a, $b) => $a[0] <=> $b[0]);
    $merged = [];
    foreach ($rangesToDelete as $r) {
        if (empty($merged)) {
            $merged[] = $r;
        } else {
            $lastIdx = count($merged) - 1;
            if ($r[0] <= $merged[$lastIdx][1]) {
                $merged[$lastIdx][1] = max($merged[$lastIdx][1], $r[1]);
            } else {
                $merged[] = $r;
            }
        }
    }

    // Delete ranges from back to front to keep character offsets stable
    usort($merged, fn ($a, $b) => $b[0] <=> $a[0]);
    $out = $code;
    foreach ($merged as [$s, $e]) {
        $out = substr($out, 0, $s).substr($out, $e);
    }

    // Clean trailing whitespace on each line
    $out = preg_replace('/[ \t]+$/m', '', $out);

    // Collapse 3 or more consecutive newlines down to 2 (at most one blank line)
    $out = preg_replace("/(\r?\n){3,}/", "\n\n", $out);

    // Ensure final newline if the original file had one or if code is non-empty
    if (str_ends_with($code, "\n") && ! str_ends_with($out, "\n")) {
        $out .= "\n";
    }

    // Validate that cleaned code remains syntactically valid PHP
    try {
        PhpToken::tokenize($out, TOKEN_PARSE);
    } catch (ParseError $e) {
        throw new RuntimeException('Cleaned code failed syntax validation: '.$e->getMessage(), 0, $e);
    }

    return [$out, $removedCount];
}

/**
 * Collect all target files from path arguments.
 *
 * @param  string[]  $paths
 * @param  string[]  $extensions
 * @return string[]
 */
function collectFiles(array $paths, array $extensions): array
{
    $files = [];
    $extLookup = array_fill_keys(array_map('strtolower', $extensions), true);
    $skippedDirs = ['vendor', 'node_modules', '.git', 'storage', 'bootstrap/cache'];

    foreach ($paths as $path) {
        if (! file_exists($path)) {
            fwrite(STDERR, "Path does not exist: {$path}\n");

            continue;
        }

        if (is_file($path)) {
            $files[] = realpath($path) ?: $path;

            continue;
        }

        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $item) {
                if (! $item->isFile()) {
                    continue;
                }

                $filePath = $item->getPathname();

                // Skip vendor, node_modules, etc.
                $skip = false;
                foreach ($skippedDirs as $skippedDir) {
                    if (str_contains($filePath, DIRECTORY_SEPARATOR.$skippedDir.DIRECTORY_SEPARATOR)) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue;
                }

                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if (isset($extLookup[$ext])) {
                    $files[] = realpath($filePath) ?: $filePath;
                }
            }
        }
    }

    $files = array_unique($files);
    sort($files);

    return $files;
}

function showHelp(): void
{
    $help = <<<'HELP'
Usage: php scripts/clean-comments.php [options] <path>...

Options:
  --write         Modify files in-place (default is dry-run mode)
  --dry-run       Run in dry-run mode without modifying files (default)
  --ext=<ext>     Comma-separated extensions to scan in directories (default: php)
  -q, --quiet     Quiet output, only display summary
  -h, --help      Display this help message

Preserved Comments:
  - Annotations: @param, @return, @var, @throws, @property, @deprecated, @see, etc.
  - Directives:  // @ts-, /* eslint-, // phpcs:, // pest:
  - Safety:      // SAFETY:, // INVARIANT:, // CRITICAL:
  - Control:     // no break, // fallthrough, /* fallthrough */
  - License:     license, copyright, (c), SPDX-

Removed Comments:
  - Narrative single-line comments (e.g. // initialize client)
  - Narrative multi-line block comments (/* ... */)
  - Pure description docblocks without @ annotations or safety markers

HELP;
    echo $help;
}

// Parse CLI arguments
$options = [
    'write' => false,
    'ext' => ['php'],
    'quiet' => false,
    'help' => false,
];
$paths = [];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--write') {
        $options['write'] = true;
    } elseif ($arg === '--dry-run') {
        $options['write'] = false;
    } elseif (str_starts_with($arg, '--ext=')) {
        $exts = explode(',', substr($arg, 6));
        $options['ext'] = array_map('trim', array_filter($exts));
    } elseif ($arg === '-q' || $arg === '--quiet') {
        $options['quiet'] = true;
    } elseif ($arg === '-h' || $arg === '--help') {
        $options['help'] = true;
    } elseif (str_starts_with($arg, '-')) {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        exit(1);
    } else {
        $paths[] = $arg;
    }
}

if ($options['help']) {
    showHelp();
    exit(0);
}

if (empty($paths)) {
    fwrite(STDERR, "Error: No target paths specified.\n\n");
    showHelp();
    exit(1);
}

$files = collectFiles($paths, $options['ext']);
if (empty($files)) {
    fwrite(STDERR, "No matching files found.\n");
    exit(0);
}

$mode = $options['write'] ? 'WRITE' : 'DRY-RUN';
if (! $options['quiet']) {
    echo "Running in {$mode} mode on ".count($files)." file(s)...\n\n";
}

$processed = 0;
$modified = 0;
$totalRemoved = 0;
$errors = 0;

foreach ($files as $file) {
    $processed++;
    $originalCode = file_get_contents($file);
    if ($originalCode === false) {
        fwrite(STDERR, "Error reading file: {$file}\n");
        $errors++;

        continue;
    }

    try {
        [$cleanedCode, $removedCount] = cleanPhpComments($originalCode);
    } catch (Throwable $e) {
        fwrite(STDERR, "Error processing {$file}: {$e->getMessage()}\n");
        $errors++;

        continue;
    }

    if ($removedCount > 0) {
        $modified++;
        $totalRemoved += $removedCount;

        if ($options['write']) {
            file_put_contents($file, $cleanedCode);
            if (! $options['quiet']) {
                echo "[MODIFIED] {$file} (removed {$removedCount} comments)\n";
            }
        } else {
            if (! $options['quiet']) {
                echo "[WOULD MODIFY] {$file} (would remove {$removedCount} comments)\n";
            }
        }
    } elseif (! $options['quiet']) {
        echo "[UNCHANGED] {$file}\n";
    }
}

echo "\nSummary:\n";
echo "  Mode: {$mode}\n";
echo "  Files checked: {$processed}\n";
echo '  Files '.($options['write'] ? 'modified' : 'to modify').": {$modified}\n";
echo '  Comments '.($options['write'] ? 'removed' : 'removable').": {$totalRemoved}\n";

if ($errors > 0) {
    echo "  Errors: {$errors}\n";
    exit(1);
}

exit(0);
