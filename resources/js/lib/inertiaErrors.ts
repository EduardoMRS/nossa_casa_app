import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { t } from '@/lib/i18n';

let initialized = false;

export function initializeInertiaErrorHandler(): void {
    if (initialized || typeof window === 'undefined') {
        return;
    }

    initialized = true;

    router.on('httpException', (event) => {
        // Prevent Inertia from popping up its default error modal (dialog#inertia-error-dialog)
        event.preventDefault();
        document.getElementById('inertia-error-dialog')?.remove();

        const isOffline = typeof navigator !== 'undefined' && !navigator.onLine;
        toast.error(
            isOffline
                ? t('pwa.offline_page_unavailable')
                : t('pwa.network_error'),
        );

        return false;
    });

    router.on('networkError', (event) => {
        // Prevent unhandled promise rejection when offline or connection drops
        event.preventDefault();
        document.getElementById('inertia-error-dialog')?.remove();

        const isOffline = typeof navigator !== 'undefined' && !navigator.onLine;
        toast.error(
            isOffline
                ? t('pwa.offline_page_unavailable')
                : t('pwa.network_error'),
        );

        return false;
    });
}
