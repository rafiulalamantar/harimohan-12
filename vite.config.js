import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Self-hosted with the build, so pages don't wait on fonts.googleapis.com before showing text.
            // Only the two most used weights are preloaded: preloading every file would compete with photos.
            fonts: [
                google('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700, 800],
                    subsets: ['latin'],
                    preload: [{ weight: 400 }, { weight: 800 }],
                    optimizedFallbacks: false,
                }),
                google('Hind Siliguri', {
                    weights: [400, 500, 600, 700],
                    subsets: ['bengali', 'latin'],
                    preload: false,
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
