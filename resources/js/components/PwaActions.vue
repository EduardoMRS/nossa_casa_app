<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { BellRing, Download } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from '@/lib/i18n';
import { usePwa } from '@/lib/pwa';

const page = usePage();
const { t } = useI18n();
const { canInstall, install, notificationPermission, subscribeToPush } =
    usePwa();
const processing = ref(false);
const pushEnabled = ref(notificationPermission.value === 'granted');
const showIosGuide = ref(false);
const publicKey = computed(
    () => (page.props.pwa as { publicKey?: string | null })?.publicKey ?? '',
);
const canEnablePush = computed(
    () =>
        Boolean(page.props.auth?.user) &&
        Boolean(publicKey.value) &&
        !pushEnabled.value,
);
const isIos = computed(() => {
    if (typeof navigator === 'undefined' || typeof window === 'undefined') {
        return false;
    }

    const userAgent = navigator.userAgent.toLowerCase();
    const iosDevice = /iphone|ipad|ipod/.test(userAgent);
    const standalone = window.matchMedia('(display-mode: standalone)').matches;

    return iosDevice && !standalone;
});

const installApp = async (): Promise<void> => {
    if (isIos.value && !canInstall.value) {
        showIosGuide.value = true;

        return;
    }

    await install();
};

const enablePush = async (): Promise<void> => {
    processing.value = true;

    try {
        pushEnabled.value = await subscribeToPush(publicKey.value);
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <div
        v-if="canInstall || isIos || canEnablePush"
        class="flex flex-wrap items-center justify-center gap-2"
    >
        <button
            v-if="canInstall || isIos"
            type="button"
            class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-[10px] font-black tracking-normal text-slate-600 normal-case transition hover:border-indigo-300 hover:text-indigo-700"
            @click="installApp"
        >
            <Download class="size-3.5" /> {{ t('pwa.install') }}
        </button>
        <button
            v-if="canEnablePush"
            type="button"
            :disabled="processing"
            class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-[10px] font-black tracking-normal text-slate-600 normal-case transition hover:border-indigo-300 hover:text-indigo-700 disabled:opacity-60"
            @click="enablePush"
        >
            <BellRing class="size-3.5" /> {{ t('pwa.notifications') }}
        </button>
        <div
            v-if="showIosGuide"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-6 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            :aria-label="t('pwa.ios_title')"
        >
            <div
                class="w-full max-w-sm rounded-3xl bg-white p-6 text-left shadow-2xl"
            >
                <h2 class="text-lg font-black text-slate-900">
                    {{ t('pwa.ios_title') }}
                </h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    {{ t('pwa.ios_instructions') }}
                </p>
                <button
                    type="button"
                    class="mt-5 w-full rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700"
                    @click="showIosGuide = false"
                >
                    {{ t('a11y.close') }}
                </button>
            </div>
        </div>
    </div>
</template>
