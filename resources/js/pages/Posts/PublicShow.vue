<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Clock3, Layers3 } from '@lucide/vue';
import PostArticle from '@/components/PostArticle.vue';
import type {
    ArticlePost,
    CommentItem,
    ReactionItem,
} from '@/components/PostArticle.vue';
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
};

defineProps<{
    post: ArticlePost;
    comments: CommentItem[];
    reactions: ReactionItem[];
    canInteract: boolean;
    relatedPosts: PublicPost[];
    latestPosts: PublicPost[];
}>();

const { locale, t } = useI18n();
const publicTemplate = usePublicTemplate('posts_show');
const formatDate = (value: string | null): string =>
    value
        ? new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
              dateStyle: 'medium',
          }).format(new Date(value))
        : '';
</script>

<template>
    <Head :title="post.title" />
    <div
        class="public-template-page flex min-h-screen flex-col text-slate-950"
        :data-public-template="publicTemplate"
        :style="{
            backgroundColor: 'var(--church-surface, #f8fafc)',
            fontFamily: 'var(--church-font, Manrope, ui-sans-serif)',
        }"
    >
        <PublicHeader active="posts" />
        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
            <Link
                :href="publicPostsIndex()"
                class="mb-5 inline-flex items-center gap-2 text-sm font-bold"
                :style="{ color: 'var(--church-primary)' }"
            >
                <ArrowLeft class="size-4" />{{ t('posts.show.back') }}
            </Link>

            <div
                class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
            >
                <PostArticle
                    :post="post"
                    :comments="comments"
                    :reactions="reactions"
                    :can-interact="canInteract"
                />

                <aside class="space-y-5 lg:sticky lg:top-24">
                    <section
                        v-if="relatedPosts.length"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <h2 class="flex items-center gap-2 font-black">
                            <Layers3
                                class="size-4"
                                :style="{ color: 'var(--church-primary)' }"
                            />
                            {{ t('posts.show.related') }}
                        </h2>
                        <div class="mt-3 divide-y divide-slate-100">
                            <Link
                                v-for="item in relatedPosts"
                                :key="item.id"
                                :href="publicPostShow({ slug: item.slug })"
                                prefetch
                                class="block py-3 first:pt-0 last:pb-0"
                            >
                                <strong
                                    class="line-clamp-2 text-sm leading-5"
                                    >{{ item.title }}</strong
                                >
                                <span
                                    class="mt-1 line-clamp-2 block text-xs leading-5 text-slate-500"
                                >
                                    {{ item.excerpt }}
                                </span>
                            </Link>
                        </div>
                    </section>

                    <section
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <h2 class="flex items-center gap-2 font-black">
                            <Clock3
                                class="size-4"
                                :style="{ color: 'var(--church-primary)' }"
                            />
                            {{ t('posts.show.latest') }}
                        </h2>
                        <div class="mt-3 divide-y divide-slate-100">
                            <Link
                                v-for="item in latestPosts"
                                :key="item.id"
                                :href="publicPostShow({ slug: item.slug })"
                                prefetch
                                class="block py-3 first:pt-0 last:pb-0"
                            >
                                <strong
                                    class="line-clamp-2 text-sm leading-5"
                                    >{{ item.title }}</strong
                                >
                                <small
                                    class="mt-1 block text-[10px] text-slate-500"
                                >
                                    {{ formatDate(item.published_at) }}
                                </small>
                            </Link>
                            <p
                                v-if="!latestPosts.length"
                                class="text-sm text-slate-500"
                            >
                                {{ t('posts.public.empty') }}
                            </p>
                        </div>
                    </section>
                </aside>
            </div>
        </main>
        <PublicFooter />
    </div>
</template>
