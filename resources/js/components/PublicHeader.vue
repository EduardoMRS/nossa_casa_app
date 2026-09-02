<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarDays,
    CircleAlert,
    Image,
    LibraryBig,
    Menu,
    Newspaper,
    Network as NetworkIcon,
    Radio,
    X,
    Users,
} from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import PublicChurchNetworkController from '@/actions/App/Http/Controllers/PublicChurchNetworkController';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { applyBranding } from '@/lib/branding';
import { useI18n } from '@/lib/i18n';
import { home, login } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { index as galleryIndex } from '@/routes/gallery';
import { index as libraryIndex } from '@/routes/library';
import { index as publicPostsIndex } from '@/routes/posts/public';

type PublicNavKey =
    | 'home'
    | 'network'
    | 'posts'
    | 'events'
    | 'gallery'
    | 'library'
    | 'classrooms';

const props = withDefaults(
    defineProps<{ active?: PublicNavKey; showLocale?: boolean }>(),
    {
        active: 'home',
        showLocale: false,
    },
);
const page = usePage();
const mobileOpen = ref(false);
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const user = computed(() => page.props.auth?.user);
const hasChurchContext = computed(() =>
    Boolean(
        (page.props.churchContext as { church?: { id: string } | null })
            ?.church,
    ),
);
const { t } = useI18n();
const branding = computed(
    () => (page.props.branding ?? {}) as Record<string, string>,
);
const activeLiveStream = computed(
    () =>
        page.props.activeLiveStream as {
            id: string;
            name: string;
            started_at: string | null;
        } | null,
);
const isForeignChurch = computed(() =>
    Boolean(
        (page.props.churchContext as { isForeignChurch?: boolean })
            ?.isForeignChurch,
    ),
);
const brandInitials = computed(() =>
    (branding.value.brand_name || t('portal.brand_name'))
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
    ...(hasChurchContext.value
        ? [
              {
                  key: 'network' as const,
                  label: t('nav.network'),
                  href: PublicChurchNetworkController.url(),
                  icon: NetworkIcon,
              },
          ]
        : []),
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
const desktopNavItems = computed(() =>
    navItems.value.filter((item) => item.key !== 'network'),
);

const navClass = (key: PublicNavKey): string =>
    props.active === key
        ? 'bg-indigo-950 text-white ring-1 ring-indigo-700'
        : 'text-indigo-100 hover:bg-indigo-800 hover:text-white';
</script>

<template>
    <header
        data-test="public-top-navigation"
        class="public-top-navigation sticky top-0 z-40 border-b border-indigo-950 bg-[#342f87] text-white shadow-sm"
        :style="{ backgroundColor: 'var(--church-primary, #342f87)' }"
    >
        <div
            class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-2 px-3 sm:h-16 sm:gap-4 sm:px-6 lg:px-8"
        >
            <Link :href="home()" class="group flex shrink-0 items-center gap-3">
                <span
                    class="grid size-9 place-items-center overflow-hidden rounded-xl border border-white/25 bg-white/10 text-base font-black transition group-hover:scale-105 sm:size-10 sm:text-lg"
                >
                    <img
                        v-if="branding.logo_url"
                        :src="branding.logo_url"
                        :alt="branding.brand_name || t('portal.brand_name')"
                        class="h-full w-full bg-white object-contain p-1"
                    />
                    <template v-else>{{ brandInitials }}</template>
                </span>
                <span class="hidden sm:block">
                    <strong class="block text-base leading-tight">{{
                        branding.brand_name || t('portal.brand_name')
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
                    v-for="item in desktopNavItems"
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
                <DropdownMenu v-if="isAuthenticated && user">
                    <DropdownMenuTrigger :as-child="true">
                        <Button
                            variant="ghost"
                            class="p-0 pr-0 pl-2 hidden h-10 gap-2 rounded-full border border-indigo-700 text-white hover:bg-indigo-950 hover:text-white md:flex"
                        >
                            <UserInfo
                                :user="user"
                                :first-name-only="true"
                                :avatar-on-right="true"
                            >
                                <template #before-avatar>
                                    <CircleAlert
                                        v-if="isForeignChurch"
                                        class="size-4 shrink-0 text-sky-300"
                                        :aria-label="
                                            t('membership.foreign_indicator')
                                        "
                                    />
                                </template>
                            </UserInfo>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56"
                        ><UserMenuContent :user="user"
                    /></DropdownMenuContent>
                </DropdownMenu>
                <Link
                    v-else
                    :href="login({ query: { redirect: page.url } })"
                    class="hidden rounded-lg bg-white px-3 py-2 text-xs font-bold text-indigo-950 shadow-sm sm:px-4 md:inline-flex"
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

        <Link
            v-if="activeLiveStream"
            :href="`/live-streams/${activeLiveStream.id}`"
            class="flex items-center justify-center gap-2 border-t border-white/15 bg-rose-600 px-3 py-2 text-xs font-black text-white transition hover:bg-rose-700 sm:gap-3 sm:px-4 sm:text-sm"
        >
            <span class="relative flex size-3">
                <span
                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-60"
                />
                <span
                    class="relative inline-flex size-3 rounded-full bg-white"
                />
            </span>
            <Radio class="size-4" />
            <span class="truncate">{{
                t('nav.live_now', { name: activeLiveStream.name })
            }}</span>
            <span class="hidden text-xs font-bold underline sm:inline">{{
                t('nav.watch')
            }}</span>
        </Link>

        <nav
            v-if="mobileOpen"
            class="border-t border-indigo-800 bg-indigo-950 p-3 md:hidden"
        >
            <div class="grid grid-cols-2 gap-2">
                <Link
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    :class="navClass(item.key)"
                    class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold"
                    @click="mobileOpen = false"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                </Link>
            </div>

            <div
                class="mt-3 border-t border-white/15 pt-3"
                data-test="mobile-account-section"
            >
                <DropdownMenu v-if="isAuthenticated && user">
                    <DropdownMenuTrigger :as-child="true">
                        <Button
                            variant="ghost"
                            class="h-auto w-full justify-start gap-3 rounded-lg border border-indigo-800 px-3 py-2.5 text-white hover:bg-indigo-900 hover:text-white"
                        >
                            <UserInfo
                                :user="user"
                                :show-email="true"
                                :first-name-only="true"
                                :avatar-on-right="true"
                            >
                                <template #before-avatar>
                                    <CircleAlert
                                        v-if="isForeignChurch"
                                        class="size-4 shrink-0 text-sky-300"
                                        :aria-label="
                                            t('membership.foreign_indicator')
                                        "
                                    />
                                </template>
                            </UserInfo>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        class="w-[calc(100vw-1.5rem)] max-w-sm"
                    >
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
                <Link
                    v-else
                    :href="login({ query: { redirect: page.url } })"
                    class="flex w-full items-center justify-center rounded-lg border border-indigo-800 px-3 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-900"
                    @click="mobileOpen = false"
                >
                    {{ t('nav.login') }}
                </Link>
            </div>
        </nav>
    </header>
</template>
