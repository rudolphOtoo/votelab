import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Self-hosted Instrument Sans, Latin subset, WOFF2 only (resources/fonts/instrument-sans, D-120).
            // 400 (body) and 600 (headings, buttons) are above the fold on every page, so they are preloaded.
            fonts: [
                local('Instrument Sans', {
                    display: 'swap',
                    optimizedFallbacks: false,
                    preload: [{ weight: 400 }, { weight: 600 }],
                    variants: [
                        { src: 'resources/fonts/instrument-sans/instrument-sans-latin-400.woff2', weight: 400 },
                        { src: 'resources/fonts/instrument-sans/instrument-sans-latin-500.woff2', weight: 500 },
                        { src: 'resources/fonts/instrument-sans/instrument-sans-latin-600.woff2', weight: 600 },
                    ],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
