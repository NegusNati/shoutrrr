import { cpSync } from 'node:fs';
import path from 'node:path';

import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import { tanstackRouter } from '@tanstack/router-plugin/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv } from 'vite';

import { resolveAppVersion } from './resolve-app-version';

// Copy the emojibase `en` locale into public/ so Frimousse and the emoji
// typeahead fetch it same-origin. The app's CSP (connect-src 'self') blocks
// Frimousse's default jsdelivr CDN, so the data must be served from our origin.
function copyEmojiData() {
    const copy = () => {
        try {
            cpSync('node_modules/emojibase-data/en', 'public/emoji/en', {
                recursive: true,
            });
        } catch (error) {
            throw new Error(
                'copy-emoji-data: could not copy node_modules/emojibase-data/en ' +
                    'to public/emoji/en. Run `bun install` to restore the ' +
                    `emojibase-data dependency. (${(error as Error).message})`,
            );
        }
    };

    return {
        name: 'copy-emoji-data',
        buildStart: copy,
        configureServer: copy,
    };
}

/**
 * Build config for the standalone SPA (web/), served by Laravel at /app/*.
 * It shares the repo's single dependency set but emits to its own build
 * directory (public/build-spa) and hot file (public/spa.hot).
 *
 * Dev: run `vite --config vite.spa.config.ts` for module serving + HMR and
 * open the app through Laravel (e.g. http://localhost:8000/app) — API calls
 * stay same-origin, so Sanctum stateful session auth works with no proxy
 * or CORS setup.
 */
export default defineConfig(({ mode }) => {
    const environment = {
        ...loadEnv(mode, process.cwd(), ''),
        ...process.env,
    };

    const appUrl = new URL(environment.APP_URL || 'http://localhost');
    const configuredHmrHost = (environment.VITE_HMR_HOST || '').trim();
    const hmrHost =
        configuredHmrHost ||
        (appUrl.protocol === 'https:' ? 'localhost' : appUrl.hostname);
    const vitePort = Number(environment.VITE_SPA_PORT || 5174);

    return {
        define: {
            __APP_VERSION__: JSON.stringify(resolveAppVersion()),
        },
        resolve: {
            alias: [
                // Wayfinder-generated route helpers keep their original
                // specifier so URL builders stay shared between frontends.
                {
                    find: /^@\/routes/,
                    replacement: path.resolve(__dirname, 'resources/js/routes'),
                },
                {
                    find: /^@\/actions/,
                    replacement: path.resolve(
                        __dirname,
                        'resources/js/actions',
                    ),
                },
                { find: '@', replacement: path.resolve(__dirname, 'web/src') },
            ],
        },
        server: {
            host: '0.0.0.0',
            port: vitePort,
            strictPort: false,
            cors: true,
            hmr: {
                host: hmrHost,
            },
        },
        plugins: [
            copyEmojiData(),
            // The router plugin must run before the React plugin so it can
            // transform route files and regenerate the route tree.
            tanstackRouter({
                target: 'react',
                autoCodeSplitting: true,
                routesDirectory: 'web/src/routes',
                generatedRouteTree: 'web/src/routeTree.gen.ts',
            }),
            laravel({
                input: ['web/src/main.tsx'],
                buildDirectory: 'build-spa',
                hotFile: 'public/spa.hot',
                refresh: true,
            }),
            react({
                babel: {
                    plugins: ['babel-plugin-react-compiler'],
                },
            }),
            tailwindcss(),
            ...(environment.SKIP_WAYFINDER_GENERATE
                ? []
                : [
                      wayfinder({
                          formVariants: true,
                      }),
                  ]),
        ],
    };
});
