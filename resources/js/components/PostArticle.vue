<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CalendarDays, Eye, Heart, MessageCircle, Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from '@/lib/i18n';
import { useRepositories } from '@/lib/repositories';

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
    reactions?: ReactionItem[];
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
    views_count?: number;
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
const { interactions } = useRepositories();
const page = usePage();
const comment = ref('');
const processing = ref(false);
const reacting = ref('');
const commentItems = ref<CommentItem[]>([...props.comments]);
const reactionItems = ref<ReactionItem[]>([...props.reactions]);
const emojis = ['👍', '❤️', '🙏', '🎉'];
const groupedReactions = computed(() =>
    emojis
        .map((emoji) => ({
            emoji,
            count: reactionItems.value.filter((item) => item.content === emoji)
                .length,
        }))
        .filter((item) => item.count),
);
const currentUserId = computed(() => String(page.props.auth?.user?.id ?? ''));
const ownPostReaction = computed(() =>
    reactionItems.value.find((item) => item.user_id === currentUserId.value),
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
        const response = await interactions.createComment<CommentItem>({
            commentableType: 'post',
            commentableId: props.post.id,
            content: comment.value,
        });
        commentItems.value.unshift({ ...response, reactions: [] });
        comment.value = '';
    } finally {
        processing.value = false;
    }
};

const react = async (content: string): Promise<void> => {
    if (!props.canInteract) {
        return;
    }

    reacting.value = content;

    try {
        if (ownPostReaction.value?.content === content) {
            await interactions.deleteReaction(ownPostReaction.value.id);
            reactionItems.value = reactionItems.value.filter(
                (item) => item.id !== ownPostReaction.value?.id,
            );

            return;
        }

        const response = await interactions.createReaction<ReactionItem>({
            reactionableType: 'post',
            reactionableId: props.post.id,
            content,
            type: 'emoji',
        });
        reactionItems.value = [
            ...reactionItems.value.filter(
                (item) => item.user_id !== response.user_id,
            ),
            response,
        ];
    } finally {
        reacting.value = '';
    }
};

const reactToComment = async (commentItem: CommentItem): Promise<void> => {
    if (!props.canInteract) {
        return;
    }

    const ownReaction = (commentItem.reactions ?? []).find(
        (item) => item.user_id === currentUserId.value,
    );
    reacting.value = commentItem.id;

    try {
        if (ownReaction) {
            await interactions.deleteReaction(ownReaction.id);
            commentItem.reactions = (commentItem.reactions ?? []).filter(
                (item) => item.id !== ownReaction.id,
            );

            return;
        }

        const response = await interactions.createReaction<ReactionItem>({
            reactionableType: 'comment',
            reactionableId: commentItem.id,
            content: '❤️',
            type: 'emoji',
        });
        commentItem.reactions = [
            ...(commentItem.reactions ?? []).filter(
                (item) => item.user_id !== response.user_id,
            ),
            response,
        ];
    } finally {
        reacting.value = '';
    }
};
</script>

