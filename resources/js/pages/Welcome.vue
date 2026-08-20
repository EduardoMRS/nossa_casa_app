<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    Church,
    Images,
    Newspaper,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import { usePublicTemplate } from '@/composables/usePublicTemplate';
import { useI18n } from '@/lib/i18n';
import { dashboard, login, register } from '@/routes';

const { t } = useI18n();
const page = usePage();
const publicTemplate = usePublicTemplate('home');
const branding = computed(
    () => page.props.branding as Record<string, string | undefined>,
);
let restoreDarkMode = false;

onMounted(() => {
    restoreDarkMode = document.documentElement.classList.contains('dark');
    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';
});

onBeforeUnmount(() => {
    if (restoreDarkMode) {
        document.documentElement.classList.add('dark');
    }

    document.documentElement.style.removeProperty('color-scheme');
});

const featureCards = computed(() => [
    { icon: Newspaper, label: t('nav.posts') },
    { icon: CalendarDays, label: t('nav.events') },
    { icon: Images, label: t('nav.gallery') },
]);
</script>

<template>
    <Head :title="t('welcome.meta_title')" />
    <div
        class="public-template public-welcome-light min-h-screen bg-white text-slate-950"
        :class="`public-template--${publicTemplate}`"
        data-public-template="home"
        :style="{
            fontFamily: branding.font_family || 'Manrope, ui-sans-serif',
            backgroundColor: branding.surface_color || '#f8fafc',
            '--church-primary': branding.primary_color || '#342f87',
        }"
    >
        <header
            class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8"
        >
            <div class="flex items-center gap-3">
                <img
                    v-if="branding.logo_url"
                    :src="branding.logo_url"
                    :alt="branding.brand_name"
                    class="size-11 rounded-xl object-cover"
                />
                <span
                    v-else
                    class="grid size-11 place-items-center rounded-xl text-white"
                    :style="{ backgroundColor: 'var(--church-primary)' }"
                >
                    <Church class="size-5" />
                </span>
                <div>
                    <strong class="block text-base leading-tight">
                        {{ branding.brand_name || $page.props.name }}
                    </strong>
                    <small class="text-xs text-slate-500">
                        {{ branding.tagline || t('nav.faith_community') }}
                    </small>
                </div>
            </div>

            <nav class="flex items-center gap-2">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="rounded-xl px-4 py-2.5 text-sm font-bold text-white"
                    :style="{ backgroundColor: 'var(--church-primary)' }"
                >
                    {{ t('welcome.dashboard') }}
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="rounded-xl px-4 py-2.5 text-sm font-bold text-slate-700"
                    >
                        {{ t('welcome.login') }}
                    </Link>
                    <Link
                        :href="register()"
                        class="rounded-xl px-4 py-2.5 text-sm font-bold text-white"
                        :style="{ backgroundColor: 'var(--church-primary)' }"
                    >
                        {{ t('welcome.register') }}
                    </Link>
                </template>
            </nav>
        </header>

        <main
            class="mx-auto grid min-h-[calc(100vh-5rem)] max-w-7xl items-center gap-12 px-4 py-12 sm:px-6 lg:grid-cols-[1.15fr_0.85fr] lg:px-8"
        >
            <section>
                <p
                    class="text-xs font-black tracking-[0.22em] uppercase"
                    :style="{ color: 'var(--church-primary)' }"
                >
                    {{ branding.tagline || t('nav.faith_community') }}
                </p>
                <h1
                    class="mt-4 max-w-3xl text-4xl leading-tight font-black sm:text-6xl"
                >
                    {{
                        branding.banner_title ||
                        branding.brand_name ||
                        $page.props.name
                    }}
                </h1>
                <p
                    class="mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg"
                >
                    {{ branding.banner_subtitle || t('home.hero.description') }}
                </p>
                <Link
                    :href="$page.props.auth.user ? dashboard() : login()"
                    class="mt-8 inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-black text-white shadow-lg"
                    :style="{ backgroundColor: 'var(--church-primary)' }"
                >
                    {{
                        $page.props.auth.user
                            ? t('welcome.dashboard')
                            : t('welcome.login')
                    }}
                    <ArrowRight class="size-4" />
                </Link>
            </section>

            <section
                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl sm:p-7"
            >
                <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                    <article
                        v-for="feature in featureCards"
                        :key="feature.label"
                        class="flex items-center gap-4 rounded-2xl border border-slate-100 bg-slate-50 p-5"
                    >
                        <span
                            class="grid size-11 place-items-center rounded-xl bg-white shadow-sm"
                            :style="{ color: 'var(--church-primary)' }"
                        >
                            <component :is="feature.icon" class="size-5" />
                        </span>
                        <strong class="text-sm">{{ feature.label }}</strong>
                    </article>
                </div>
            </section>
        </main>
        <PublicFooter show-locale />
    </div>
</template>
