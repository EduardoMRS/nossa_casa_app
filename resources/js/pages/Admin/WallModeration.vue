<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import { useI18n } from '@/lib/i18n';

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
const { locale, t } = useI18n();
async function remove(comment: CommentItem): Promise<void> {
    if (!window.confirm(t('admin.wall.remove_confirm'))) {
        return;
    }

    await axios.delete(`/api/comments/${comment.id}`);
    comments.value = comments.value.filter((item) => item.id !== comment.id);
}
</script>
<template>
    <Head :title="t('admin.wall.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-800 p-7 text-white shadow-xl"
        >
            <p
                class="text-xs font-bold tracking-[0.2em] text-indigo-200 uppercase"
            >
                {{ t('admin.wall.kicker') }}
            </p>
            <h1 class="mt-2 text-3xl font-black">
                {{ t('admin.wall.title') }}
            </h1>
            <p class="mt-2 max-w-xl text-sm text-indigo-100">
                {{ t('admin.wall.description') }}
            </p>
        </header>
        <section class="grid gap-4 sm:grid-cols-3">
            <article
                v-for="(stat, index) in props.stats"
                :key="stat.label"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <p
                    class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                >
                    {{ t(`admin.wall.stats.${index}`) }}
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
                        {{ t('admin.wall.recent_comments') }}
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{
                            t('admin.wall.items_page', {
                                count: comments.length,
                            })
                        }}
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
                                        : t('admin.wall.removed_user')
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
                                    ).toLocaleDateString(
                                        locale === 'pt' ? 'pt-BR' : 'en-US',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <button
                        class="rounded-lg px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50"
                        @click="remove(comment)"
                    >
                        {{ t('admin.common.remove') }}
                    </button>
                </article>
            </div>
            <div v-else class="p-12 text-center text-sm text-slate-500">
                {{ t('admin.wall.empty') }}
            </div>
        </section>
    </main>
</template>
