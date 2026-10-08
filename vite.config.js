import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const port = 5173;
const ddevUrl = process.env.DDEV_PRIMARY_URL || ''
const origin = ddevUrl ? `${ddevUrl}:${port}` : null;


export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/sass/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: ddevUrl ? {
        // Respond to all network requests
        host: "0.0.0.0",
        port: 5173,
        strictPort: true,
        origin: `${process.env.DDEV_PRIMARY_URL.replace(/:\d+$/, "")}:5173`,
        cors: {
            origin: /https?:\/\/([A-Za-z0-9\-\.]+)?(\.ddev\.site)(?::\d+)?$/,
        },
    } : {},
    build: {
        manifest: 'manifest.json',
    },
});
