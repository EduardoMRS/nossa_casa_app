<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import PostArticle from '@/components/PostArticle.vue';
import type {
    ArticlePost,
    CommentItem,
    ReactionItem,
} from '@/components/PostArticle.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';

defineProps<{
    post: ArticlePost;
    comments: CommentItem[];
    reactions: ReactionItem[];
    canInteract: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <Head :title="post.title" />
    <div class="flex min-h-screen flex-col bg-slate-50 text-slate-950">
        <PublicHeader />
        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
            <Link
                href="/"
                class="mb-5 inline-flex items-center gap-2 text-sm font-bold text-indigo-700"
            >
                <ArrowLeft class="size-4" />{{ t('posts.public.back_home') }}
            </Link>
            <PostArticle
                :post="post"
                :comments="comments"
                :reactions="reactions"
                :can-interact="canInteract"
            />
        </main>
        <PublicFooter />
    </div>
</template>
