<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarRange,
    Eye,
    Heart,
    MessageCircle,
    Newspaper,
    Search,
    SlidersHorizontal,
    X,
} from '@lucide/vue';
import { reactive } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { usePublicTemplate } from '@/composables/usePublicTemplate';
import { useI18n } from '@/lib/i18n';
import {
    index as publicPostsIndex,
    show as publicPostShow,
} from '@/routes/posts/public';

type PublicPost = {
    id: string;
    title: string;
    slug: string;
    excerpt: string;
    published_at: string | null;
    cover_url: string | null;
    comments_count: number;
    reactions_count: number;
    views_count: number;
    categories: string[];
    author?: { first_name: string; last_name: string } | null;
    church?: { name: string } | null;
};

type CategoryOption = { id: string; name: string; slug: string };
type PaginationLink = { url: string | null; label: string; active: boolean };
type Filters = {
    search: string;
    category: string;
    date_from: string;
    date_to: string;
    sort: 'latest' | 'popular';
};

const props = defineProps<{
    posts: {
        data: PublicPost[];
        links: PaginationLink[];
    };
    categories: CategoryOption[];
    filters: Filters;
    mostViewed: PublicPost[];
}>();

const { locale, t } = useI18n();
const publicTemplate = usePublicTemplate('posts_index');
const filterForm = reactive<Filters>({ ...props.filters });
const formatDate = (value: string | null): string =>
    value
        ? new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
              dateStyle: 'long',
          }).format(new Date(value))
        : t('posts.index.draft');