<template>
    <div class="min-w-0 space-y-6">
        <article
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <img
                v-if="post.cover_url"
                :src="post.cover_url"
                :alt="post.title"
                class="max-h-[36rem] w-full object-cover"
            />
            <div class="p-6 md:p-9">
                <div
                    class="flex flex-wrap items-center gap-2 text-[10px] font-bold tracking-wider uppercase"
                    :style="{ color: 'var(--church-primary)' }"
                >
                    <span v-if="post.church">{{ post.church.name }}</span>
                    <span v-if="post.category">· {{ post.category }}</span>
                </div>
                <h1
                    class="mt-3 text-3xl leading-tight font-black text-slate-950 md:text-5xl"
                >
                    {{ post.title }}
                </h1>
                <div
                    class="mt-4 flex flex-wrap items-center gap-4 border-b border-slate-100 pb-5 text-xs text-slate-500"
                >
                    <span v-if="post.author" class="font-bold text-slate-700">
                        {{ post.author.first_name }} {{ post.author.last_name }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <CalendarDays class="size-4" />{{
                            formatDate(post.published_at)
                        }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <Eye class="size-4" />{{ post.views_count ?? 0 }}
                    </span>
                </div>
                <div
                    class="markdown-content mt-7 text-[15px] leading-7 text-slate-700"
                    v-html="post.contentHtml"
                />
            </div>

            <section class="border-t border-slate-100 px-5 py-3 md:px-9">
                <div
                    class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500"
                >
                    <div class="flex items-center gap-2">
                        <span
                            v-if="groupedReactions.length"
                            class="flex -space-x-1"
                        >
                            <span
                                v-for="item in groupedReactions"
                                :key="item.emoji"
                                class="grid size-6 place-items-center rounded-full border-2 border-white bg-slate-100 text-xs"
                                >{{ item.emoji }}</span
                            >
                        </span>
                        <span>{{
                            t('posts.show.reaction_count', {
                                count: reactionItems.length,
                            })
                        }}</span>
                    </div>
                    <span>{{
                        t('posts.show.comment_count', {
                            count: commentItems.length,
                        })
                    }}</span>
                </div>

                <div
                    class="mt-3 grid grid-cols-4 border-t border-slate-100 pt-2"
                >
                    <button
                        v-for="emoji in emojis"
                        :key="emoji"
                        type="button"
                        :disabled="!canInteract || reacting !== ''"
                        class="flex items-center justify-center gap-1 rounded-lg py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-45"
                        :class="
                            ownPostReaction?.content === emoji
                                ? 'bg-slate-100 ring-2 ring-slate-200'
                                : ''
                        "
                        :aria-pressed="ownPostReaction?.content === emoji"
                        @click="react(emoji)"
                    >
                        <span class="text-lg">{{ emoji }}</span>
                    </button>
                </div>
                <p
                    v-if="!canInteract"
                    class="mt-2 text-center text-xs text-slate-500"
                >
                    {{ t('posts.show.login_to_interact') }}
                </p>
            </section>
        </article>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-6"
        >
            <div
                class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4"
            >
                <h2 class="flex items-center gap-2 text-lg font-black">
                    <MessageCircle
                        class="size-5"
                        :style="{ color: 'var(--church-primary)' }"
                    />
                    {{ t('posts.show.recent_comments') }}
                </h2>
                <span
                    class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500"
                >
                    {{ commentItems.length }}
                </span>
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
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold text-white disabled:opacity-50"
                        :style="{ backgroundColor: 'var(--church-primary)' }"
                    >
                        <Send class="size-4" />{{
                            t('posts.show.comment_submit')
                        }}
                    </button>
                </div>
            </form>

            <div v-if="commentItems.length" class="mt-4 space-y-3">
                <article
                    v-for="item in commentItems"
                    :key="item.id"
                    class="rounded-xl border border-slate-100 bg-slate-50 p-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <strong class="text-sm text-slate-800">
                            {{ item.user_details?.first_name }}
                            {{ item.user_details?.last_name }}
                        </strong>
                        <time class="text-[10px] text-slate-400">{{
                            formatDate(item.created_at)
                        }}</time>
                    </div>
                    <p
                        class="mt-2 text-sm leading-6 whitespace-pre-wrap text-slate-600"
                    >
                        {{ item.content }}
                    </p>
                    <button
                        type="button"
                        :disabled="!canInteract || reacting !== ''"
                        class="mt-2 inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-slate-500 transition hover:bg-white hover:text-rose-600 disabled:opacity-40"
                        :class="
                            (item.reactions ?? []).some(
                                (reaction) =>
                                    reaction.user_id === currentUserId,
                            )
                                ? 'text-rose-600'
                                : ''
                        "
                        :aria-pressed="
                            (item.reactions ?? []).some(
                                (reaction) =>
                                    reaction.user_id === currentUserId,
                            )
                        "
                        @click="reactToComment(item)"
                    >
                        <Heart class="size-3.5" />
                        {{ (item.reactions ?? []).length }}
                    </button>
                </article>
            </div>
            <p v-else class="mt-5 text-center text-sm text-slate-400">
                {{ t('posts.show.no_comments') }}
            </p>
        </section>
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
    color: var(--church-primary, #4f46e5);
    font-weight: 700;
    text-decoration: underline;
}
.markdown-content :deep(blockquote) {
    border-left: 4px solid var(--church-primary, #6366f1);
    background: #f8fafc;
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
