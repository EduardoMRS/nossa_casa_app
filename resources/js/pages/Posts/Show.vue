<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Share2, Trash } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { destroy } from '@/actions/App/Http/Controllers/PostController';
import PostArticle from '@/components/PostArticle.vue';
import type { CommentItem, ReactionItem } from '@/components/PostArticle.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
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
const page = usePage();
const { confirm } = useConfirmDialog();
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

const shareDescription = computed(() =>
    article.value.contentHtml.replace(/<[^>]*>/g, '').slice(0, 160),
);

const sharePost = async (): Promise<void> => {
    const shareData = {
        title: article.value.title,
        text: shareDescription.value,
        url: window.location.href,
    };

    try {
        if (navigator.share) {
            await navigator.share(shareData);
        } else if (navigator.clipboard) {
            await navigator.clipboard.writeText(shareData.url);
            toast.success(t('share.copied'));
        } else {
            toast.error(t('share.unavailable'));
        }
    } catch (error) {
        if ((error as DOMException).name !== 'AbortError') {
            toast.error(t('share.unavailable'));
        }
    }
};

const deletePost = async (): Promise<void> => {
    if (
        await confirm({
            message: t('posts.shared.delete_confirm'),
            confirmLabel: t('actions.delete'),
            intent: 'danger',
        })
    ) {
        router.delete(destroy.url({ post: props.post.id }));
    }
};
</script>

<template>
    <Head :title="article.title">
        <meta
            head-key="description"
            name="description"
            :content="shareDescription"
        />
        <meta
            head-key="og:title"
            property="og:title"
            :content="article.title"
        />
        <meta
            head-key="og:description"
            property="og:description"
            :content="shareDescription"
        />
        <meta
            v-if="article.cover_url"
            head-key="og:image"
            property="og:image"
            :content="article.cover_url"
        />
        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="article.title"
        />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="shareDescription"
        />
    </Head>
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
                        :href="
                            edit(
                                { post: post.id },
                                { query: { return_to: page.url } },
                            )
                        "
                        class="inline-flex size-10 items-center justify-center rounded-lg border border-border bg-card text-card-foreground shadow-sm"
                        :title="t('actions.edit')"
                        :aria-label="t('actions.edit')"
                        ><Edit class="size-4" /><span class="sr-only">{{
                            t('actions.edit')
                        }}</span></Link
                    >
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-lg border border-border bg-card text-card-foreground shadow-sm"
                        :title="t('share.button')"
                        :aria-label="t('share.button')"
                        @click="sharePost"
                    >
                        <Share2 class="size-4" /><span class="sr-only">{{
                            t('share.button')
                        }}</span>
                    </button>
                    <button
                        v-if="can.delete"
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-lg bg-rose-600 text-white"
                        :title="t('actions.delete')"
                        :aria-label="t('actions.delete')"
                        @click="deletePost"
                    >
                        <Trash class="size-4" /><span class="sr-only">{{
                            t('actions.delete')
                        }}</span>
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
