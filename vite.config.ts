import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import os from 'node:os';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { defineConfig } from 'vite';

function detectLanIp(): string {
    if (process.env.VITE_DEV_HOST) {
        return process.env.VITE_DEV_HOST;
    }

    // Solo en esta PC (APP_URL=localhost): evita IPs de VPN/adaptadores viejos.
    // Para entrar desde el celular en la misma Wi‑Fi: VITE_DEV_HOST=192.168.x.x
    const appUrl = process.env.APP_URL ?? '';
    if (/localhost|127\.0\.0\.1/i.test(appUrl)) {
        return 'localhost';
    }

    const skip = /vmware|virtualbox|vethernet|hyper-?v|radmin|loopback|bluetooth|tap|tun|docker|teredo|isatap|wsl|vpngate|hamachi/i;
    const candidates: string[] = [];

    for (const [name, addresses] of Object.entries(os.networkInterfaces())) {
        if (skip.test(name)) {
            continue;
        }

        for (const address of addresses ?? []) {
            const family = String(address.family);
            if (
                (family === 'IPv4' || family === '4') &&
                !address.internal &&
                !address.address.startsWith('169.254.')
            ) {
                candidates.push(address.address);
            }
        }
    }

    // Preferir Wi‑Fi típica 192.168.* sobre otras redes privadas.
    candidates.sort((a, b) => scorePrivateIp(b) - scorePrivateIp(a));

    return candidates[0] ?? 'localhost';
}

function scorePrivateIp(ip: string): number {
    if (ip.startsWith('192.168.')) {
        return 3;
    }
    if (/^172\.(1[6-9]|2\d|3[0-1])\./.test(ip)) {
        return 2;
    }
    if (ip.startsWith('10.')) {
        return 1;
    }

    return 0;
}

const lanIp = detectLanIp();

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5175,
        strictPort: false,
        cors: true,
        allowedHosts: true,
        hmr: {
            host: lanIp,
        },
    },
    css: {
        postcss: {
            plugins: [tailwindcss, autoprefixer],
        },
    },
});
