<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { ArrowLeft, MessageCircle, Pin, Send, Trash2 } from '@lucide/vue';
import axios from 'axios';
import { computed, ref } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';

type Person = {
    id: string;
    first_name: string;
    last_name: string;
};

type CommentItem = {
    id: string;
    content: string;
    is_pinned: boolean;
    created_at: string;
    user: Person | null;
};

const props = defineProps<{
    liveStream: {
        id: string;
        name: string;
        status: string;
        embed_url: string;
        started_at: string | null;
    };
    realtimePrivate: boolean;
    comments: CommentItem[];
    canComment: boolean;
    canModerate: boolean;
}>();

const { locale, t } = useI18n();
const page = usePage();
const stream = ref({ ...props.liveStream });
const comments = ref([...props.comments]);
const content = ref('');
const processing = ref(false);
const error = ref('');
const isLive = computed(() => stream.value.status === 'live');
const isEnded = computed(() =>
    ['stopped', 'failed'].includes(stream.value.status),
);
const loginUrl = computed(
    () => `/login?redirect=${encodeURIComponent(page.url)}`,
);

type StreamUpdatedPayload = {
    id: string;
    status: string;
    embed_url: string;
    started_at: string | null;
};

type CommentsUpdatedPayload = { comments: CommentItem[] };

useEcho<StreamUpdatedPayload, 'reverb', 'private' | 'public'>(
    `live-stream.${props.liveStream.id}`,
    '.live-stream.updated',
    (payload) => {
        stream.value = { ...stream.value, ...payload };
    },
    [],
    props.realtimePrivate ? 'private' : 'public',
);

useEcho<CommentsUpdatedPayload, 'reverb', 'private' | 'public'>(
    `live-stream.${props.liveStream.id}`,
    '.live-stream.comments.updated',
    (payload) => {
        comments.value = payload.comments;
    },
    [],
    props.realtimePrivate ? 'private' : 'public',
);

const submitComment = async (): Promise<void> => {
    if (!content.value.trim() || processing.value) {
        return;
    }

    processing.value = true;
    error.value = '';

    try {
        await axios.post('/api/comments', {
            commentable_type: 'live_stream',
            commentable_id: props.liveStream.id,
            content: content.value,
        });
        content.value = '';
    } catch {
        error.value = t('live_stream.comment_error');
    } finally {
        processing.value = false;
    }
};

const togglePin = async (comment: CommentItem): Promise<void> => {
    await axios.put(`/api/comments/${comment.id}/pin`, {
        is_pinned: !comment.is_pinned,
    });
};

const removeComment = async (comment: CommentItem): Promise<void> => {
    if (!window.confirm(t('live_stream.remove_confirm'))) {
        return;
    }

    await axios.delete(`/api/comments/${comment.id}`);
};

const formatDate = (value: string): string =>
    new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
</script>

