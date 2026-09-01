<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, CalendarDays, Newspaper, UserRound } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from '@/lib/i18n';
import { login } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { bible as bibleRoute } from '@/routes/library';
import { index as publicPostsIndex } from '@/routes/posts/public';
import { edit as profileEdit } from '@/routes/profile';

type StandaloneNavigator = Navigator & {
    standalone?: boolean;
};

type BottomNavigationKey = 'bible' | 'news' | 'events' | 'profile';

const publicShellComponents = new Set([
    'Home',
    'Welcome',
    'Portal/Index',
    'Portal/CommunityShow',
    'ErrorPage',
    'Events/Index',
    'Events/Show',
    'Events/Register',
    'Events/PrivateArea',
    'Posts/PublicIndex',
    'Posts/PublicShow',
    'Admin/ClassroomLabels',
    'Gallery/Index',
    'Library/Index',
    'Library/Bible',
    'settings/Workspace',
    'LiveStreams/Show',
]);

const page = usePage();
const { t } = useI18n();
const standalone = ref(false);
const mediaQueries: MediaQueryList[] = [];

const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const currentPath = computed(() => page.url.split(/[?#]/, 1)[0]);
const isPublicShell = computed(() => publicShellComponents.has(page.component));
const shouldShow = computed(
    () =>
        standalone.value &&
        page.component !== 'Library/Bible' &&
        !page.component.startsWith('auth/') &&
        !currentPath.value.startsWith('/dashboard') &&
        !currentPath.value.startsWith('/classrooms'),
);

const activeKey = computed<BottomNavigationKey | null>(() => {
    if (
        currentPath.value.startsWith('/library') ||
        currentPath.value.startsWith('/biblioteca')
    ) {
        return 'bible';
    }

    if (currentPath.value.startsWith('/posts')) {
        return 'news';
    }

    if (currentPath.value.startsWith('/events')) {
        return 'events';
    }

    if (currentPath.value.startsWith('/settings/profile')) {
        return 'profile';
    }

    return null;
});

const profileHref = computed(() =>
    isAuthenticated.value
        ? profileEdit()
        : login({ query: { redirect: page.url } }),
);

const items = computed(() => [
    {
        key: 'bible' as const,
        label: t('nav.bible'),
        href: bibleRoute(),
        icon: BookOpen,
    },
    {
        key: 'news' as const,
        label: t('nav.news'),
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
        key: 'profile' as const,
        label: t('nav.profile'),
        href: profileHref.value,
        icon: UserRound,
    },
]);

const updateStandalone = (): void => {
    standalone.value =
        (navigator as StandaloneNavigator).standalone === true ||
        mediaQueries.some((query) => query.matches);
};

const addMediaListener = (query: MediaQueryList): void => {
    if ('addEventListener' in query) {
        query.addEventListener('change', updateStandalone);

        return;
    }

    query.addListener(updateStandalone);
};

const removeMediaListener = (query: MediaQueryList): void => {
    if ('removeEventListener' in query) {
        query.removeEventListener('change', updateStandalone);

        return;
    }

    query.removeListener(updateStandalone);
};

const syncDocumentClasses = (): void => {
    document.documentElement.classList.toggle(
        'pwa-standalone',
        standalone.value,
    );
    document.documentElement.classList.toggle(
        'pwa-public-shell',
        standalone.value && isPublicShell.value,
    );
    document.documentElement.classList.toggle(
        'pwa-navigation-visible',
        shouldShow.value,
    );
};

watch([standalone, isPublicShell, shouldShow], syncDocumentClasses);

onMounted(() => {
    mediaQueries.push(
        window.matchMedia('(display-mode: standalone)'),
        window.matchMedia('(display-mode: fullscreen)'),
    );
    mediaQueries.forEach(addMediaListener);
    updateStandalone();
    syncDocumentClasses();
});

onBeforeUnmount(() => {
    mediaQueries.forEach(removeMediaListener);
    document.documentElement.classList.remove(
        'pwa-standalone',
        'pwa-public-shell',
        'pwa-navigation-visible',
    );
});
</script>

<template>
    <nav
        v-if="shouldShow"
        data-test="pwa-bottom-navigation"
        class="fixed inset-x-0 bottom-0 z-[70] border-t border-slate-200 bg-white/95 shadow-[0_-8px_30px_rgba(15,23,42,0.10)] backdrop-blur-xl md:hidden"
        :aria-label="t('nav.pwa_navigation')"
        :style="{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }"
    >
        <div class="mx-auto grid h-16 max-w-md grid-cols-4 px-2">
            <Link
                v-for="item in items"
                :key="item.key"
                :href="item.href"
                class="relative flex min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[10px] font-bold transition active:scale-95"
                :class="
                    activeKey === item.key
                        ? 'bg-indigo-50 text-indigo-700'
                        : 'text-slate-500'
                "
                :style="
                    activeKey === item.key
                        ? { color: 'var(--church-primary, #342f87)' }
                        : undefined
                "
                :aria-current="activeKey === item.key ? 'page' : undefined"
            >
                <component :is="item.icon" class="size-5" />
                <span class="max-w-full truncate">{{ item.label }}</span>
                <span
                    v-if="activeKey === item.key"
                    class="absolute top-0 h-0.5 w-8 rounded-full"
                    :style="{
                        backgroundColor: 'var(--church-primary, #342f87)',
                    }"
                />
            </Link>
        </div>
    </nav>
</template>
