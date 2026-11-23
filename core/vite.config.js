import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/*
 * Vite configuration for Prince Byron.
 *
 * Notes:
 * - No external font CDN dependency at runtime; fonts are served locally
 *   or embedded in the CSS bundle to work on slow/offline connections.
 * - Alpine.js is imported in app.js so it is tree-shaken into the bundle.
 * - Lucide SVG icon subset will be inlined in Blade templates — no icon CDN.
 * - Build with: npm run build (before packaging for deployment)
 * - Dev with:   npm run dev
 */
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        // Generate a manifest for versioned asset references in Blade
        manifest: 'manifest.json',
        rollupOptions: {
            output: {
                // Predictable chunk names for cache-busting
                entryFileNames: 'js/[name]-[hash].js',
                chunkFileNames: 'js/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash].[ext]',
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
