/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : menulis konfigurasi Vite ini untuk membundel aset CSS/JS Tailwind v4 serta mengizinkan HMR diakses oleh perangkat lain di jaringan LAN kampus.
 */

import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import os from 'os';

/**
 * Detektor Alamat IP LAN Aktif untuk Dev Server Vite
 *
 * menulis fungsi ini agar server HMR Vite tidak terpaku pada localhost dan bisa diuji langsung dari perangkat mobile/LAN lain.
 *
 * @returns {string} Alamat IPv4 LAN aktif yang dipilih, atau '127.0.0.1' jika tidak ada antarmuka eksternal.
 *
 * Contoh pemanggilan:
 * `const ip = getActiveLanIp();`
 */
function getActiveLanIp() {
    const interfaces = os.networkInterfaces();
    // memprioritaskan adapter Wi-Fi/Ethernet fisik lebih dulu karena adapter virtual (WSL/Docker/VPN) sering mengembalikan IP yang tidak bisa diakses perangkat lain.
    for (const name of Object.keys(interfaces)) {
        if (/wi-?fi|ethernet|local area/i.test(name)) {
            for (const iface of interfaces[name]) {
                if (iface.family === 'IPv4' && !iface.internal) {
                    return iface.address;
                }
            }
        }
    }
    // menyediakan fallback ke sembarang IPv4 non-internal agar lingkungan non-standar (mis. macOS en0 / Linux eth0) tetap bisa jalan.
    for (const name of Object.keys(interfaces)) {
        for (const iface of interfaces[name]) {
            if (iface.family === 'IPv4' && !iface.internal) {
                return iface.address;
            }
        }
    }
    return '127.0.0.1';
}

/**
 * Konfigurasi Utama Bundler Vite untuk SIM-PBJ
 *
 * menggabungkan plugin Laravel Vite, Google Fonts preloading, dan Tailwind CSS v4 dengan konfigurasi server HMR dinamis.
 *
 * @param {object} envContext - Konteks mode build/dev yang disuntikkan otomatis oleh Vite.
 * @param {string} envContext.mode - Nama mode aktif ('development' atau 'production').
 * @returns {import('vite').UserConfig} Objek konfigurasi lengkap Vite.
 *
 * Contoh pemanggilan:
 * `npm run dev` atau `npm run build`
 */
export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    let defaultHost = '127.0.0.1';

    try {
        if (env.APP_URL) {
            defaultHost = new URL(env.APP_URL).hostname || '127.0.0.1';
        }
    } catch {
        defaultHost = '127.0.0.1';
    }

    // menyelaraskan host HMR dengan hostname APP_URL agar akses dari 127.0.0.1 tidak gagal WebSocket, sambil tetap mendukung VITE_DEV_IP=lan untuk uji perangkat jaringan lokal.
    const explicitDevIp = process.env.VITE_DEV_IP || env.VITE_DEV_IP;
    const hmrHost = explicitDevIp === 'lan' ? getActiveLanIp() : (explicitDevIp || defaultHost);

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
            // mengikat ke 0.0.0.0 dengan strictPort agar server tetap menerima koneksi dari host lokal maupun LAN sesuai origin/hmr.
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            cors: true,
            origin: `http://${hmrHost}:5173`,
            hmr: {
                host: hmrHost,
            },
            watch: {
                // mengecualikan cache Blade terkompilasi supaya perubahan view tidak memicu reload loop tanpa henti.
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
