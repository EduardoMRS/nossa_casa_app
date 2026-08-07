<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Plus,
    Edit,
    Trash,
    Eye,
    MessageCircle,
    Heart,
    Clock,
    Tags,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { destroy } from '@/actions/App/Http/Controllers/PostController';
import CategoryManagerModal from '@/components/CategoryManagerModal.vue';
import type { ManagedCategory } from '@/components/CategoryManagerModal.vue';
import { useI18n } from '@/lib/i18n';
import { create, edit, show } from '@/routes/posts';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface User {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
}

export interface PostMetrics {
    comments_count: number;
    reactions_count: number;
}

export interface Post {
    id: string;
    title: string;
    slug: string;
    content: string;
    published_at: string | null;
    expires_at: string | null;
    author: User;
    metrics: PostMetrics;
    translations: Record<string, string> | any[];
}

const props = defineProps<{
    posts: {
        data: Post[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    categories: ManagedCategory[];
}>();
const { locale, t } = useI18n();
const categoriesOpen = ref(false);

const page = usePage<{
    branding?: {
        primary_color?: string;
        secondary_color?: string;
        accent_color?: string;
        font_family?: string;
    };
}>();

const branding = computed(() => page.props.branding ?? {});

const paletteStyle = computed(() => ({
    '--brand-primary': branding.value.primary_color ?? '#2f6e79',
    '--brand-secondary': branding.value.secondary_color ?? '#5f7d95',
    '--brand-accent': branding.value.accent_color ?? '#c88b4a',
    '--brand-font': branding.value.font_family ?? 'Manrope, ui-sans-serif',
}));

const deletePost = (id: string) => {
    if (confirm(t('posts.shared.delete_confirm'))) {
        router.delete(destroy.url({ post: id }));
    }
};

const formatDate = (dateString: string | null) => {
    if (!dateString) {
        return null;
    }

    return new Date(dateString).toLocaleDateString(
        locale.value === 'pt' ? 'pt-BR' : 'en-US',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        },
    );
};

const getTitle = (post: Post) => {
    if (!Array.isArray(post.translations) && post.translations?.title) {
        return post.translations.title;
    }

    return post.title;
};
</script>

<template>
    <div
        class="min-h-screen bg-[#f4f7fb] p-5 text-slate-800 md:p-8 dark:bg-slate-900 dark:text-slate-100"
        :style="paletteStyle"
    >
        <Head :title="t('posts.index.meta_title')" />

        <div class="mx-auto max-w-7xl space-y-6">
            <section
                class="rounded-2xl border border-indigo-900 bg-gradient-to-br from-indigo-950 to-indigo-800 p-6 text-white shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h1
                            class="text-2xl font-black tracking-tight md:text-3xl"
                            :style="{ fontFamily: 'var(--brand-font)' }"
                        >
                            {{ t('posts.index.title') }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-100/90">
                            {{ t('posts.index.description') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            class="inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-sm font-semibold"
                            @click="categoriesOpen = true"
                        >
                            <Tags class="size-4" />{{
                                t('admin.categories.title')
                            }}</button
                        ><Link
                            :href="create()"
                            class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-800"
                        >
                            <Plus class="h-4 w-4" />
                            {{ t('posts.index.new') }}
                        </Link>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800/70"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300"
                        >
                            <tr>
                                <th class="px-5 py-3 font-semibold">
                                    {{ t('posts.index.content') }}
                                </th>
                                <th class="px-5 py-3 font-semibold">
                                    {{ t('posts.index.author') }}
                                </th>
                                <th class="px-5 py-3 font-semibold">
                                    {{ t('posts.index.engagement') }}
                                </th>
                                <th class="px-5 py-3 font-semibold">
                                    {{ t('posts.index.status') }}
                                </th>
                                <th class="px-5 py-3 text-right font-semibold">
                                    {{ t('posts.index.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-700/80"
                        >
                            <tr
                                v-for="post in props.posts.data"
                                :key="post.id"
                                class="transition hover:bg-slate-50/80 dark:hover:bg-slate-700/20"
                            >
                                <td class="px-5 py-4">
                                    <p
                                        class="max-w-md truncate font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{ getTitle(post) }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-slate-500 dark:text-slate-300"
                                    >
                                        /{{ post.slug }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="grid h-8 w-8 place-items-center rounded-full border border-slate-300 bg-slate-100 text-xs font-bold text-slate-700 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                                        >
                                            {{ post.author.first_name.charAt(0)
                                            }}{{
                                                post.author.last_name.charAt(0)
                                            }}
                                        </div>
                                        <span
                                            class="text-slate-700 dark:text-slate-200"
                                            >{{ post.author.first_name }}
                                            {{ post.author.last_name }}</span
                                        >
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div
                                        class="flex items-center gap-4 text-slate-600 dark:text-slate-300"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1 text-xs"
                                        >
                                            <Heart class="h-4 w-4" />
                                            {{ post.metrics.reactions_count }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1 text-xs"
                                        >
                                            <MessageCircle class="h-4 w-4" />
                                            {{ post.metrics.comments_count }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div
                                        class="inline-flex items-center gap-2 text-sm"
                                    >
                                        <Clock
                                            class="h-4 w-4"
                                            :style="{
                                                color: post.published_at
                                                    ? 'var(--brand-primary)'
                                                    : 'var(--brand-secondary)',
                                            }"
                                        />
                                        <span
                                            class="text-slate-700 dark:text-slate-200"
                                            >{{
                                                formatDate(post.published_at) ??
                                                t('posts.index.draft')
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Link
                                            :href="show({ post: post.id })"
                                            class="rounded-lg border border-slate-200 p-2 text-slate-600 transition hover:text-slate-900 dark:border-slate-600 dark:text-slate-200 dark:hover:text-white"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Link>
                                        <Link
                                            :href="edit({ post: post.id })"
                                            class="rounded-lg border border-slate-200 p-2 text-slate-600 transition hover:text-slate-900 dark:border-slate-600 dark:text-slate-200 dark:hover:text-white"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Link>
                                        <button
                                            @click="deletePost(post.id)"
                                            class="rounded-lg border border-red-200 p-2 text-red-600 transition hover:bg-red-50 dark:border-red-500/40 dark:hover:bg-red-500/10"
                                        >
                                            <Trash class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!props.posts.data.length">
                                <td
                                    colspan="5"
                                    class="px-5 py-12 text-center text-slate-500 dark:text-slate-300"
                                >
                                    {{ t('posts.index.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-if="props.posts.links.length > 3"
                class="flex flex-col items-center justify-between gap-4 sm:flex-row"
            >
                <p class="text-sm text-slate-500 dark:text-slate-300">
                    {{
                        t('posts.index.pagination', {
                            from: props.posts.from ?? 0,
                            to: props.posts.to ?? 0,
                            total: props.posts.total,
                        })
                    }}
                </p>

                <div class="flex flex-wrap gap-2">
                    <template
                        v-for="(link, key) in props.posts.links"
                        :key="key"
                    >
                        <span
                            v-if="link.url === null"
                            class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500"
                            v-html="link.label"
                        />
                        <Link
                            v-else
                            :href="link.url"
                            class="rounded-lg border px-3 py-1.5 text-sm"
                            :class="
                                link.active
                                    ? 'border-transparent text-white'
                                    : 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'
                            "
                            :style="
                                link.active
                                    ? {
                                          backgroundColor:
                                              'var(--brand-primary)',
                                      }
                                    : undefined
                            "
                        >
                            <span v-html="link.label" />
                        </Link>
                    </template>
                </div>
            </section>
        </div>
        <CategoryManagerModal
            :open="categoriesOpen"
            category-type="post"
            :categories="categories"
            @close="categoriesOpen = false"
        />
    </div>
</template>
