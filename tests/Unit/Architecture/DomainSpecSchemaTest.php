<?php

declare(strict_types=1);

/**
 * Architectural domain specifications schema and budget validation.
 */
$expectedDomains = [
    '01-orders-and-checkout.md',
    '02-taxation-and-localization.md',
    '03-catalog-and-inventory.md',
    '04-shipping-and-tracking.md',
    '05-cart-and-wishlist.md',
    '06-marketing-and-comms.md',
    '07-settings-registry.md',
    '08-auth-and-accounts.md',
    '09-frontend-core-and-ui.md',
];

$getDomainsDir = function (): string {
    $dir = dirname(__DIR__, 3).'/.ai/rules/domains';

    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
};

$getRulesDir = function (): string {
    return dirname(__DIR__, 3).'/.ai/rules';
};

it('enforces the 150-line token budget for all domain specs', function () use ($getDomainsDir) {
    $domainsDir = $getDomainsDir();
    $files = glob($domainsDir.'/*.md') ?: [];

    if (empty($files)) {
        expect($files)->toBeArray();

        return;
    }

    foreach ($files as $file) {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        expect(count($lines))->toBeLessThanOrEqual(
            150,
            basename($file).' exceeds the 150-line token budget ('.count($lines).' lines).'
        );
    }
});

it('validates the standard 5-section schema and frontmatter', function () use ($getDomainsDir) {
    $domainsDir = $getDomainsDir();
    $files = glob($domainsDir.'/*.md') ?: [];

    if (empty($files)) {
        expect($files)->toBeArray();

        return;
    }

    foreach ($files as $file) {
        $content = file_get_contents($file);
        $filename = basename($file);

        // 1. YAML frontmatter with domain: and version:
        expect(preg_match('/^---\r?\n(.*?)\r?\n---/s', $content, $matches))
            ->toBe(1, "{$filename} must contain YAML frontmatter enclosed in ---");

        $frontmatter = $matches[1];
        expect(preg_match('/^domain:\s*.+/m', $frontmatter))
            ->toBe(1, "{$filename} frontmatter must declare 'domain:'");
        expect(preg_match('/^version:\s*.+/m', $frontmatter))
            ->toBe(1, "{$filename} frontmatter must declare 'version:'");

        // 2. The 5 standard markdown sections
        expect(preg_match('/^##\s*1\.\s*Scope\s*&\s*Responsibility/m', $content))
            ->toBe(1, "{$filename} is missing section '## 1. Scope & Responsibility'");

        expect(preg_match('/^##\s*2\.\s*Core\s*Invariants/m', $content))
            ->toBe(1, "{$filename} is missing section '## 2. Core Invariants'");

        expect(preg_match('/^##\s*3\.\s*(State\s*Machines\s*&\s*Transitions|Lifecycle)/m', $content))
            ->toBe(1, "{$filename} is missing section '## 3. State Machines & Transitions' (or Lifecycle)");

        expect(preg_match('/^##\s*4\.\s*Key\s*Contracts\s*&\s*Entry\s*Points/m', $content))
            ->toBe(1, "{$filename} is missing section '## 4. Key Contracts & Entry Points'");

        expect(preg_match('/^##\s*5\.\s*External\s*Boundaries\s*&\s*Failure\s*Modes/m', $content))
            ->toBe(1, "{$filename} is missing section '## 5. External Boundaries & Failure Modes'");
    }
});

it('ensures all 9 expected domain specs exist', function () use ($expectedDomains, $getDomainsDir) {
    $domainsDir = $getDomainsDir();
    $existing = glob($domainsDir.'/*.md') ?: [];

    if (empty($existing)) {
        $this->markTestSkipped('Domain specs are not yet created (Phase 1).');
    }

    foreach ($expectedDomains as $domainFile) {
        expect(file_exists($domainsDir.'/'.$domainFile))
            ->toBeTrue("Domain spec [{$domainFile}] does not exist in {$domainsDir}.");
    }
});

it('validates that .ai/rules/index.md maps to valid domain specs', function () use ($getRulesDir) {
    $rulesDir = $getRulesDir();
    $indexPath = $rulesDir.'/index.md';

    if (! file_exists($indexPath)) {
        expect(file_exists($indexPath))->toBeFalse();

        return;
    }

    $content = file_get_contents($indexPath);

    // Extract any referenced domain spec files matching domains/*.md or .ai/rules/domains/*.md
    preg_match_all('/(?:domains\/[a-zA-Z0-9_\-]+\.md|\.ai\/rules\/domains\/[a-zA-Z0-9_\-]+\.md)/', $content, $matches);

    $references = array_unique($matches[0]);
    expect($references)->not->toBeEmpty('Expected .ai/rules/index.md to reference domain specs.');

    $baseDir = dirname(__DIR__, 3);
    foreach ($references as $ref) {
        $resolvedPath = str_starts_with($ref, '.ai/rules/')
            ? $baseDir.'/'.$ref
            : $rulesDir.'/'.$ref;

        expect(file_exists($resolvedPath))
            ->toBeTrue("Domain spec [{$ref}] referenced in index.md does not exist on disk at [{$resolvedPath}].");
    }
});
