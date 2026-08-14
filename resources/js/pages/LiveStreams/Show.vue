<script setup lang="ts">
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, MessageCircle, Pin, Send, Trash2 } from '@lucide/vue';
import axios from 'axios';
import { computed, ref } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';

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
    comments: CommentItem[];
    canComment: boolean;
    canModerate: boolean;
}>();

usePoll(3000, { only: ['comments', 'liveStream'] });

const page = usePage();
const content = ref('');
const processing = ref(false);
const error = ref('');
const isLive = computed(() => props.liveStream.status === 'live');
const isEnded = computed(() =>
    ['stopped', 'failed'].includes(props.liveStream.status),
);
const loginUrl = computed(
    () => `/login?redirect=${encodeURIComponent(page.url)}`,
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
        router.reload({ only: ['comments'] });
    } catch {
        error.value = 'Não foi possível enviar o comentário.';
    } finally {
        processing.value = false;
    }
};

const togglePin = async (comment: CommentItem): Promise<void> => {
    await axios.put(`/api/comments/${comment.id}/pin`, {
        is_pinned: !comment.is_pinned,
    });
    router.reload({ only: ['comments'] });
};

const removeComment = async (comment: CommentItem): Promise<void> => {
    if (!window.confirm('Remover este comentário?')) {
        return;
    }

    await axios.delete(`/api/comments/${comment.id}`);
    router.reload({ only: ['comments'] });
};

const formatDate = (value: string): string =>
    new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
</script>

<template>
    <Head :title="liveStream.name" />

    <div class="flex min-h-screen flex-col bg-slate-950 text-white">
        <PublicHeader />

        <main
            class="mx-auto grid w-full max-w-7xl flex-1 gap-6 px-4 py-7 sm:px-6 lg:grid-cols-[minmax(0,1fr)_23rem] lg:px-8"
        >
            <section class="min-w-0">
                <Link
                    href="/"
                    class="mb-4 inline-flex items-center gap-2 text-sm font-bold text-slate-300 hover:text-white"
                >
                    <ArrowLeft class="size-4" /> Voltar
                </Link>

                <div
                    class="aspect-video overflow-hidden rounded-2xl border border-white/10 bg-black shadow-2xl"
                >
                    <iframe
                        v-if="isLive"
                        :src="liveStream.embed_url"
                        :title="liveStream.name"
                        class="h-full w-full border-0"
                        allow="autoplay; fullscreen; picture-in-picture"
                        allowfullscreen
                        scrolling="no"
                    />
                    <div
                        v-else
                        class="grid h-full place-items-center p-8 text-center text-slate-300"
                    >
                        <span v-if="isEnded"
                            >Esta transmissão foi encerrada.</span
                        >
                        <span v-else>
                            A transmissão está se conectando. Esta página será
                            atualizada automaticamente.
                        </span>
                    </div>
                </div>

                <div class="mt-5 flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-black tracking-[0.18em] text-rose-400 uppercase"
                        >
                            Transmissão ao vivo
                        </p>
                        <h1 class="mt-1 text-2xl font-black sm:text-3xl">
                            {{ liveStream.name }}
                        </h1>
                    </div>
                    <span
                        v-if="isLive"
                        class="rounded-full bg-rose-600 px-3 py-1.5 text-xs font-black uppercase"
                        >Ao vivo</span
                    >
                </div>
            </section>

            <aside
                class="flex min-h-[32rem] flex-col overflow-hidden rounded-2xl border border-white/10 bg-white text-slate-950 lg:max-h-[calc(100vh-8rem)]"
            >
                <header
                    class="flex items-center gap-2 border-b border-slate-200 p-4 font-black"
                >
                    <MessageCircle class="size-5" /> Comentários
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
                                            ? 'Remover destaque'
                                            : 'Destacar'
                                    "
                                    @click="togglePin(comment)"
                                >
                                    <Pin class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 hover:bg-rose-100 hover:text-rose-700"
                                    title="Remover comentário"
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
                        Seja a primeira pessoa a comentar.
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
                            placeholder="Escreva um comentário"
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
                        >Entre para comentar</Link
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

        <PublicFooter />
    </div>
</template>
