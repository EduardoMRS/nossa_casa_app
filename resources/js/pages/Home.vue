<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicHeader from '@/components/PublicHeader.vue';
import { index as eventsIndex, show as eventsShow } from '@/routes/events';
import { index as galleryIndex } from '@/routes/gallery';
import { useI18n } from '@/lib/i18n';

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

    <div class="min-h-screen bg-[#f5f6f2] text-[#111b2d]">
        <PublicHeader active="home" />

        <main class="mx-auto max-w-7xl space-y-8 px-5 py-8 md:px-8 md:py-10">
            <section class="grid gap-5 rounded-3xl bg-gradient-to-r from-[#0b3d44] via-[#125a63] to-[#0f7a69] p-6 text-white md:grid-cols-[1.2fr_0.8fr] md:p-10">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.22em] text-[#a9f4e3]">{{ t('home.hero.kicker') }}</p>
                    <h1 class="mb-3 text-3xl font-black [font-family:Manrope,ui-sans-serif] md:text-5xl">
                        {{ t('home.hero.title') }}
                    </h1>
                    <p class="max-w-2xl text-sm text-[#d8f6ef] md:text-base">
                        {{ t('home.hero.description') }}
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            :href="eventsIndex()"
                            class="rounded-full bg-white px-5 py-2.5 text-sm font-extrabold text-[#0b3d44]"
                        >
                            {{ t('home.hero.cta_events') }}
                        </Link>
                        <Link
                            :href="galleryIndex()"
                            class="rounded-full border border-white/40 px-5 py-2.5 text-sm font-extrabold text-white"
                        >
                            {{ t('home.hero.cta_gallery') }}
                        </Link>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 md:grid-cols-1">
                    <article
                        v-for="item in statsRows"
                        :key="item.label"
                        class="rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur"
                    >
                        <p class="text-xs uppercase tracking-[0.15em] text-[#bceee2]">{{ item.label }}</p>
                        <p class="mt-2 text-3xl font-black [font-family:Manrope,ui-sans-serif]">{{ item.value }}</p>
                    </article>
                </div>
            </section>

            <section class="grid gap-8 lg:grid-cols-[1.4fr_1fr]">
                <div>
                    <div class="mb-4 flex items-end justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-[#4f6a82]">{{ t('home.featured.kicker') }}</p>
                            <h2 class="text-2xl font-black [font-family:Manrope,ui-sans-serif]">{{ t('home.featured.title') }}</h2>
                        </div>
                        <Link :href="eventsIndex()" class="text-sm font-semibold text-[#0b5666]">{{ t('home.featured.view_all') }}</Link>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="event in props.featuredEvents"
                            :key="event.id"
                            class="overflow-hidden rounded-2xl border border-[#d4dee7] bg-white shadow-sm"
                        >
                            <div class="relative h-36 overflow-hidden bg-[#d6e3ef]">
                                <img
                                    v-if="event.cover_path"
                                    :src="event.cover_path"
                                    :alt="event.title"
                                    class="h-full w-full object-cover"
                                >
                                <div v-else class="absolute inset-0 bg-gradient-to-br from-[#0b3d44] to-[#145362]" />
                            </div>
                            <div class="space-y-2 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#58718a]">
                                    {{ event.church?.name ?? t('events.shared.community') }}
                                </p>
                                <h3 class="line-clamp-2 text-lg font-black [font-family:Manrope,ui-sans-serif]">{{ event.title }}</h3>
                                <p class="line-clamp-2 text-sm text-[#53687d]">{{ event.excerpt }}</p>
                                <p class="text-xs text-[#6b8096]">{{ formatDate(event.start_time) }}</p>
                                <Link
                                    :href="eventsShow({ event: event.slug })"
                                    class="inline-flex rounded-full bg-[#103f60] px-4 py-1.5 text-xs font-bold text-white"
                                >
                                    {{ t('home.featured.view_detail') }}
                                </Link>
                            </div>
                        </article>
                    </div>
                </div>

                <div>
                    <div class="mb-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#4f6a82]">{{ t('home.latest_posts.kicker') }}</p>
                        <h2 class="text-2xl font-black [font-family:Manrope,ui-sans-serif]">{{ t('home.latest_posts.title') }}</h2>
                    </div>

                    <div class="space-y-3">
                        <article
                            v-for="post in props.latestPosts"
                            :key="post.id"
                            class="rounded-2xl border border-[#d4dee7] bg-white p-4 shadow-sm"
                        >
                            <h3 class="text-base font-black [font-family:Manrope,ui-sans-serif]">{{ post.title }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-[#53687d]">{{ post.excerpt }}</p>
                            <p class="mt-2 text-xs text-[#6b8096]">{{ formatDate(post.published_at) }}</p>
                        </article>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
