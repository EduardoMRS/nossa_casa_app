<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Edit,
    Trash,
    Calendar,
    Clock,
    MessageCircle,
    Heart,
} from '@lucide/vue';
import { computed } from 'vue';
import { destroy } from '@/actions/App/Http/Controllers/PostController';
import { useI18n } from '@/lib/i18n';
import { edit, index } from '@/routes/posts';

// --- Interfaces Baseadas no Payload ---
export interface UserDetails {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
}

export interface Media {
    id: string;
    url: string;
    mimetype?: string;
}

export interface Reaction {
    id: string;
    user_id: string;
    content: string;
    type: string;
    user_details: UserDetails;
}

export interface Comment {
    id: string;
    user_id: string;
    content: string;
    created_at: string;
    user_details: UserDetails;
    replies?: Comment[];
    reactions?: Reaction[];
}

export interface Post {
    id: string;
    title: string;
    slug: string;
    content: string;
    published_at: string | null;
    expires_at: string | null;
    category?: string;
    author_details: UserDetails;
    metrics: {
        comments_count: number;
        reactions_count: number;
    };
    medias: Media[];
    translations?: { title?: string };
}

const props = defineProps<{
    post: Post;
    can: {
        edit: boolean;
        delete: boolean;
        comment: boolean;
        react: boolean;
    };
    comments: Comment[];
    reactions: Reaction[];
}>();
const { locale, t } = useI18n();

const page = usePage<{
    branding?: {
        primary_color?: string;
        secondary_color?: string;
        accent_color?: string;
        font_family?: string;
    };
}>();

const branding = computed(() => page.props.branding ?? {});

const paletteStyle = computed(() => ({
    '--brand-primary': branding.value.primary_color ?? '#2f6e79',
    '--brand-secondary': branding.value.secondary_color ?? '#5f7d95',
    '--brand-accent': branding.value.accent_color ?? '#c88b4a',
    '--brand-font': branding.value.font_family ?? 'Manrope, ui-sans-serif',
}));

const formatDate = (dateString: string | null) => {
    if (!dateString) {
        return '';
    }

    return new Date(dateString).toLocaleDateString(
        locale.value === 'pt' ? 'pt-BR' : 'en-US',
        {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        },
    );
};

const getInitials = (user: UserDetails) => {
    return `${user.first_name?.charAt(0) || ''}${user.last_name?.charAt(0) || ''}`;
};

const getTitle = () => props.post.translations?.title || props.post.title;

const deletePost = () => {
    if (confirm(t('posts.shared.delete_confirm'))) {
        router.delete(destroy.url({ post: props.post.id }));
    }
};
</script>

