<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';
import { show as showEvent } from '@/routes/events';

type Tab = 'posts' | 'materials' | 'media';
const props = defineProps<{
    event: {
        title: string;
        slug: string;
        description_html: string;
        start_time: string;
        end_time: string;
        cover_path: string | null;
        address: Record<string, string | null> | null;
    };
    posts: Array<{
        id: string;
        title: string;
        content_html: string;
        published_at: string;
    }>;
    materials: Array<{
        id: string;
        title: string;
        type: string;
        download_url: string | null;
    }>;
    media: Array<{
        id: string;
        title: string | null;
        type: string;
        url: string;
    }>;
}>();
const { locale, t } = useI18n();
const activeTab = ref<Tab>('posts');
const dateRange = computed(() => {
    const formatter = new Intl.DateTimeFormat(
        locale.value === 'pt' ? 'pt-BR' : 'en-US',
        {
            dateStyle: 'long',
            timeStyle: 'short',
        },
    );

    return `${formatter.format(new Date(props.event.start_time))} – ${formatter.format(new Date(props.event.end_time))}`;
});
const address = computed(() =>
    Object.values(props.event.address ?? {})
        .filter(Boolean)
        .join(', '),
);
</script>

<template>
    <Head :title="`${event.title} - ${t('events.private.title')}`" />
    <div class="flex min-h-screen flex-col bg-slate-50 text-slate-950">
        <PublicHeader active="events" />
        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <Link
                :href="showEvent({ event: event.slug })"
                class="text-sm font-bold text-teal-800"
            >
                {{ t('events.private.back') }}
            </Link>
            <header
                class="mt-4 rounded-3xl bg-gradient-to-r from-slate-900 to-teal-800 p-7 text-white"
            >
                <p class="text-xs font-bold tracking-widest uppercase">
                    {{ t('events.private.title') }}
                </p>
                <h1 class="mt-2 text-3xl font-black">{{ event.title }}</h1>
                <p class="mt-3 text-sm text-teal-50">{{ dateRange }}</p>
                <p v-if="address" class="mt-1 text-sm text-teal-50">
                    {{ address }}
                </p>
            </header>
            <nav
                class="mt-6 flex gap-2 overflow-x-auto"
                :aria-label="t('events.private.sections')"
            >
                <button
                    v-for="tab in ['posts', 'materials', 'media'] as Tab[]"
                    :key="tab"
                    class="rounded-full px-5 py-2 text-sm font-bold"
                    :class="
                        activeTab === tab
                            ? 'bg-teal-800 text-white'
                            : 'bg-white text-slate-700'
                    "
                    @click="activeTab = tab"
                >
                    {{ t(`events.private.${tab}`) }}
                </button>
            </nav>
            <section class="mt-6">
                <div v-if="activeTab === 'posts'" class="space-y-4">
                    <article
                        v-for="post in posts"
                        :key="post.id"
                        class="rounded-2xl border bg-white p-6"
                    >
                        <h2 class="text-xl font-black">{{ post.title }}</h2>
                        <div
                            class="prose prose-slate mt-4 max-w-none"
                            v-html="post.content_html"
                        />
                    </article>
                    <p
                        v-if="!posts.length"
                        class="rounded-2xl bg-white p-6 text-slate-500"
                    >
                        {{ t('events.private.empty') }}
                    </p>
                </div>
                <div
                    v-else-if="activeTab === 'materials'"
                    class="grid gap-4 md:grid-cols-2"
                >
                    <a
                        v-for="material in materials"
                        :key="material.id"
                        :href="material.download_url ?? '#'"
                        target="_blank"
                        class="rounded-2xl border bg-white p-5 font-bold text-teal-800"
                        >{{ material.title }}</a
                    >
                    <p
                        v-if="!materials.length"
                        class="rounded-2xl bg-white p-6 text-slate-500"
                    >
                        {{ t('events.private.empty') }}
                    </p>
                </div>
                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="item in media"
                        :key="item.id"
                        class="overflow-hidden rounded-2xl border bg-white"
                    >
                        <video
                            v-if="item.type === 'video'"
                            :src="item.url"
                            controls
                            class="aspect-video w-full bg-black"
                        />
                        <img
                            v-else
                            :src="item.url"
                            :alt="item.title ?? event.title"
                            class="aspect-video w-full object-cover"
                        />
                        <p v-if="item.title" class="p-3 text-sm font-bold">
                            {{ item.title }}
                        </p>
                    </article>
                    <p
                        v-if="!media.length"
                        class="rounded-2xl bg-white p-6 text-slate-500"
                    >
                        {{ t('events.private.empty') }}
                    </p>
                </div>
            </section>
        </main>
        <PublicFooter show-locale />
    </div>
</template>
