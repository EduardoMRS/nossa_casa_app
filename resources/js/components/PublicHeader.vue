<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { useI18n } from '@/lib/i18n';
import { dashboard, login } from '@/routes';
import { home } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { index as galleryIndex } from '@/routes/gallery';

type PublicNavKey = 'home' | 'events' | 'gallery';

const props = withDefaults(
    defineProps<{
        active?: PublicNavKey;
    }>(),
    {
        active: 'home',
    },
);

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const { t } = useI18n();

const navClass = (key: PublicNavKey): string => {
    return props.active === key
        ? 'rounded-full bg-[#0b3d44] px-4 py-2 text-white'
        : 'rounded-full border border-[#c5d3df] px-4 py-2 text-[#234] hover:border-[#0b3d44] hover:text-[#0b3d44]';
};
</script>

<template>
    <header class="border-b border-[#dbe2e8] bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-5 py-4 md:px-8">
            <Link :href="home()" class="text-lg font-black tracking-tight [font-family:Manrope,ui-sans-serif]">Nossa Casa</Link>

            <nav class="flex items-center gap-2 text-sm font-medium [font-family:Manrope,ui-sans-serif]">
                <Link :href="home()" :class="navClass('home')">{{ t('nav.home') }}</Link>
                <Link :href="eventsIndex()" :class="navClass('events')">{{ t('nav.events') }}</Link>
                <Link :href="galleryIndex()" :class="navClass('gallery')">{{ t('nav.gallery') }}</Link>
            </nav>

            <div class="flex items-center gap-2">
                <LocaleSwitcher />
                <Link
                    v-if="isAuthenticated"
                    :href="dashboard()"
                    class="rounded-full border border-[#c5d3df] bg-white px-4 py-2 text-sm font-semibold text-[#1b334a]"
                >
                    {{ t('nav.dashboard') }}
                </Link>
                <Link
                    v-else
                    :href="login()"
                    class="rounded-full bg-[#12354f] px-4 py-2 text-sm font-semibold text-white"
                >
                    {{ t('nav.login') }}
                </Link>
            </div>
        </div>
    </header>
</template>
