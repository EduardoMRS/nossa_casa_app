<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';
import { index as eventsIndex, show as eventsShow } from '@/routes/events';
import { index as galleryIndex } from '@/routes/gallery';

type EventCard = {
    id: string;
    title: string;
    slug: string;
    excerpt: string;
    cover_path: string | null;
    start_time: string;
    church?: {
        id: string;
        name: string;
        slug: string;
    } | null;
};

type PostCard = {
    id: string;
    title: string;
    slug: string;
    excerpt: string;
    published_at: string | null;
};

const props = defineProps<{
    stats: {
        events: number;
        gallery: number;
        posts: number;
    };
    featuredEvents: EventCard[];
    latestPosts: PostCard[];
}>();

const { locale, t } = useI18n();
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

const formatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }),
);

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return formatter.value.format(new Date(value));
};

const statsRows = computed(() => [
    { label: t('home.stats.events'), value: props.stats.events },
    { label: t('home.stats.gallery'), value: props.stats.gallery },
    { label: t('home.stats.posts'), value: props.stats.posts },
]);
</script>

<template>
    <Head :title="t('home.meta_title')" />

    <div
        class="public-welcome-light flex min-h-screen flex-col bg-[#f8fafc] text-slate-950"
        :style="{
            backgroundColor: 'var(--church-surface, #f8fafc)',
            fontFamily: 'var(--church-font, Manrope, ui-sans-serif)',
        }"
    >
        <PublicHeader active="home" />

        <main
            class="mx-auto w-full max-w-7xl flex-1 space-y-7 px-4 py-7 sm:px-6 lg:px-8 lg:py-9"
        >
            <section
                class="grid gap-5 rounded-2xl border p-6 text-white shadow-md md:grid-cols-[1.35fr_0.65fr] md:p-8"
                :style="{
                    borderColor:
                        'color-mix(in srgb, var(--church-primary) 75%, black)',
                    background:
                        'linear-gradient(135deg, color-mix(in srgb, var(--church-primary) 72%, black), var(--church-primary), color-mix(in srgb, var(--church-primary) 72%, white))',
                }"
            >
                <div>
                    <p
                        class="mb-2 font-mono text-[10px] font-bold tracking-[0.2em] text-amber-300 uppercase"
                    >
                        {{ t('home.hero.kicker') }}
                    </p>
                    <h1
                        class="mb-3 [font-family:Manrope,ui-sans-serif] text-3xl font-black md:text-5xl"
                    >
                        {{ t('home.hero.title') }}
                    </h1>
                    <p class="max-w-2xl text-sm leading-6 text-indigo-100">
                        {{ t('home.hero.description') }}
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            :href="eventsIndex()"
                            class="rounded-lg bg-white px-4 py-2.5 text-xs font-extrabold text-indigo-950 shadow-sm"
                        >
                            {{ t('home.hero.cta_events') }}
                        </Link>
                        <Link
                            :href="galleryIndex()"
                            class="rounded-lg border border-white/35 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-white/10"
                        >
                            {{ t('home.hero.cta_gallery') }}
                        </Link>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 md:grid-cols-1">
                    <article
                        v-for="item in statsRows"
                        :key="item.label"
                        class="rounded-xl border border-white/15 bg-indigo-950/35 p-4 backdrop-blur"
                    >
                        <p
                            class="text-[10px] font-bold tracking-[0.15em] text-indigo-200 uppercase"
                        >
                            {{ item.label }}
                        </p>
                        <p
                            class="mt-2 [font-family:Manrope,ui-sans-serif] text-3xl font-black"
                        >
                            {{ item.value }}
                        </p>
                    </article>
                </div>
            </section>

            <section class="grid gap-8 lg:grid-cols-[1.4fr_1fr]">
                <div>
                    <div class="mb-4 flex items-end justify-between">
                        <div>
                            <p
                                class="font-mono text-[10px] font-bold tracking-[0.16em] text-indigo-500 uppercase"
                            >
                                {{ t('home.featured.kicker') }}
                            </p>
                            <h2
                                class="[font-family:Manrope,ui-sans-serif] text-2xl font-black"
                            >
                                {{ t('home.featured.title') }}
                            </h2>
                        </div>
                        <Link
                            :href="eventsIndex()"
                            class="text-xs font-bold text-indigo-600"
                            >{{ t('home.featured.view_all') }}</Link
                        >
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="event in props.featuredEvents"
                            :key="event.id"
                            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <div
                                class="relative h-36 overflow-hidden bg-[#d6e3ef]"
                            >
                                <img
                                    v-if="event.cover_path"
                                    :src="event.cover_path"
                                    :alt="event.title"
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="absolute inset-0 bg-gradient-to-br from-indigo-950 to-indigo-700"
                                />
                            </div>
                            <div class="space-y-2 p-4">
                                <p
                                    class="text-[10px] font-semibold tracking-[0.12em] text-indigo-500 uppercase"
                                >
                                    {{
                                        event.church?.name ??
                                        t('events.shared.community')
                                    }}
                                </p>
                                <h3
                                    class="line-clamp-2 [font-family:Manrope,ui-sans-serif] text-lg font-black"
                                >
                                    {{ event.title }}
                                </h3>
                                <p class="line-clamp-2 text-sm text-[#53687d]">
                                    {{ event.excerpt }}
                                </p>
                                <p class="text-xs text-[#6b8096]">
                                    {{ formatDate(event.start_time) }}
                                </p>
                                <Link
                                    :href="eventsShow({ event: event.slug })"
                                    class="inline-flex rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white"
                                >
                                    {{ t('home.featured.view_detail') }}
                                </Link>
                            </div>
                        </article>
                    </div>
                </div>

                <div>
                    <div class="mb-4">
                        <p
                            class="font-mono text-[10px] font-bold tracking-[0.16em] text-indigo-500 uppercase"
                        >
                            {{ t('home.latest_posts.kicker') }}
                        </p>
                        <h2
                            class="[font-family:Manrope,ui-sans-serif] text-2xl font-black"
                        >
                            {{ t('home.latest_posts.title') }}
                        </h2>
                    </div>

                    <div class="space-y-3">
                        <Link
                            v-for="post in props.latestPosts"
                            :key="post.id"
                            :href="`/posts/${post.slug}`"
                            class="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md"
                        >
                            <h3
                                class="[font-family:Manrope,ui-sans-serif] text-base font-black"
                            >
                                {{ post.title }}
                            </h3>
                            <p class="mt-1 line-clamp-2 text-sm text-[#53687d]">
                                {{ post.excerpt }}
                            </p>
                            <p class="mt-2 text-xs text-[#6b8096]">
                                {{ formatDate(post.published_at) }}
                            </p>
                        </Link>
                    </div>
                </div>
            </section>
        </main>
        <PublicFooter />
    </div>
</template>
