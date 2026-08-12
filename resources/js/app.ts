import { createInertiaApp } from '@inertiajs/vue3';
import axios from 'axios';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { applyBranding } from '@/lib/branding';
import { initializeFlashToast } from '@/lib/flashToast';
import { installI18n } from '@/lib/i18n';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        const publicPages = [
            'Home',
            'Welcome',
            'Portal/Index',
            'Events/Index',
            'Events/Show',
            'Events/Register',
            'Posts/PublicIndex',
            'Posts/PublicShow',
            'Admin/ClassroomLabels',
            'Gallery/Index',
            'Library/Index',
        ];

        switch (true) {
            case publicPages.includes(name):
                return null;
            case name === 'settings/Workspace':
                return null;
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
    withApp: (app, { page }) => {
        installI18n(app, page.props.locale);
        applyBranding(page.props.branding);
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
