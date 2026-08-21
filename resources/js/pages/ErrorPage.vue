<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    House,
    MapPinOff,
    ServerCrash,
    ShieldAlert,
    TriangleAlert,
} from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import { applyBranding } from '@/lib/branding';
import { useI18n } from '@/lib/i18n';

const props = defineProps<{ status: number }>();
const page = usePage();
const { t } = useI18n();
const branding = computed(
    () => (page.props.branding ?? {}) as Record<string, string>,
);
const churchContext = computed(
    () =>
        page.props.churchContext as
            { church?: { name: string } | null } | undefined,
);
const portalUrl = computed(
    () => (page.props.portalUrl as string | undefined) ?? '/',
);
const homeUrl = computed(() =>
    churchContext.value?.church ? '/' : portalUrl.value,
);
const knownStatuses = new Set([401, 403, 404, 500, 503, 505]);
const translationStatus = computed(() =>
    knownStatuses.has(props.status) ? props.status : 'generic',
);
const title = computed(() =>
    t(`errors.status.${translationStatus.value}.title`, {
        status: props.status,
    }),
);
const description = computed(() =>
    t(`errors.status.${translationStatus.value}.description`, {
        status: props.status,
    }),
);
const icon = computed(() => {
    if (props.status === 401 || props.status === 403) {
        return ShieldAlert;
    }

    if (props.status === 404) {
        return MapPinOff;
    }

    if (props.status >= 500) {
        return ServerCrash;
    }

    return TriangleAlert;
});

const goBack = (): void => {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();

        return;
    }

    router.visit(homeUrl.value);
};

watchEffect(() => {
    if (typeof document !== 'undefined') {
        applyBranding(branding.value);
    }
});
</script>

<template>
    <Head :title="title" />
    <main
        class="relative grid min-h-screen place-items-center overflow-hidden bg-slate-50 px-5 py-12 text-slate-950"
        :style="{
            backgroundColor: 'var(--church-surface, #f8fafc)',
            fontFamily: 'var(--church-font, Manrope, ui-sans-serif)',
        }"
    >
        <div
            class="pointer-events-none absolute -top-40 -right-32 size-96 rounded-full opacity-15 blur-3xl"
            :style="{ backgroundColor: 'var(--church-primary, #342f87)' }"
        />
        <div
            class="pointer-events-none absolute -bottom-44 -left-32 size-96 rounded-full opacity-15 blur-3xl"
            :style="{ backgroundColor: 'var(--church-accent, #c88b4a)' }"
        />

        <section class="relative w-full max-w-2xl text-center">
            <div
                class="mx-auto grid size-20 place-items-center rounded-3xl text-white shadow-xl"
                :style="{ backgroundColor: 'var(--church-primary, #342f87)' }"
            >
                <component :is="icon" class="size-9" />
            </div>
            <p
                class="mt-8 font-mono text-sm font-black tracking-[0.3em] uppercase"
                :style="{ color: 'var(--church-primary, #342f87)' }"
            >
                {{ status }}
            </p>
            <h1 class="mt-3 text-4xl font-black tracking-tight sm:text-6xl">
                {{ title }}
            </h1>
            <p
                class="mx-auto mt-5 max-w-xl text-base leading-7 text-slate-600 sm:text-lg"
            >
                {{ description }}
            </p>

            <div class="mt-9 flex flex-wrap justify-center gap-3">
                <Link
                    :href="homeUrl"
                    class="inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-black text-white shadow-sm"
                    :style="{
                        backgroundColor: 'var(--church-primary, #342f87)',
                    }"
                >
                    <House class="size-4" />
                    {{ t('errors.actions.home') }}
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-black text-slate-700"
                    @click="goBack"
                >
                    <ArrowLeft class="size-4" />
                    {{ t('errors.actions.back') }}
                </button>
            </div>

            <p v-if="branding.brand_name" class="mt-10 text-xs text-slate-400">
                {{ branding.brand_name }}
            </p>
        </section>
    </main>
</template>
