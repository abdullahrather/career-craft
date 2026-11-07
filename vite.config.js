import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny, local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Downloaded at build time and served by the app, so browsers never contact a font service.
                bunny('Instrument Sans', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Fraunces', {
                    weights: [500, 600, 700],
                }),
                // Fonts for CVs, kept in resources/fonts so PDF exports can use the same files.
                // Only CV pages load them, so they aren't preloaded.
                local('IBM Plex Sans', {
                    variants: [400, 600, 700].map((weight) => ({ src: `resources/fonts/ibm-plex-sans-${weight}.woff2`, weight })),
                    preload: false,
                }),
                local('Source Serif 4', {
                    variants: [400, 600, 700].map((weight) => ({ src: `resources/fonts/source-serif-4-${weight}.woff2`, weight })),
                    preload: false,
                }),
                local('Lora', {
                    variants: [400, 600, 700].map((weight) => ({ src: `resources/fonts/lora-${weight}.woff2`, weight })),
                    preload: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // In Docker the dev server listens on every address, and the browser reaches it on localhost.
        host: '0.0.0.0',
        hmr: { host: 'localhost' },
        watch: {
            // File change events don't cross a Windows mount into the container, so poll.
            usePolling: true,
            ignored: ['**/storage/framework/views/**', '**/vendor/**'],
        },
    },
});