<template>
    <Head :title="stream.name" />

    <div class="flex min-h-screen flex-col bg-slate-950 text-white">
        <PublicHeader />

        <main
            class="mx-auto grid w-full max-w-6xl flex-1 gap-5 px-3 py-5 sm:px-6 sm:py-7 lg:grid-cols-[minmax(0,1fr)_21rem] lg:gap-6 lg:px-8"
        >
            <section class="min-w-0">
                <Link
                    href="/"
                    class="mb-4 inline-flex items-center gap-2 text-sm font-bold text-slate-300 hover:text-white"
                >
                    <ArrowLeft class="size-4" /> {{ t('actions.back') }}
                </Link>

                <div
                    class="aspect-video overflow-hidden rounded-2xl border border-white/10 bg-black shadow-2xl"
                >
                    <iframe
                        v-if="isLive"
                        :src="stream.embed_url"
                        :title="stream.name"
                        class="h-full w-full border-0"
                        allow="autoplay; fullscreen; picture-in-picture"
                        allowfullscreen
                        scrolling="no"
                    />
                    <div
                        v-else
                        class="grid h-full place-items-center p-8 text-center text-slate-300"
                    >
                        <span v-if="isEnded">{{ t('live_stream.ended') }}</span>
                        <span v-else>{{ t('live_stream.connecting') }}</span>
                    </div>
                </div>

                <div class="mt-5 flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-black tracking-[0.18em] text-rose-400 uppercase"
                        >
                            {{ t('live_stream.kicker') }}
                        </p>
                        <h1 class="mt-1 text-2xl font-black sm:text-3xl">
                            {{ stream.name }}
                        </h1>
                    </div>
                    <span
                        v-if="isLive"
                        class="rounded-full bg-rose-600 px-3 py-1.5 text-xs font-black uppercase"
                        >{{ t('live_stream.live') }}</span
                    >
                </div>
            </section>

            <aside
                class="flex min-h-96 flex-col overflow-hidden rounded-2xl border border-white/10 bg-white text-slate-950 sm:min-h-[30rem] lg:max-h-[calc(100vh-8rem)]"
            >
                <header
                    class="flex items-center gap-2 border-b border-slate-200 p-4 font-black"
                >
                    <MessageCircle class="size-5" />
                    {{ t('live_stream.comments') }}
                </header>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    <article
                        v-for="comment in comments"
                        :key="comment.id"
                        class="rounded-xl border p-3"
                        :class="
                            comment.is_pinned
                                ? 'border-amber-300 bg-amber-50'
                                : 'border-slate-200'
                        "
                    >
                        <div class="flex items-start justify-between gap-2">
                            <strong class="text-sm">
                                {{ comment.user?.first_name }}
                                {{ comment.user?.last_name }}
                            </strong>
                            <Pin
                                v-if="comment.is_pinned"
                                class="size-4 shrink-0 fill-amber-500 text-amber-600"
                            />
                        </div>
                        <p
                            class="mt-1 text-sm leading-5 whitespace-pre-wrap text-slate-700"
                        >
                            {{ comment.content }}
                        </p>
                        <div
                            class="mt-2 flex items-center justify-between gap-2 text-[10px] text-slate-400"
                        >
                            <time>{{ formatDate(comment.created_at) }}</time>
                            <span v-if="canModerate" class="flex gap-1">
                                <button
                                    type="button"
                                    class="rounded p-1 hover:bg-slate-200 hover:text-amber-700"
                                    :title="
                                        comment.is_pinned
                                            ? t('live_stream.unpin')
                                            : t('live_stream.pin')
                                    "
                                    @click="togglePin(comment)"
                                >
                                    <Pin class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 hover:bg-rose-100 hover:text-rose-700"
                                    :title="t('live_stream.remove_comment')"
                                    @click="removeComment(comment)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </span>
                        </div>
                    </article>

                    <p
                        v-if="!comments.length"
                        class="py-10 text-center text-sm text-slate-400"
                    >
                        {{ t('live_stream.first_comment') }}
                    </p>
                </div>

                <footer class="border-t border-slate-200 p-4">
                    <form
                        v-if="canComment"
                        class="flex gap-2"
                        @submit.prevent="submitComment"
                    >
                        <input
                            v-model="content"
                            maxlength="5000"
                            class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                            :placeholder="t('live_stream.comment_placeholder')"
                        />
                        <button
                            :disabled="processing || !content.trim()"
                            class="grid size-10 place-items-center rounded-xl bg-indigo-600 text-white disabled:opacity-40"
                        >
                            <Send class="size-4" />
                        </button>
                    </form>
                    <Link
                        v-else
                        :href="loginUrl"
                        class="block rounded-xl bg-indigo-600 px-4 py-3 text-center text-sm font-black text-white"
                        >{{ t('live_stream.login_to_comment') }}</Link
                    >
                    <p
                        v-if="error"
                        class="mt-2 text-xs font-bold text-rose-600"
                    >
                        {{ error }}
                    </p>
                </footer>
            </aside>
        </main>

        <PublicFooter show-locale />
    </div>
</template>
