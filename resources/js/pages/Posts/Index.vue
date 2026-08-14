<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Plus,
    Edit,
    Trash,
    Eye,
    MessageCircle,
    Heart,
    Clock,
    Tags,
} from '@lucide/vue';
import { ref } from 'vue';
import { destroy } from '@/actions/App/Http/Controllers/PostController';
import AdminPageHeader from '@/components/AdminPageHeader.vue';
import CategoryManagerModal from '@/components/CategoryManagerModal.vue';
import type { ManagedCategory } from '@/components/CategoryManagerModal.vue';
import { useI18n } from '@/lib/i18n';
import { create, edit, show } from '@/routes/posts';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface User {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
}

export interface PostMetrics {
    comments_count: number;
    reactions_count: number;
}

export interface Post {
    id: string;
    title: string;
    slug: string;
    content: string;
    published_at: string | null;
    expires_at: string | null;
    author: User;
    metrics: PostMetrics;
    translations: Record<string, string> | any[];
}

const props = defineProps<{
    posts: {
        data: Post[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    categories: ManagedCategory[];
}>();
const { locale, t } = useI18n();
const categoriesOpen = ref(false);

const deletePost = (id: string) => {
    if (confirm(t('posts.shared.delete_confirm'))) {
        router.delete(destroy.url({ post: id }));
    }
};

const formatDate = (dateString: string | null) => {
    if (!dateString) {
        return null;
    }

    return new Date(dateString).toLocaleDateString(
        locale.value === 'pt' ? 'pt-BR' : 'en-US',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        },
    );
};

const getTitle = (post: Post) => {
    if (!Array.isArray(post.translations) && post.translations?.title) {
        return post.translations.title;
    }

    return post.title;
};
</script>

<template>
    <main class="min-h-screen space-y-6 p-4 md:p-8">
        <Head :title="t('posts.index.meta_title')" />

        <div class="mx-auto max-w-7xl space-y-6">
            <AdminPageHeader
                :kicker="t('navigation.communication')"
                :title="t('posts.index.title')"
                :description="t('posts.index.description')"
            >
                <button
                    class="inline-flex items-center gap-2 rounded-lg border border-primary-foreground/30 px-4 py-2.5 text-sm font-bold text-primary-foreground"
                    @click="categoriesOpen = true"
                >
                    <Tags class="size-4" />{{ t('admin.categories.title') }}
                </button>
                <Link
                    :href="create()"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-foreground px-4 py-2.5 text-sm font-bold text-primary"
                >
                    <Plus class="size-4" />{{ t('posts.index.new') }}
                </Link>
            </AdminPageHeader>

            <section
                class="overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead
                            class="border-b border-border bg-muted text-muted-foreground"
                        >
                            <tr>
                                <th class="px-5 py-3 font-semibold">
                                    {{ t('posts.index.content') }}
                                </th>
                                <th class="px-5 py-3 font-semibold">
                                    {{ t('posts.index.author') }}
                                </th>
                                <th class="px-5 py-3 font-semibold">
                                    {{ t('posts.index.engagement') }}
                                </th>
                                <th class="px-5 py-3 font-semibold">
                                    {{ t('posts.index.status') }}
                                </th>
                                <th class="px-5 py-3 text-right font-semibold">
                                    {{ t('posts.index.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="post in props.posts.data"
                                :key="post.id"
                                class="transition hover:bg-muted/60"
                            >
                                <td class="px-5 py-4">
                                    <p
                                        class="max-w-md truncate font-semibold text-card-foreground"
                                    >
                                        {{ getTitle(post) }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        /{{ post.slug }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="grid h-8 w-8 place-items-center rounded-full border border-border bg-muted text-xs font-bold text-foreground"
                                        >
                                            {{ post.author.first_name.charAt(0)
                                            }}{{
                                                post.author.last_name.charAt(0)
                                            }}
                                        </div>
                                        <span class="text-foreground"
                                            >{{ post.author.first_name }}
                                            {{ post.author.last_name }}</span
                                        >
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div
                                        class="flex items-center gap-4 text-muted-foreground"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1 text-xs"
                                        >
                                            <Heart class="h-4 w-4" />
                                            {{ post.metrics.reactions_count }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1 text-xs"
                                        >
                                            <MessageCircle class="h-4 w-4" />
                                            {{ post.metrics.comments_count }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div
                                        class="inline-flex items-center gap-2 text-sm"
                                    >
                                        <Clock
                                            class="h-4 w-4"
                                            :style="{
                                                color: post.published_at
                                                    ? 'var(--primary)'
                                                    : 'var(--muted-foreground)',
                                            }"
                                        />
                                        <span class="text-foreground">{{
                                            formatDate(post.published_at) ??
                                            t('posts.index.draft')
                                        }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Link
                                            :href="show({ post: post.id })"
                                            class="rounded-lg border border-border p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Link>
                                        <Link
                                            :href="edit({ post: post.id })"
                                            class="rounded-lg border border-border p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Link>
                                        <button
                                            @click="deletePost(post.id)"
                                            class="rounded-lg border border-red-200 p-2 text-red-600 transition hover:bg-red-50 dark:border-red-500/40 dark:hover:bg-red-500/10"
                                        >
                                            <Trash class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!props.posts.data.length">
                                <td
                                    colspan="5"
                                    class="px-5 py-12 text-center text-muted-foreground"
                                >
                                    {{ t('posts.index.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <nav class="flex flex-wrap gap-2 border-t border-border p-4">
                <Link
                    v-for="link in props.posts.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'rounded-lg border px-3 py-1.5 text-xs',
                        link.active
                            ? 'border-transparent bg-primary text-white'
                            : 'border-border bg-card text-muted-foreground',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                >
                    <span v-html="link.label" />
                </Link>
            </nav>
        </div>
        <CategoryManagerModal
            :open="categoriesOpen"
            category-type="post"
            :categories="categories"
            @close="categoriesOpen = false"
        />
    </main>
</template>
