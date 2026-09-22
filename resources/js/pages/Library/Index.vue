<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Download, Edit, ExternalLink, Share2 } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { usePublicTemplate } from '@/composables/usePublicTemplate';
import { useI18n } from '@/lib/i18n';
import { index as adminLibraryIndex } from '@/routes/admin/libraryVerse';
type Item = {
    id: string;
    kind: 'resource';
    title: string;
    description: string | null;
    type: string;
    file_url: string | null;
    href: string | null;
};
defineProps<{
    items: {
        data: Item[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    bible: {
        title: string;
        description: string;
        href: string;
        versions_count: number;
    } | null;
}>();
const { t } = useI18n();
const page = usePage<{ permissions?: { manageLibrary?: boolean } }>();
const canManageLibrary = computed(
    () => page.props.permissions?.manageLibrary === true,
);
const publicTemplate = usePublicTemplate('library');
const shareLibraryItem = async (
    title: string,
    text: string,
    url: string,
): Promise<void> => {
    const shareData = { title, text, url };

    try {
        if (navigator.share) {
            await navigator.share(shareData);
        } else if (navigator.clipboard) {
            await navigator.clipboard.writeText(shareData.url);
            toast.success(t('share.copied'));
        } else {
            toast.error(t('share.unavailable'));
        }
    } catch (error) {
        if ((error as DOMException).name !== 'AbortError') {
            toast.error(t('share.unavailable'));
        }
    }
};
</script>
<template>
    <div
        class="public-template-page flex min-h-screen flex-col bg-[#f8fafc] text-slate-950"
        :data-public-template="publicTemplate"
        :style="{
            backgroundColor: 'var(--church-surface, #f8fafc)',
            fontFamily: 'var(--church-font, Manrope, ui-sans-serif)',
        }"
    >
        <Head :title="t('library.title')" />
        <PublicHeader active="library" />
        <main
            class="mx-auto w-full max-w-6xl flex-1 space-y-6 px-3 py-6 sm:space-y-7 sm:px-6 sm:py-8 lg:px-8"
        >
            <header class="flex flex-col items-center gap-3 text-center">
                <p
                    class="font-mono text-[10px] font-bold tracking-[0.2em] text-indigo-500 uppercase"
                >
                    {{ t('library.kicker') }}
                </p>
                <h1 class="mt-2 text-3xl font-black">
                    {{ t('library.title') }}
                </h1>
                <p
                    class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-500"
                >
                    {{ t('library.description') }}
                </p>
                <Link
                    v-if="canManageLibrary"
                    :href="
                        adminLibraryIndex({ query: { return_to: page.url } })
                    "
                    class="inline-flex size-10 items-center justify-center rounded-lg bg-indigo-700 text-white"
                    :title="t('admin.library.title')"
                    :aria-label="t('admin.library.title')"
                >
                    <Edit class="size-4" /><span class="sr-only">{{
                        t('admin.library.title')
                    }}</span>
                </Link>
            </header>
            <section class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-if="bible"
                    class="flex flex-col rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-950 to-indigo-800 p-5 text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div
                        class="flex aspect-[3/2] w-full flex-col justify-between rounded-xl border border-white/20 bg-white/10 p-4"
                    >
                        <BookOpen class="size-8" />
                        <p class="text-xs font-bold text-indigo-100">
                            {{
                                t('library.bible.versions_available', {
                                    count: bible.versions_count,
                                })
                            }}
                        </p>
                    </div>
                    <p
                        class="mt-4 text-[10px] font-bold text-amber-300 uppercase"
                    >
                        {{ t('library.bible.type') }}
                    </p>
                    <h2 class="mt-1 text-lg font-black">
                        {{ bible.title }}
                    </h2>
                    <p class="mt-2 flex-1 text-sm leading-6 text-indigo-100">
                        {{ bible.description }}
                    </p>
                    <Link
                        :href="bible.href"
                        class="mt-4 inline-flex items-center gap-2 self-end rounded-lg bg-white px-3 py-2 text-xs font-bold text-indigo-950"
                    >
                        <BookOpen class="size-4" />{{ t('library.bible.read') }}
                    </Link>
                    <button
                        type="button"
                        class="mt-3 inline-flex size-10 items-center justify-center self-end rounded-lg border border-white/40 text-white"
                        :title="t('share.button')"
                        :aria-label="t('share.button')"
                        @click="
                            shareLibraryItem(
                                bible.title,
                                bible.description,
                                bible.href,
                            )
                        "
                    >
                        <Share2 class="size-4" /><span class="sr-only">{{
                            t('share.button')
                        }}</span>
                    </button>
                </article>
                <article
                    v-for="item in items.data"
                    :key="item.id"
                    class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md"
                >
                    <div
                        class="flex aspect-[3/2] w-full flex-col justify-between rounded-xl border border-l-4 border-indigo-200 border-l-indigo-600 bg-gradient-to-br from-indigo-50 to-indigo-100/60 p-4 text-indigo-700"
                    >
                        <BookOpen class="size-5" />
                    </div>
                    <p
                        class="mt-4 text-[10px] font-bold text-indigo-600 uppercase"
                    >
                        {{ item.type }}
                    </p>
                    <h2 class="mt-1 text-lg font-black">{{ item.title }}</h2>
                    <p class="mt-2 flex-1 text-sm leading-6 text-slate-500">
                        {{ item.description }}
                    </p>
                    <a
                        v-if="item.file_url"
                        :href="item.file_url"
                        target="_blank"
                        rel="noopener"
                        class="mt-4 inline-flex items-center gap-2 self-end rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white"
                        ><ExternalLink
                            v-if="item.file_url.startsWith('http')"
                            class="size-4"
                        /><Download v-else class="size-4" />{{
                            t('library.open')
                        }}</a
                    >
                    <button
                        type="button"
                        class="mt-3 inline-flex size-10 items-center justify-center self-end rounded-lg border border-indigo-200 text-indigo-700"
                        :title="t('share.button')"
                        :aria-label="t('share.button')"
                        @click="
                            shareLibraryItem(
                                item.title,
                                item.description ?? item.title,
                                item.file_url ?? window.location.href,
                            )
                        "
                    >
                        <Share2 class="size-4" /><span class="sr-only">{{
                            t('share.button')
                        }}</span>
                    </button>
                </article>
            </section>
            <p
                v-if="!items.data.length && !bible"
                class="rounded-2xl border border-dashed p-12 text-center text-slate-500"
            >
                {{ t('library.empty') }}
            </p>
            <nav class="flex flex-wrap gap-2">
                <Link
                    v-for="link in items.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'rounded-lg border px-3 py-2 text-xs',
                        link.active ? 'bg-indigo-600 text-white' : 'bg-white',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                    ><span v-html="link.label"
                /></Link>
            </nav>
        </main>
        <PublicFooter show-locale />
    </div>
</template>