const applyFilters = (): void => {
    router.get(
        publicPostsIndex.url(),
        Object.fromEntries(
            Object.entries(filterForm).filter(([, value]) => value !== ''),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const clearFilters = (): void => {
    Object.assign(filterForm, {
        search: '',
        category: '',
        date_from: '',
        date_to: '',
        sort: 'latest',
    });
    applyFilters();
};
</script>

<template>
    <Head :title="t('posts.public.title')" />
    <div
        class="public-template-page flex min-h-screen flex-col text-slate-950"
        :data-public-template="publicTemplate"
        :style="{
            backgroundColor: 'var(--church-surface, #f8fafc)',
            fontFamily: 'var(--church-font, Manrope, ui-sans-serif)',
        }"
    >
        <PublicHeader active="posts" />
        <main
            class="mx-auto w-full max-w-6xl flex-1 px-3 py-6 sm:px-6 sm:py-9 lg:px-8"
        >
            <header class="mb-8 max-w-3xl">
                <p
                    class="text-xs font-black tracking-[0.2em] uppercase"
                    :style="{ color: 'var(--church-primary)' }"
                >
                    {{ t('posts.public.kicker') }}
                </p>
                <h1 class="mt-2 text-3xl font-black md:text-4xl">
                    {{ t('posts.public.title') }}
                </h1>
                <p class="mt-2 text-slate-600">
                    {{ t('posts.public.description') }}
                </p>
            </header>

            <div class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section class="space-y-5">
                    <article
                        v-for="post in posts.data"
                        :key="post.id"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <Link
                            :href="publicPostShow({ slug: post.slug })"
                            prefetch
                            class="grid md:grid-cols-[15rem_1fr]"
                        >
                            <img
                                v-if="post.cover_url"
                                :src="post.cover_url"
                                :alt="post.title"
                                class="h-44 w-full object-cover sm:h-56 md:h-full"
                            />
                            <div
                                v-else
                                class="grid min-h-52 place-items-center bg-slate-100 text-slate-300"
                            >
                                <Newspaper class="size-12" />
                            </div>
                            <div class="flex min-w-0 flex-col p-4 sm:p-6">
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="category in post.categories"
                                        :key="category"
                                        class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase"
                                        >{{ category }}</span
                                    >
                                </div>
                                <h2 class="mt-3 text-xl font-black">
                                    {{ post.title }}
                                </h2>
                                <p
                                    class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600"
                                >
                                    {{ post.excerpt }}
                                </p>
                                <footer
                                    class="mt-auto flex flex-wrap items-center gap-4 border-t border-slate-100 pt-4 text-xs text-slate-500"
                                >
                                    <span class="font-semibold text-slate-700">
                                        {{ post.author?.first_name }}
                                        {{ post.author?.last_name }}
                                    </span>
                                    <span>{{
                                        formatDate(post.published_at)
                                    }}</span>
                                    <span
                                        class="ml-auto inline-flex items-center gap-1"
                                    >
                                        <Eye class="size-4" />{{
                                            post.views_count
                                        }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <Heart class="size-4" />{{
                                            post.reactions_count
                                        }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <MessageCircle class="size-4" />{{
                                            post.comments_count
                                        }}
                                    </span>
                                </footer>
                            </div>
                        </Link>
                    </article>

                    <div
                        v-if="!posts.data.length"
                        class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500"
                    >
                        {{ t('posts.public.empty') }}
                    </div>

                    <nav
                        v-if="posts.links.length > 3"
                        class="flex flex-wrap gap-2 pt-2"
                    >
                        <template v-for="link in posts.links" :key="link.label">
                            <span
                                v-if="!link.url"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-400"
                                v-html="link.label"
                            />
                            <Link
                                v-else
                                :href="link.url"
                                preserve-scroll
                                class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold"
                                :class="
                                    link.active
                                        ? 'text-white'
                                        : 'bg-white text-slate-700'
                                "
                                :style="
                                    link.active
                                        ? {
                                              backgroundColor:
                                                  'var(--church-primary)',
                                          }
                                        : undefined
                                "
                            >
                                <span v-html="link.label" />
                            </Link>
                        </template>
                    </nav>
                </section>

                <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                    <form
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        @submit.prevent="applyFilters"
                    >
                        <h2 class="flex items-center gap-2 font-black">
                            <SlidersHorizontal class="size-4" />
                            {{ t('posts.public.filters_title') }}
                        </h2>

                        <label
                            class="mt-4 block text-xs font-bold text-slate-600"
                        >
                            {{ t('posts.public.search') }}
                            <span class="relative mt-1.5 block">
                                <Search
                                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    v-model="filterForm.search"
                                    type="search"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pr-3 pl-9 text-sm"
                                    :placeholder="
                                        t('posts.public.search_placeholder')
                                    "
                                />
                            </span>
                        </label>

                        <label
                            class="mt-4 block text-xs font-bold text-slate-600"
                        >
                            {{ t('posts.public.category') }}
                            <select
                                v-model="filterForm.category"
                                class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                            >
                                <option value="">
                                    {{ t('posts.public.all_categories') }}
                                </option>
                                <option
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select>
                        </label>

                        <fieldset class="mt-4">
                            <legend
                                class="flex items-center gap-2 text-xs font-bold text-slate-600"
                            >
                                <CalendarRange class="size-4" />{{
                                    t('posts.public.period')
                                }}
                            </legend>
                            <div class="mt-1.5 grid grid-cols-2 gap-2">
                                <input
                                    v-model="filterForm.date_from"
                                    type="date"
                                    :aria-label="t('posts.public.date_from')"
                                    class="min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-2 py-2.5 text-xs"
                                />
                                <input
                                    v-model="filterForm.date_to"
                                    type="date"
                                    :aria-label="t('posts.public.date_to')"
                                    class="min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-2 py-2.5 text-xs"
                                />
                            </div>
                        </fieldset>

                        <label
                            class="mt-4 block text-xs font-bold text-slate-600"
                        >
                            {{ t('posts.public.order') }}
                            <select
                                v-model="filterForm.sort"
                                class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                            >
                                <option value="latest">
                                    {{ t('posts.public.latest') }}
                                </option>
                                <option value="popular">
                                    {{ t('posts.public.most_accessed') }}
                                </option>
                            </select>
                        </label>

                        <div class="mt-5 grid grid-cols-[1fr_auto] gap-2">
                            <button
                                class="rounded-xl px-4 py-2.5 text-sm font-bold text-white"
                                :style="{
                                    backgroundColor: 'var(--church-primary)',
                                }"
                            >
                                {{ t('posts.public.apply_filters') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 p-2.5 text-slate-500 hover:bg-slate-50"
                                :title="t('posts.public.clear_filters')"
                                @click="clearFilters"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </form>

                    <section
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <h2 class="flex items-center gap-2 font-black">
                            <Eye
                                class="size-4"
                                :style="{ color: 'var(--church-primary)' }"
                            />
                            {{ t('posts.public.most_accessed') }}
                        </h2>
                        <div class="mt-3 divide-y divide-slate-100">
                            <Link
                                v-for="item in mostViewed"
                                :key="item.id"
                                :href="publicPostShow({ slug: item.slug })"
                                prefetch
                                class="grid grid-cols-[2rem_1fr] gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <span
                                    class="grid size-8 place-items-center rounded-lg bg-slate-100 text-xs font-black"
                                >
                                    {{ item.views_count }}
                                </span>
                                <span class="min-w-0">
                                    <strong
                                        class="line-clamp-2 text-sm leading-5"
                                        >{{ item.title }}</strong
                                    >
                                    <small
                                        class="mt-1 block text-[10px] text-slate-500"
                                    >
                                        {{ formatDate(item.published_at) }}
                                    </small>
                                </span>
                            </Link>
                        </div>
                    </section>
                </aside>
            </div>
        </main>
        <PublicFooter show-locale />
    </div>
</template>
