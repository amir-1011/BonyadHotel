import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/money-input.js',
                'resources/js/room-type-form.js',
            ],
            publicDirectory: 'public_html',
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        outDir: 'public_html/build',
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