<template>
    <div
        class="min-h-screen bg-[#f4f7fb] p-5 text-slate-800 md:p-8 dark:bg-slate-900 dark:text-slate-100"
        :style="paletteStyle"
    >
        <Head :title="getTitle()" />

        <div class="mx-auto max-w-5xl space-y-6">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <Link
                    :href="index()"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-slate-900 dark:text-slate-200 dark:hover:text-white"
                >
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('posts.show.back') }}
                </Link>

                <div v-if="can.edit || can.delete" class="flex gap-2">
                    <Link
                        v-if="can.edit"
                        :href="edit({ post: post.id })"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <Edit class="h-4 w-4" />
                        {{ t('actions.edit') }}
                    </Link>
                    <button
                        v-if="can.delete"
                        @click="deletePost"
                        class="inline-flex items-center gap-2 rounded-full border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 dark:border-red-500/40 dark:bg-slate-800 dark:hover:bg-red-500/10"
                    >
                        <Trash class="h-4 w-4" />
                        {{ t('actions.delete') }}
                    </button>
                </div>
            </div>

            <article
                class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800/70"
            >
                <div
                    v-if="post.medias && post.medias.length > 0"
                    class="h-64 w-full overflow-hidden border-b border-slate-200 md:h-96 dark:border-slate-700"
                >
                    <img
                        :src="post.medias[0].url"
                        class="h-full w-full object-cover"
                        :alt="t('posts.show.cover_alt')"
                    />
                </div>

                <div class="p-6 md:p-10">
                    <header class="mb-7">
                        <div class="mb-5 flex flex-wrap items-center gap-3">
                            <span
                                v-if="post.category"
                                class="rounded-full border px-3 py-1 text-xs font-bold tracking-wide uppercase"
                                :style="{
                                    borderColor: 'var(--brand-secondary)',
                                    color: 'var(--brand-primary)',
                                    backgroundColor:
                                        'color-mix(in srgb, var(--brand-primary) 8%, white)',
                                }"
                            >
                                {{ post.category }}
                            </span>
                            <div
                                class="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-300"
                            >
                                <Calendar class="h-4 w-4" />
                                <span>{{ formatDate(post.published_at) }}</span>
                            </div>
                            <div
                                v-if="post.expires_at"
                                class="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-300"
                            >
                                <Clock class="h-4 w-4" />
                                <span>{{
                                    t('posts.show.expires_at', {
                                        date: formatDate(post.expires_at),
                                    })
                                }}</span>
                            </div>
                        </div>

                        <h1
                            class="mb-5 text-3xl leading-tight font-black text-slate-900 md:text-5xl dark:text-slate-100"
                            :style="{ fontFamily: 'var(--brand-font)' }"
                        >
                            {{ getTitle() }}
                        </h1>

                        <div class="flex items-center gap-3">
                            <div
                                class="grid h-10 w-10 place-items-center rounded-full border border-slate-300 bg-slate-100 text-sm font-bold text-slate-700 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                {{ getInitials(post.author_details) }}
                            </div>
                            <div class="flex flex-col">
                                <span
                                    class="font-medium text-slate-800 dark:text-slate-100"
                                    >{{ post.author_details.first_name }}
                                    {{ post.author_details.last_name }}</span
                                >
                                <span
                                    class="text-xs text-slate-500 dark:text-slate-300"
                                    >{{ post.author_details.email }}</span
                                >
                            </div>
                        </div>
                    </header>

                    <div
                        class="prose dark:prose-invert max-w-none text-base leading-relaxed whitespace-pre-wrap text-slate-700 md:text-lg dark:text-slate-200"
                    >
                        {{ post.content }}
                    </div>
                </div>
            </article>

            <section class="grid gap-4 md:grid-cols-2">
                <article
                    class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800/70"
                >
                    <p
                        class="text-xs tracking-[0.14em] text-slate-500 uppercase dark:text-slate-300"
                    >
                        {{ t('posts.show.engagement') }}
                    </p>
                    <div
                        class="mt-3 flex items-center gap-6 text-slate-700 dark:text-slate-100"
                    >
                        <span
                            class="inline-flex items-center gap-2 text-sm font-semibold"
                        >
                            <Heart class="h-4 w-4" />
                            {{
                                t('posts.show.likes', {
                                    count: post.metrics.reactions_count,
                                })
                            }}
                        </span>
                        <span
                            class="inline-flex items-center gap-2 text-sm font-semibold"
                        >
                            <MessageCircle class="h-4 w-4" />
                            {{
                                t('posts.show.comments', {
                                    count: post.metrics.comments_count,
                                })
                            }}
                        </span>
                    </div>
                </article>

                <article
                    class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800/70"
                >
                    <p
                        class="text-xs tracking-[0.14em] text-slate-500 uppercase dark:text-slate-300"
                    >
                        {{ t('posts.show.summary') }}
                    </p>
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-200">
                        {{
                            t('posts.show.summary_text', {
                                comments: comments.length,
                                reactions: reactions.length,
                            })
                        }}
                    </p>
                </article>
            </section>

            <section
                v-if="comments.length"
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800/70"
            >
                <h2
                    class="text-lg font-black text-slate-900 dark:text-slate-100"
                    :style="{ fontFamily: 'var(--brand-font)' }"
                >
                    {{ t('posts.show.recent_comments') }}
                </h2>
                <div class="mt-4 space-y-3">
                    <article
                        v-for="comment in comments.slice(0, 5)"
                        :key="comment.id"
                        class="rounded-xl border border-slate-200/80 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/40"
                    >
                        <p
                            class="text-sm font-semibold text-slate-800 dark:text-slate-100"
                        >
                            {{ comment.user_details.first_name }}
                            {{ comment.user_details.last_name }}
                        </p>
                        <p
                            class="mt-1 text-sm text-slate-600 dark:text-slate-200"
                        >
                            {{ comment.content }}
                        </p>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>
