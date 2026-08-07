<script setup lang="ts">
import { CalendarDays, Heart, MessageCircle, Send, Tags } from '@lucide/vue';
import axios from 'axios';
import { computed, ref } from 'vue';
import { useI18n } from '@/lib/i18n';

export type Person = {
    id: string;
    first_name: string;
    last_name: string;
    email?: string;
};
export type CommentItem = {
    id: string;
    content: string;
    created_at: string;
    user_details?: Person | null;
};
export type ReactionItem = {
    id: string;
    user_id: string;
    content: string;
    user_details?: Person | null;
};
export type ArticlePost = {
    id: string;
    title: string;
    contentHtml: string;
    published_at: string | null;
    category?: string | null;
    cover_url?: string | null;
    author?: Person | null;
    church?: { name: string } | null;
    metrics: { comments_count: number; reactions_count: number };
};

const props = defineProps<{
    post: ArticlePost;
    comments: CommentItem[];
    reactions: ReactionItem[];
    canInteract: boolean;
}>();
const { locale, t } = useI18n();
const comment = ref('');
const processing = ref(false);
const emojis = ['👍', '❤️', '🙏', '🎉'];
const groupedReactions = computed(() =>
    emojis
        .map((emoji) => ({
            emoji,
            count: props.reactions.filter((item) => item.content === emoji)
                .length,
        }))
        .filter((item) => item.count),
);

const formatDate = (value: string | null): string =>
    value
        ? new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
              dateStyle: 'long',
              timeStyle: 'short',
          }).format(new Date(value))
        : t('posts.index.draft');

const submitComment = async (): Promise<void> => {
    if (!comment.value.trim()) {
        return;
    }

    processing.value = true;

    try {
        await axios.post('/api/comments', {
            commentable_type: 'post',
            commentable_id: props.post.id,
            content: comment.value,
        });
        comment.value = '';
        window.location.reload();
    } finally {
        processing.value = false;
    }
};

