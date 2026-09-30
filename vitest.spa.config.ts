import { resolve } from 'node:path';

import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

import { resolveAppVersion } from './resolve-app-version';

/**
 * Vitest project for the standalone SPA (web/). Kept separate from
 * vitest.config.ts because the two frontends share the `@/` specifier for
 * different roots — a single config cannot alias both.
 */
export default defineConfig({
    define: {
        __APP_VERSION__: JSON.stringify(resolveAppVersion()),
    },
    plugins: [react()],
    resolve: {
        alias: [
            {
                find: /^@\/routes/,
                replacement: resolve(
                    import.meta.dirname,
                    './resources/js/routes',
                ),
            },
            {
                find: /^@\/actions/,
                replacement: resolve(
                    import.meta.dirname,
                    './resources/js/actions',
                ),
            },
            {
                find: '@',
                replacement: resolve(import.meta.dirname, './web/src'),
            },
        ],
    },
    test: {
        projects: [
            {
                extends: true,
                test: {
                    name: 'spa-node',
                    environment: 'node',
                    include: ['web/src/**/*.test.ts'],
                },
            },
            {
                extends: true,
                test: {
                    name: 'spa-jsdom',
                    environment: 'jsdom',
                    include: ['web/src/**/*.test.tsx'],
                    setupFiles: ['./vitest.setup.ts'],
                },
            },
        ],
    },
});
