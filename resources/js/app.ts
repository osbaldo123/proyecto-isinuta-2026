import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import { vNumeric } from './directives/vNumeric';
import { vTablaResponsiva } from './directives/vTablaResponsiva';
import { applyAppLayout } from './utils/applyAppLayout';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare global {
    interface Window {
        Ziggy?: {
            url?: string;
            port?: number | null;
            defaults?: Record<string, unknown>;
            routes?: Record<string, unknown>;
        };
    }
}

// Evita 419 por APP_URL distinto al host real (localhost vs 127.0.0.1, etc.).
if (typeof window !== 'undefined' && window.Ziggy) {
    try {
        const ziggyUrl = new URL(window.Ziggy.url || '/', window.location.origin);
        if (ziggyUrl.origin !== window.location.origin) {
            window.Ziggy.url = `${window.location.origin}${ziggyUrl.pathname.replace(/\/$/, '')}`;
        }
        window.Ziggy.port = window.location.port ? Number(window.location.port) : null;
    } catch {
        window.Ziggy.url = window.location.origin;
        window.Ziggy.port = null;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        );

        applyAppLayout(page.default);

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .directive('numeric', vNumeric)
            .directive('tabla-responsiva', vTablaResponsiva)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
