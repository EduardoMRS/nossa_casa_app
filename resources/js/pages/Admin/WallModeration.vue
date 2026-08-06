<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

type CommentItem = {
    id: string;
    content: string;
    commentable_type: string;
    created_at: string;
    user?: { first_name: string; last_name: string } | null;
};
const props = defineProps<{
    comments: { data: CommentItem[] };
    stats: Array<{ label: string; value: number }>;
}>();
const comments = ref([...props.comments.data]);
async function remove(comment: CommentItem): Promise<void> {
    if (!window.confirm('Remover este comentário?')) {
        return;
    }

    await axios.delete(`/api/comments/${comment.id}`);
    comments.value = comments.value.filter((item) => item.id !== comment.id);
}
</script>
<template>
    <Head title="Moderar mural" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-800 p-7 text-white shadow-xl"
        >
            <p
                class="text-xs font-bold tracking-[0.2em] text-indigo-200 uppercase"
            >
                Comunicação
            </p>
            <h1 class="mt-2 text-3xl font-black">Moderar mural</h1>
            <p class="mt-2 max-w-xl text-sm text-indigo-100">
                Revise conversas recentes e mantenha o ambiente acolhedor para a
                comunidade.
            </p>
        </header>
        <section class="grid gap-4 sm:grid-cols-3">
            <article
                v-for="stat in props.stats"
                :key="stat.label"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <p
                    class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                >
                    {{ stat.label }}
                </p>
                <p class="mt-2 text-3xl font-black text-slate-900">
                    {{ stat.value }}
                </p>
            </article>
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <header
                class="flex items-center justify-between border-b px-6 py-4"
            >
                <div>
                    <h2 class="font-black text-slate-900">
                        Comentários recentes
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{ comments.length }} itens nesta página
                    </p>
                </div>
            </header>
            <div v-if="comments.length" class="divide-y">
                <article
                    v-for="comment in comments"
                    :key="comment.id"
                    class="flex items-start justify-between gap-5 px-6 py-5"
                >
                    <div class="flex min-w-0 gap-3">
                        <div
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-indigo-100 text-sm font-black text-indigo-700"
                        >
                            {{ comment.user?.first_name?.charAt(0) ?? '?' }}
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-900">
                                {{
                                    comment.user
                                        ? `${comment.user.first_name} ${comment.user.last_name}`
                                        : 'Usuário removido'
                                }}
                            </p>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ comment.content }}
                            </p>
                            <p
                                class="mt-2 text-[11px] font-semibold tracking-wide text-slate-400 uppercase"
                            >
                                {{
                                    comment.commentable_type?.split('\\').pop()
                                }}
                                ·
                                {{
                                    new Date(
                                        comment.created_at,
                                    ).toLocaleDateString('pt-BR')
                                }}
                            </p>
                        </div>
                    </div>
                    <button
                        class="rounded-lg px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50"
                        @click="remove(comment)"
                    >
                        Remover
                    </button>
                </article>
            </div>
            <div v-else class="p-12 text-center text-sm text-slate-500">
                Nenhum comentário para revisar.
            </div>
        </section>
    </main>
</template>
