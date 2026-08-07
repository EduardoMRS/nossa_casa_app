<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MessageSquare, Search, Trash2 } from '@lucide/vue';
import axios from 'axios';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from '@/lib/i18n';
type CommentItem = {
    id: string;
    content: string;
    commentable_type: string;
    commentable_title: string;
    created_at: string;
    user?: { first_name: string; last_name: string } | null;
};
const props = defineProps<{
    comments: { data: CommentItem[] };
    stats: Array<{ label: string; value: number }>;
}>();
const comments = ref([...props.comments.data]);
const query = ref('');
const type = ref('');
const { locale, t } = useI18n();
const filtered = computed(() =>
    comments.value.filter(
        (comment) =>
            (!type.value || comment.commentable_type === type.value) &&
            `${comment.content} ${comment.commentable_title} ${comment.user?.first_name ?? ''} ${comment.user?.last_name ?? ''}`
                .toLowerCase()
                .includes(query.value.toLowerCase()),
    ),
);
async function remove(comment: CommentItem): Promise<void> {
    if (!window.confirm(t('admin.wall.remove_confirm'))) {
        return;
    }

    await axios.delete(`/api/comments/${comment.id}`);
    comments.value = comments.value.filter((item) => item.id !== comment.id);
    toast.success(t('admin.wall.removed'));
}
</script>
<template>
    <Head :title="t('admin.wall.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white shadow-sm"
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
                v-for="(stat, index) in stats"
                :key="stat.label"
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <p
                    class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                >
                    {{ t(`admin.wall.stats.${index}`) }}
                </p>
                <p class="mt-2 text-3xl font-black">{{ stat.value }}</p>
            </article>
        </section>
        <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
            <header
                class="flex flex-col gap-4 border-b bg-slate-50 px-5 py-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h2 class="flex items-center gap-2 font-black">
                        <MessageSquare class="size-4 text-indigo-600" />{{
                            t('admin.wall.recent_comments')
                        }}
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{
                            t('admin.wall.items_page', {
                                count: filtered.length,
                            })
                        }}
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label class="relative"
                        ><Search
                            class="absolute top-2.5 left-3 size-4 text-slate-400" /><input
                            v-model="query"
                            class="w-full rounded-lg border-slate-300 pl-9 text-sm"
                            :placeholder="t('admin.wall.search')" /></label
                    ><select
                        v-model="type"
                        class="rounded-lg border-slate-300 text-sm"
                    >
                        <option value="">
                            {{ t('admin.wall.all_types') }}
                        </option>
                        <option value="post">
                            {{ t('posts.index.title') }}
                        </option>
                        <option value="event">{{ t('nav.events') }}</option>
                        <option value="media">{{ t('nav.gallery') }}</option>
                    </select>
                </div>
            </header>
            <div v-if="filtered.length" class="divide-y">
                <article
                    v-for="comment in filtered"
                    :key="comment.id"
                    class="grid gap-4 px-5 py-5 md:grid-cols-[12rem_minmax(0,1fr)_auto]"
                >
                    <div>
                        <p class="text-sm font-bold">
                            {{
                                comment.user
                                    ? `${comment.user.first_name} ${comment.user.last_name}`
                                    : t('admin.wall.removed_user')
                            }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{
                                new Date(comment.created_at).toLocaleString(
                                    locale === 'pt' ? 'pt-BR' : 'en-US',
                                )
                            }}
                        </p>
                    </div>
                    <div>
                        <span
                            class="rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-black text-indigo-700 uppercase"
                            >{{ comment.commentable_type }}</span
                        >
                        <p class="mt-2 text-xs font-bold text-slate-500">
                            {{ comment.commentable_title }}
                        </p>
                        <p class="mt-2 text-sm leading-6 text-slate-700">
                            {{ comment.content }}
                        </p>
                    </div>
                    <button
                        class="inline-flex h-fit items-center gap-2 rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50"
                        @click="remove(comment)"
                    >
                        <Trash2 class="size-4" />{{ t('admin.common.remove') }}
                    </button>
                </article>
            </div>
            <p v-else class="p-12 text-center text-sm text-slate-500">
                {{ t('admin.wall.empty') }}
            </p>
        </section>
    </main>
</template>
