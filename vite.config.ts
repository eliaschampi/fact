import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
        svelte(),
    ],
    resolve: {
        alias: {
            '@': path.resolve('resources/js'),
        },
    },
    optimizeDeps: {
        exclude: ['@lumi-ui/svelte'],
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: { host: '127.0.0.1' },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        warmup: {
            clientFiles: ['./resources/js/app.ts', './resources/js/Layouts/DashboardLayout.svelte'],
        },
    },
});
