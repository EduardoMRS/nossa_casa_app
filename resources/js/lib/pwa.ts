import axios from 'axios';
import { computed, ref } from 'vue';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const installPrompt = ref<BeforeInstallPromptEvent | null>(null);
const installed = ref(false);
const notificationPermission = ref<NotificationPermission>(
    typeof Notification === 'undefined' ? 'default' : Notification.permission,
);

export const initializePwa = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt.value = event as BeforeInstallPromptEvent;
    });
    window.addEventListener('appinstalled', () => {
        installed.value = true;
        installPrompt.value = null;
    });

    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' });
        });
    }
};

export const usePwa = () => {
    const canInstall = computed(
        () => installPrompt.value !== null && !installed.value,
    );

    const install = async (): Promise<boolean> => {
        if (!installPrompt.value) {
            return false;
        }

        await installPrompt.value.prompt();
        const choice = await installPrompt.value.userChoice;

        if (choice.outcome === 'accepted') {
            installPrompt.value = null;
        }

        return choice.outcome === 'accepted';
    };

    const subscribeToPush = async (publicKey: string): Promise<boolean> => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return false;
        }

        const permission = await Notification.requestPermission();
        notificationPermission.value = permission;

        if (permission !== 'granted') {
            return false;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription =
            (await registration.pushManager.getSubscription()) ??
            (await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToArrayBuffer(publicKey),
            }));

        await axios.post('/api/push-subscriptions', {
            ...subscription.toJSON(),
            content_encoding: 'aes128gcm',
        });

        return true;
    };

    return {
        canInstall,
        install,
        notificationPermission,
        subscribeToPush,
    };
};

const urlBase64ToArrayBuffer = (value: string): ArrayBuffer => {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);

    return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)))
        .buffer;
};
