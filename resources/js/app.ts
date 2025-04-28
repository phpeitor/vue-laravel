import '../css/app.css';
import './echo';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { router } from '@inertiajs/vue3'; 
import { initializeTheme } from './composables/useAppearance';

// Extend ImportMeta interface for Vite...
interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
    [key: string]: string | boolean | undefined;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
    readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => {
        console.log("Title being set:", title);
        if (title === 'Error403') {
            return `403 - Laravel`; 
        }
        return `${title} - ${appName}`; 
    },
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    }
});

router.on('error', (errors) => {
    if ((errors as any)?.response?.status === 403) {
        window.location.replace('/error/403'); 
    }
});



// This will set light / dark mode on page load...
initializeTheme();