const react = async (content: string): Promise<void> => {
    await axios.post('/api/reactions', {
        reactionable_type: 'post',
        reactionable_id: props.post.id,
        content,
        type: 'emoji',
    });
    window.location.reload();
};
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px] lg:items-start">
        <div class="space-y-6">
            <article
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <img
                    v-if="post.cover_url"
                    :src="post.cover_url"
                    :alt="post.title"
                    class="max-h-[32rem] w-full object-cover"
                />
                <div class="p-6 md:p-9">
                    <div
                        class="flex flex-wrap items-center gap-2 text-[10px] font-bold tracking-wider text-indigo-600 uppercase"
                    >
                        <span v-if="post.church">{{ post.church.name }}</span
                        ><span v-if="post.category">· {{ post.category }}</span>
                    </div>
                    <h1
                        class="mt-3 text-3xl leading-tight font-black text-slate-950 md:text-5xl"
                    >
                        {{ post.title }}
                    </h1>
                    <div
                        class="mt-4 flex flex-wrap items-center gap-4 border-b border-slate-100 pb-5 text-xs text-slate-500"
                    >
                        <span
                            v-if="post.author"
                            class="font-bold text-slate-700"
                            >{{ post.author.first_name }}
                            {{ post.author.last_name }}</span
                        ><span class="inline-flex items-center gap-1.5"
                            ><CalendarDays class="size-4" />{{
                                formatDate(post.published_at)
                            }}</span
                        >
                    </div>
                    <div
                        class="markdown-content mt-7 text-[15px] leading-7 text-slate-700"
                        v-html="post.contentHtml"
                    />
                </div>
            </article>

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4"
                >
                    <h2 class="flex items-center gap-2 text-lg font-black">
                        <MessageCircle class="size-5 text-indigo-600" />{{
                            t('posts.show.recent_comments')
                        }}
                    </h2>
                    <span
                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500"
                        >{{ comments.length }}</span
                    >
                </div>
                <form
                    v-if="canInteract"
                    class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3"
                    @submit.prevent="submitComment"
                >
                    <textarea
                        v-model="comment"
                        rows="3"
                        class="w-full resize-none border-0 bg-transparent text-sm shadow-none focus:ring-0"
                        :placeholder="t('posts.show.comment_placeholder')"
                    />
                    <div class="flex justify-end">
                        <button
                            :disabled="processing"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50"
                        >
                            <Send class="size-4" />{{
                                t('posts.show.comment_submit')
                            }}
                        </button>
                    </div>
                </form>
                <p
                    v-else
                    class="mt-4 rounded-lg bg-indigo-50 p-3 text-xs text-indigo-700"
                >
                    {{ t('posts.show.login_to_interact') }}
                </p>
                <div v-if="comments.length" class="mt-4 space-y-3">
                    <article
                        v-for="item in comments"
                        :key="item.id"
                        class="rounded-lg border border-slate-100 bg-slate-50 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <strong class="text-sm text-slate-800"
                                >{{ item.user_details?.first_name }}
                                {{ item.user_details?.last_name }}</strong
                            ><time class="text-[10px] text-slate-400">{{
                                formatDate(item.created_at)
                            }}</time>
                        </div>
                        <p
                            class="mt-2 text-sm leading-6 whitespace-pre-wrap text-slate-600"
                        >
                            {{ item.content }}
                        </p>
                    </article>
                </div>
                <p v-else class="mt-5 text-center text-sm text-slate-400">
                    {{ t('posts.show.no_comments') }}
                </p>
            </section>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24">
            <section
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <p
                    class="font-mono text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                >
                    {{ t('posts.show.engagement') }}
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div class="rounded-lg bg-rose-50 p-3">
                        <Heart class="size-4 text-rose-500" /><strong
                            class="mt-1 block text-lg"
                            >{{ post.metrics.reactions_count }}</strong
                        ><span class="text-[10px] text-slate-500">{{
                            t('posts.show.reactions')
                        }}</span>
                    </div>
                    <div class="rounded-lg bg-indigo-50 p-3">
                        <MessageCircle class="size-4 text-indigo-500" /><strong
                            class="mt-1 block text-lg"
                            >{{ post.metrics.comments_count }}</strong
                        ><span class="text-[10px] text-slate-500">{{
                            t('posts.show.comments_label')
                        }}</span>
                    </div>
                </div>
            </section>
            <section
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <p class="flex items-center gap-2 text-sm font-black">
                    <Heart class="size-4 text-rose-500" />{{
                        t('posts.show.react')
                    }}
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="emoji in emojis"
                        :key="emoji"
                        :disabled="!canInteract"
                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-lg transition hover:-translate-y-0.5 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="react(emoji)"
                    >
                        {{ emoji }}
                    </button>
                </div>
                <div
                    v-if="groupedReactions.length"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <span
                        v-for="item in groupedReactions"
                        :key="item.emoji"
                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold"
                        >{{ item.emoji }} {{ item.count }}</span
                    >
                </div>
            </section>
            <section
                v-if="post.category"
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <p class="flex items-center gap-2 text-sm font-black">
                    <Tags class="size-4 text-indigo-600" />{{
                        t('events.show.categories')
                    }}
                </p>
                <span
                    class="mt-3 inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700"
                    >{{ post.category }}</span
                >
            </section>
        </aside>
    </div>
</template>

<style scoped>
.markdown-content :deep(h1),
.markdown-content :deep(h2),
.markdown-content :deep(h3) {
    margin: 1.5em 0 0.55em;
    color: #0f172a;
    font-weight: 900;
    line-height: 1.2;
}
.markdown-content :deep(h1) {
    font-size: 2em;
}
.markdown-content :deep(h2) {
    font-size: 1.55em;
}
.markdown-content :deep(h3) {
    font-size: 1.25em;
}
.markdown-content :deep(p),
.markdown-content :deep(ul),
.markdown-content :deep(ol),
.markdown-content :deep(blockquote),
.markdown-content :deep(pre) {
    margin: 1em 0;
}
.markdown-content :deep(ul) {
    list-style: disc;
    padding-left: 1.5em;
}
.markdown-content :deep(ol) {
    list-style: decimal;
    padding-left: 1.5em;
}
.markdown-content :deep(a) {
    color: #4f46e5;
    font-weight: 700;
    text-decoration: underline;
}
.markdown-content :deep(blockquote) {
    border-left: 4px solid #6366f1;
    background: #eef2ff;
    padding: 0.75em 1em;
    font-style: italic;
}
.markdown-content :deep(code) {
    border-radius: 0.35rem;
    background: #f1f5f9;
    padding: 0.15rem 0.35rem;
    font-size: 0.9em;
}
.markdown-content :deep(pre) {
    overflow-x: auto;
    border-radius: 0.75rem;
    background: #0f172a;
    padding: 1rem;
    color: white;
}
</style>
