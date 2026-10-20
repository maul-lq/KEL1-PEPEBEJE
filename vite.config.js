import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/tes_app.css', 'resources/js/tes_app.js'],
            refresh: true,
            fonts: [
                google('Instrument Sans', {
                    weights: [400, 500, 600],
                    preload: [{ weight: 400 }, { weight: 500 }],
                    optimizedFallbacks: false,
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
