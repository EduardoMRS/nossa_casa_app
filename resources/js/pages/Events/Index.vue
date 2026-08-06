<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicHeader from '@/components/PublicHeader.vue';
import { show as showEvent } from '@/routes/events';
import { useI18n } from '@/lib/i18n';

interface EventItem {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    cover_path: string | null;
    start_time: string;
    end_time: string;
    church?: {
        id: string;
        name: string;
        slug: string;
    } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    events: {
        data: EventItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
}>();

const { locale, t } = useI18n();

const formatDate = (date: string) => {
    return new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
};

const cardBackground = (event: EventItem) => {
    if (event.cover_path) {
        return `background-image: linear-gradient(135deg, rgba(8, 22, 40, 0.72), rgba(11, 61, 68, 0.58)), url('${event.cover_path}'); background-size: cover; background-position: center;`;
    }

    return 'background-image: radial-gradient(circle at 15% 20%, #0b3d44 0%, #061824 60%, #050c14 100%);';
};
</script>

<template>
    <Head :title="t('events.index.meta_title')" />

    <div class="min-h-screen bg-[#f5f6f2] text-[#111b2d]">
        <PublicHeader active="events" />

        <main class="mx-auto max-w-7xl px-5 py-8 md:px-8 md:py-10">
            <section class="mb-8 rounded-3xl bg-gradient-to-r from-[#0b3d44] via-[#145362] to-[#0f7a69] p-7 text-white md:p-10">
                <p class="mb-2 text-xs uppercase tracking-[0.2em] text-[#a9f4e3]">{{ t('events.index.hero.kicker') }}</p>
                <h1 class="mb-2 text-3xl font-black [font-family:Manrope,ui-sans-serif] md:text-4xl">{{ t('events.index.hero.title') }}</h1>
                <p class="max-w-3xl text-sm text-[#d8f6ef] md:text-base">
                    {{ t('events.index.hero.description') }}
                </p>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="event in props.events.data"
                    :key="event.id"
                    class="group relative overflow-hidden rounded-2xl p-5 text-white shadow-lg ring-1 ring-black/5"
                    :style="cardBackground(event)"
                >
                    <div class="absolute inset-0 bg-black/20 transition group-hover:bg-black/10" />
                    <div class="relative z-10 flex min-h-[220px] flex-col justify-between">
                        <div>
                            <p class="mb-2 inline-block rounded-full border border-white/30 bg-white/10 px-3 py-1 text-[11px] uppercase tracking-wider">
                                {{ event.church?.name ?? t('events.shared.community') }}
                            </p>
                            <h2 class="mb-2 line-clamp-2 text-xl font-extrabold [font-family:Manrope,ui-sans-serif]">{{ event.title }}</h2>
                            <p class="line-clamp-2 text-sm text-[#daf2ff]">{{ event.description || t('events.index.no_description') }}</p>
                        </div>

                        <div class="mt-5 space-y-2 text-sm">
                            <p><strong>{{ t('events.shared.start') }}:</strong> {{ formatDate(event.start_time) }}</p>
                            <p><strong>{{ t('events.shared.end') }}:</strong> {{ formatDate(event.end_time) }}</p>
                            <Link
                                :href="showEvent({ event: event.slug })"
                                class="inline-flex rounded-full border border-white/35 bg-white/15 px-3 py-1.5 text-xs font-bold text-white"
                            >
                                {{ t('events.index.view_details') }}
                            </Link>
                        </div>
                    </div>
                </article>
            </section>

            <section v-if="props.events.links.length > 3" class="mt-8 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-[#4d5a69]">
                    {{ t('events.index.pagination', { from: props.events.from ?? 0, to: props.events.to ?? 0, total: props.events.total }) }}
                </p>

                <div class="flex flex-wrap gap-2">
                    <template v-for="(link, index) in props.events.links" :key="index">
                        <span
                            v-if="!link.url"
                            class="rounded-lg border border-[#c9d4de] bg-white px-3 py-1.5 text-sm text-[#9aa7b3]"
                            v-html="link.label"
                        />
                        <Link
                            v-else
                            :href="link.url"
                            class="rounded-lg border px-3 py-1.5 text-sm"
                            :class="link.active ? 'border-[#0b3d44] bg-[#0b3d44] text-white' : 'border-[#c9d4de] bg-white text-[#17324a]'"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </section>
        </main>
    </div>
</template>