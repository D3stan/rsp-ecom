import { resolve } from 'node:path';
import { defineConfig } from 'vitest/config';

// Unit tests for pure frontend logic (no DOM). Mirrors the `@/` alias from tsconfig.json
// (laravel-vite-plugin provides it implicitly in vite.config.ts).
export default defineConfig({
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
        },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
        environment: 'node',
    },
});
