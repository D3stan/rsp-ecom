import { sentryVitePlugin } from '@sentry/vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig, type Plugin } from 'vite';

// One release name per build. The browser source maps are uploaded under it and config/sentry.php reads it back from
// public/build/release.txt, so server and browser events are tagged with the same release.
const release = process.env.SENTRY_RELEASE ?? `build-${new Date().toISOString().replace(/[:.]/g, '-')}`;

// Source maps are only produced and uploaded when a Sentry auth token is present at build time (the Docker build).
const uploadSourceMaps = Boolean(process.env.SENTRY_AUTH_TOKEN);

const writeReleaseFile = (): Plugin => ({
    name: 'write-release-file',
    apply: 'build',
    closeBundle() {
        mkdirSync('public/build', { recursive: true });
        writeFileSync('public/build/release.txt', release);
    },
});

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
        writeReleaseFile(),
        uploadSourceMaps &&
            sentryVitePlugin({
                org: process.env.SENTRY_ORG,
                project: process.env.SENTRY_PROJECT,
                authToken: process.env.SENTRY_AUTH_TOKEN,
                url: process.env.SENTRY_URL,
                release: { name: release },
                sourcemaps: { filesToDeleteAfterUpload: ['public/build/**/*.map'] },
                telemetry: false,
            }),
    ],
    build: {
        sourcemap: uploadSourceMaps ? 'hidden' : false,
    },
    esbuild: {
        jsx: 'automatic',
    },
    resolve: {
        alias: {
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
        },
    },
});
