import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/persian-digits.js',
                'resources/js/bootstrap-collapse-navigate.js',
                'resources/js/image-upload-gate.js',
                'resources/js/money-input.js',
                'resources/js/room-type-form.js',
                'resources/js/rsb-layout-sort.js',
                'resources/js/rsb-datepicker.js',
                'resources/js/occupancy-calendar.js',
                'resources/js/jalali-date-today.js',
                'resources/js/dashboard-accommodation-filter.js',
                'resources/js/admin-overview-stats.js',
                'resources/js/bnb-room-picker.js',
                'resources/js/program-datepicker.js',
                'resources/js/cancellation-settle-datepicker.js',
                'resources/js/facility-detail-modal.js',
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
