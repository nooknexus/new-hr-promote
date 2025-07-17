import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'path';


export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/assets/vendor/fontawesome-free/css/all.min.css', 
                'resources/assets/css/sb-admin-2.min.css',
                'resources/assets/vendor/jquery/jquery.min.js',
                'resources/assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
                'resources/assets/vendor/jquery-easing/jquery.easing.min.js',
                'resources/assets/js/sb-admin-2.min.js',
            ],
            refresh: true,
        }),

    ],
    resolve: {
        alias: {
            '$': 'jquery',
            'jquery': 'jquery',
        },
    },
    build: {
        commonjsOptions: {
            include: ['node_modules/**'],
        },
    },
});
