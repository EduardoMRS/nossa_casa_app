<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Trash } from '@lucide/vue';
import { computed } from 'vue';
import { destroy } from '@/actions/App/Http/Controllers/PostController';
import PostArticle from '@/components/PostArticle.vue';
import type { CommentItem, ReactionItem } from '@/components/PostArticle.vue';
import { useI18n } from '@/lib/i18n';
import { edit, index } from '@/routes/posts';

const props = defineProps<{
    post: Record<string, any>;
    can: { edit: boolean; delete: boolean; comment: boolean; react: boolean };
    comments: CommentItem[];
    reactions: ReactionItem[];
    contentHtml: string;
}>();

const { t } = useI18n();
const article = computed(() => ({
    id: props.post.id,
    title: props.post.translations?.title || props.post.title,
    contentHtml: props.contentHtml,
    published_at: props.post.published_at,
    category: props.post.category,
    cover_url: props.post.medias?.[0]?.url ?? null,
    author: props.post.author_details,
    church: props.post.church ?? null,
    metrics: props.post.metrics ?? {
        comments_count: props.comments.length,
        reactions_count: props.reactions.length,
    },
}));

const deletePost = (): void => {
    if (confirm(t('posts.shared.delete_confirm'))) {
        router.delete(destroy.url({ post: props.post.id }));
    }
};
</script>

<template>
    <Head :title="article.title" />
    <main class="min-h-screen bg-background p-4 text-foreground md:p-8">
        <div class="mx-auto max-w-7xl space-y-5">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="index()"
                    class="inline-flex items-center gap-2 text-sm font-bold text-primary"
                    ><ArrowLeft class="size-4" />{{
                        t('posts.show.back')
                    }}</Link
                >
                <div class="flex gap-2">
                    <Link
                        v-if="can.edit"
                        :href="edit({ post: post.id })"
                        class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-bold text-card-foreground shadow-sm"
                        ><Edit class="size-4" />{{ t('actions.edit') }}</Link
                    >
                    <button
                        v-if="can.delete"
                        class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-bold text-white"
                        @click="deletePost"
                    >
                        <Trash class="size-4" />{{ t('actions.delete') }}
                    </button>
                </div>
            </header>
            <PostArticle
                :post="article"
                :comments="comments"
                :reactions="reactions"
                :can-interact="can.comment || can.react"
            />
        </div>
    </main>
</template>
