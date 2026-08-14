<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarDays,
    Image,
    LayoutDashboard,
    LibraryBig,
    Menu,
    Newspaper,
    X,
} from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import ChurchMembershipPrompt from '@/components/ChurchMembershipPrompt.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import { applyBranding } from '@/lib/branding';
import { useI18n } from '@/lib/i18n';
import { dashboard, home, login } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { index as galleryIndex } from '@/routes/gallery';
import { index as libraryIndex } from '@/routes/library';
import { index as publicPostsIndex } from '@/routes/posts/public';

type PublicNavKey = 'home' | 'posts' | 'events' | 'gallery' | 'library';

const props = withDefaults(
    defineProps<{ active?: PublicNavKey; showLocale?: boolean }>(),
    {
        active: 'home',
        showLocale: true,
    },
);
const page = usePage();
const mobileOpen = ref(false);
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const user = computed(() => page.props.auth?.user);
const { t } = useI18n();
const branding = computed(
    () => (page.props.branding ?? {}) as Record<string, string>,
);
const canAccessDashboard = computed(
    () =>
        isAuthenticated.value &&
        Boolean(
            (
                page.props.permissions as
                    { accessDashboard?: boolean } | undefined
            )?.accessDashboard,
        ),
);
const brandInitials = computed(() =>
    (branding.value.brand_name || 'Nossa Casa')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join(''),
);

watchEffect(() => {
    if (typeof document === 'undefined') {
        return;
    }

    applyBranding(branding.value);
});

const navItems = computed(() => [
    {
        key: 'home' as const,
        label: t('nav.home'),
        href: home(),
        icon: BookOpen,
    },
    {
        key: 'posts' as const,
        label: t('nav.posts'),
        href: publicPostsIndex(),
        icon: Newspaper,
    },
    {
        key: 'events' as const,
        label: t('nav.events'),
        href: eventsIndex(),
        icon: CalendarDays,
    },
    {
        key: 'gallery' as const,
        label: t('nav.gallery'),
        href: galleryIndex(),
        icon: Image,
    },
    {
        key: 'library' as const,
        label: t('nav.library'),
        href: libraryIndex(),
        icon: LibraryBig,
    },
]);

const navClass = (key: PublicNavKey): string =>
    props.active === key
        ? 'bg-indigo-950 text-white ring-1 ring-indigo-700'
        : 'text-indigo-100 hover:bg-indigo-800 hover:text-white';
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-indigo-950 bg-[#342f87] text-white shadow-sm"
        :style="{ backgroundColor: 'var(--church-primary, #342f87)' }"
    >
        <div
            class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8"
        >
            <Link :href="home()" class="group flex shrink-0 items-center gap-3">
                <span
                    class="grid size-10 place-items-center overflow-hidden rounded-xl border border-white/25 bg-white/10 text-lg font-black transition group-hover:scale-105"
                >
                    <img
                        v-if="branding.logo_url"
                        :src="branding.logo_url"
                        :alt="branding.brand_name || 'Nossa Casa'"
                        class="h-full w-full bg-white object-contain p-1"
                    />
                    <template v-else>{{ brandInitials }}</template>
                </span>
                <span class="hidden sm:block">
                    <strong class="block text-base leading-tight">{{
                        branding.brand_name || 'Nossa Casa'
                    }}</strong>
                    <small
                        class="block font-mono text-[9px] font-bold tracking-[0.2em] text-indigo-200 uppercase"
                        >{{
                            branding.tagline || t('nav.faith_community')
                        }}</small
                    >
                </span>
            </Link>

            <nav class="hidden items-center gap-1 md:flex">
                <Link
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    :class="navClass(item.key)"
                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                </Link>
            </nav>

            <div class="flex items-center gap-2">
                <LocaleSwitcher v-if="showLocale" />
                <Link
                    v-if="canAccessDashboard"
                    :href="dashboard()"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 text-xs font-bold text-white transition hover:bg-white hover:text-indigo-950"
                >
                    <LayoutDashboard class="size-4" />
                    <span class="hidden lg:inline">{{
                        t('nav.dashboard')
                    }}</span>
                </Link>
                <DropdownMenu v-if="isAuthenticated && user">
                    <DropdownMenuTrigger :as-child="true">
                        <Button
                            variant="ghost"
                            class="h-10 gap-2 rounded-full border border-indigo-700 px-1.5 pr-3 text-white hover:bg-indigo-950 hover:text-white"
                        >
                            <span
                                class="hidden max-w-36 truncate text-xs font-semibold lg:block"
                                >{{ user.name }}</span
                            >
                            <Avatar class="size-8 ring-2 ring-amber-400/70">
                                <AvatarImage
                                    v-if="user.avatar"
                                    :src="user.avatar"
                                    :alt="user.name"
                                />
                                <AvatarFallback
                                    class="bg-indigo-950 text-white"
                                    >{{
                                        getInitials(user.name)
                                    }}</AvatarFallback
                                >
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56"
                        ><UserMenuContent :user="user"
                    /></DropdownMenuContent>
                </DropdownMenu>
                <Link
                    v-else
                    :href="login()"
                    class="rounded-lg bg-white px-4 py-2 text-xs font-bold text-indigo-950 shadow-sm"
                    >{{ t('nav.login') }}</Link
                >
                <button
                    class="rounded-lg p-2 text-indigo-100 hover:bg-indigo-800 md:hidden"
                    :aria-label="t('nav.menu')"
                    @click="mobileOpen = !mobileOpen"
                >
                    <X v-if="mobileOpen" class="size-5" /><Menu
                        v-else
                        class="size-5"
                    />
                </button>
            </div>
        </div>

        <nav
            v-if="mobileOpen"
            class="grid grid-cols-2 gap-2 border-t border-indigo-800 bg-indigo-950 p-3 md:hidden"
        >
            <Link
                v-for="item in navItems"
                :key="item.key"
                :href="item.href"
                :class="navClass(item.key)"
                class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold"
                @click="mobileOpen = false"
            >
                <component :is="item.icon" class="size-4" /> {{ item.label }}
            </Link>
        </nav>
        <ChurchMembershipPrompt />
    </header>
</template>
