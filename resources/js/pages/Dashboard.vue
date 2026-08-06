<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';

type ModuleItem = {
    title_key: string;
    description_key: string;
    href: string;
};

const props = defineProps<{
    role: string;
    kpis: {
        events: number;
        gallery: number;
        posts: number;
    };
    modules: ModuleItem[];
}>();

const { t } = useI18n();
const page = usePage<{
    branding?: {
        brand_name?: string;
        tagline?: string;
        banner_title?: string;
        banner_subtitle?: string;
        primary_color?: string;
        secondary_color?: string;
        accent_color?: string;
        font_family?: string;
        contact_email?: string;
    };
}>();

const branding = computed(() => page.props.branding ?? {});

const paletteStyle = computed(() => ({
    '--brand-primary': branding.value.primary_color ?? '#2f6e79',
    '--brand-secondary': branding.value.secondary_color ?? '#5f7d95',
    '--brand-accent': branding.value.accent_color ?? '#c88b4a',
    '--brand-font': branding.value.font_family ?? 'Manrope, ui-sans-serif',
}));

const roleLabel = computed(() => {
    const key = `dashboard.roles.${props.role}`;
    const translated = t(key);

    return translated === key ? props.role : translated;
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-hidden bg-[#f4f7fb] p-4 text-slate-800 dark:bg-slate-900 dark:text-slate-100 md:p-6" :style="paletteStyle">
        <section
            class="rounded-3xl border border-slate-200/70 bg-gradient-to-r from-[color:var(--brand-primary)]/90 via-[color:var(--brand-secondary)]/90 to-[color:var(--brand-primary)]/85 p-6 text-white shadow-sm dark:border-slate-700/60 dark:from-[color:var(--brand-primary)]/55 dark:via-[color:var(--brand-secondary)]/50 dark:to-[color:var(--brand-primary)]/50 md:p-8"
        >
            <p class="mb-1 text-xs uppercase tracking-[0.2em] text-slate-100/80">{{ t('dashboard.workspace') }}</p>
            <h1 class="text-3xl font-black md:text-4xl" :style="{ fontFamily: 'var(--brand-font)' }">
                {{ branding.banner_title || t('dashboard.title') }}
            </h1>
            <p class="mt-2 text-sm text-slate-100/90 md:text-base">
                {{ branding.banner_subtitle || t('dashboard.profile_context', { role: roleLabel }) }}
            </p>
            <p v-if="branding.contact_email" class="mt-3 text-xs font-semibold text-slate-100/80">{{ branding.contact_email }}</p>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-2xl border border-slate-200/70 bg-white/90 p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500 dark:text-slate-300">{{ t('dashboard.kpis.events') }}</p>
                <p class="mt-2 text-3xl font-black text-slate-900 dark:text-slate-100" :style="{ fontFamily: 'var(--brand-font)' }">{{ kpis.events }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200/70 bg-white/90 p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500 dark:text-slate-300">{{ t('dashboard.kpis.gallery') }}</p>
                <p class="mt-2 text-3xl font-black text-slate-900 dark:text-slate-100" :style="{ fontFamily: 'var(--brand-font)' }">{{ kpis.gallery }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200/70 bg-white/90 p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500 dark:text-slate-300">{{ t('dashboard.kpis.posts') }}</p>
                <p class="mt-2 text-3xl font-black text-slate-900 dark:text-slate-100" :style="{ fontFamily: 'var(--brand-font)' }">{{ kpis.posts }}</p>
            </article>
        </section>

        <section>
            <div class="mb-3">
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500 dark:text-slate-300">{{ t('dashboard.modules') }}</p>
                <h2 class="text-2xl font-black text-slate-900 dark:text-slate-100" :style="{ fontFamily: 'var(--brand-font)' }">{{ t('dashboard.recommended_actions') }}</h2>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="module in modules"
                    :key="module.title_key"
                    class="rounded-2xl border border-slate-200/70 bg-white/90 p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800/70"
                >
                    <h3 class="text-lg font-black text-slate-900 dark:text-slate-100" :style="{ fontFamily: 'var(--brand-font)' }">{{ t(module.title_key) }}</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ t(module.description_key) }}</p>
                    <Link
                        :href="module.href"
                        class="mt-4 inline-flex rounded-full px-4 py-2 text-xs font-bold text-white"
                        :style="{ backgroundColor: 'var(--brand-primary)' }"
                    >
                        {{ t('dashboard.open_module') }}
                    </Link>
                </article>
            </div>
        </section>
    </div>
</template>
