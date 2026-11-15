import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import os from 'os';

function getActiveLanIp() {
    const interfaces = os.networkInterfaces();
    // Prioritaskan adapter Wi-Fi atau Ethernet fisik
    for (const name of Object.keys(interfaces)) {
        if (/wi-?fi|ethernet|local area/i.test(name)) {
            for (const iface of interfaces[name]) {
                if (iface.family === 'IPv4' && !iface.internal) {
                    return iface.address;
                }
            }
        }
    }
    // Fallback ke sembarang IPv4 non-internal
    for (const name of Object.keys(interfaces)) {
        for (const iface of interfaces[name]) {
            if (iface.family === 'IPv4' && !iface.internal) {
                return iface.address;
            }
        }
    }
    return '127.0.0.1';
}

export default defineConfig(({ mode }) => {
    const lanIp = process.env.VITE_DEV_IP || getActiveLanIp();

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                ],
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
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            cors: true,
            origin: `http://${lanIp}:5173`,
            hmr: {
                host: lanIp,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
