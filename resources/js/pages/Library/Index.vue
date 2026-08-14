<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Download, ExternalLink } from '@lucide/vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';
type Item = {
    id: string;
    title: string;
    description: string | null;
    type: string;
    file_path: string | null;
    file_url: string | null;
};
defineProps<{
    items: {
        data: Item[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();
const { t } = useI18n();
</script>
<template>
    <div
        class="flex min-h-screen flex-col bg-[#f8fafc] text-slate-950"
        :style="{
            backgroundColor: 'var(--church-surface, #f8fafc)',
            fontFamily: 'var(--church-font, Manrope, ui-sans-serif)',
        }"
    >
        <Head :title="t('library.title')" />
        <PublicHeader active="library" />
        <main
            class="mx-auto w-full max-w-7xl flex-1 space-y-7 px-4 py-8 sm:px-6 lg:px-8"
        >
            <header class="text-center">
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
            </header>
            <section class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
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
                </article>
            </section>
            <p
                v-if="!items.data.length"
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
        <PublicFooter />
    </div>
</template>
