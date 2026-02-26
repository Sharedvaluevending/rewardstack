import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // PWA (customer portal install support)
        // Conservative: precache only static assets (no HTML/API caching).
        VitePWA({
            // We'll register manually from app.js to control update prompting.
            injectRegister: null,
            registerType: 'prompt',

            // We serve a static manifest from /public.
            manifest: false,

            // Laravel serves built assets from /build/, but we want the SW at site root (/sw.js)
            // so it can control /portal and other routes.
            buildBase: '/',
            scope: '/',

            // Ensure icons/manifest are included in precache.
            includeAssets: [
                'favicon.png',
                'favicon.svg',
                'apple-touch-icon.png',
                'manifest.webmanifest',
                'pwa/icons/*.png',
                'pwa/icons/*.svg',
            ],

            workbox: {
                // Write the SW into Laravel's web root (/public/sw.js) so it can control /portal.
                // NOTE: workbox's swDest is resolved from the project root, not Vite's outDir.
                swDest: 'public/sw.js',
                cleanupOutdatedCaches: true,
                globPatterns: ['**/*.{js,css,mjs,map,png,svg,ico,woff2,woff,ttf,json}'],
                // Do NOT use an SPA navigation fallback for Laravel/Inertia.
                // (Default Workbox behavior would try to serve index.html for navigations.)
                navigateFallback: null,
            },

            // Keep SW disabled during `vite dev` unless explicitly enabled.
            devOptions: {
                enabled: false,
            },
        }),
    ],
    build: {
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('vue') || id.includes('@inertiajs') || id.includes('@vue')) {
                            return 'vendor-vue';
                        }
                        if (id.includes('lodash') || id.includes('axios')) {
                            return 'vendor-utils';
                        }
                        return 'vendor';
                    }
                },
            },
        },
    },
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
