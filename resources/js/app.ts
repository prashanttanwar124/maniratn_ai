import '../css/app.css';

// PrimeVue styles
import 'primeicons/primeicons.css';

// Sakai layout styles
import './assets/styles.scss';

import { createInertiaApp } from '@inertiajs/vue3';
import { definePreset } from '@primevue/themes';
import Nora from '@primevue/themes/nora';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import DialogService from 'primevue/dialogservice';
import ToastService from 'primevue/toastservice';

// PrimeVue directives
import AnimateOnScroll from 'primevue/animateonscroll';
import BadgeDirective from 'primevue/badgedirective';
import Ripple from 'primevue/ripple';
import StyleClass from 'primevue/styleclass';
import Tooltip from 'primevue/tooltip';

import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Maniratn AI';

// Custom Theme Preset (Emerald & Gold)
const ManiratnTheme = definePreset(Nora, {
    semantic: {
        primary: {
            50: '#f2f8f6',
            100: '#e1f0eb',
            200: '#c5e2d8',
            300: '#99ccbe',
            400: '#64af9f',
            500: '#1c3633',
            600: '#182f2c',
            700: '#142826',
            800: '#10201e',
            900: '#0c1817',
            950: '#060d0c',
        },
    },
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        app.use(plugin);
        app.use(ZiggyVue);
        app.use(ToastService);
        app.use(ConfirmationService);
        app.use(DialogService);

        app.use(PrimeVue, {
            theme: {
                preset: ManiratnTheme,
                options: {
                    darkModeSelector: '.dark',
                },
            },
            ripple: true,
        });

        // PrimeVue directives
        app.directive('ripple', Ripple);
        app.directive('tooltip', Tooltip);
        app.directive('styleclass', StyleClass);
        app.directive('badge', BadgeDirective);
        app.directive('animateonscroll', AnimateOnScroll);

        app.mount(el);
    },
    progress: {
        color: '#3B82F6',
    },
});
