import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import axios from 'axios';
import { createApp, Fragment, h } from 'vue';
import ConfirmDialogHost from '@/components/ConfirmDialogHost.vue';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import ReaderLayout from '@/layouts/ReaderLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { applyBranding } from '@/lib/branding';
import { initializeFlashToast } from '@/lib/flashToast';
import { installI18n } from '@/lib/i18n';
import { initializePwa } from '@/lib/pwa';

configureEcho({
    broadcaster: 'reverb',
    authEndpoint: '/api/broadcasting/auth',
});

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        const publicPages = [
            'Home',
            'Welcome',
            'Portal/Index',
            'Portal/CommunityShow',
            'ErrorPage',
            'Events/Index',
            'Events/Show',
            'Events/Register',
            'Posts/PublicIndex',
            'Posts/PublicShow',
            'Admin/ClassroomLabels',
            'Gallery/Index',
            'Library/Index',
            'LiveStreams/Show',
        ];

        switch (true) {
            case publicPages.includes(name):
                return null;
            case name === 'settings/Workspace':
                return null;
            case name === 'Library/Bible':
                return ReaderLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
    setup: ({ el, App, props, plugin }) => {
        const app = createApp({
            render: () => h(Fragment, [h(App, props), h(ConfirmDialogHost)]),
        });

        app.use(plugin);
        installI18n(app, props.initialPage.props.locale);
        applyBranding(props.initialPage.props.branding);
        app.mount(el as HTMLElement);
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

initializePwa();
