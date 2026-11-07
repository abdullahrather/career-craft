import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
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
