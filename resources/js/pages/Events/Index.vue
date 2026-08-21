<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, Clock3 } from '@lucide/vue';
import { computed, ref } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { usePublicTemplate } from '@/composables/usePublicTemplate';
import { useI18n } from '@/lib/i18n';
import { show as showEvent } from '@/routes/events';

interface EventItem {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    cover_path: string | null;
    start_time: string;
    end_time: string;
    church?: { id: string; name: string; slug: string } | null;
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
const publicTemplate = usePublicTemplate('events_index');
const activeTab = ref<'future' | 'ongoing' | 'past'>('future');

const filteredEvents = computed(() => {
    const now = Date.now();

    return props.events.data.filter((event) => {
        const startsAt = new Date(event.start_time).getTime();
        const endsAt = new Date(event.end_time).getTime();

        if (activeTab.value === 'past') {
            return endsAt < now;
        }

        if (activeTab.value === 'ongoing') {
            return startsAt <= now && endsAt >= now;
        }

        return startsAt > now;
    });
});

const formatDate = (date: string): string =>
    new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(date));
</script>

<template>
    <Head :title="t('events.index.meta_title')" />
    <div
        class="public-template-page flex min-h-screen flex-col bg-[#f8fafc] text-slate-950"
        :data-public-template="publicTemplate"
        :style="{
            backgroundColor: 'var(--church-surface, #f8fafc)',
            fontFamily: 'var(--church-font, Manrope, ui-sans-serif)',
        }"
    >
        <PublicHeader active="events" />
        <main
            class="mx-auto w-full max-w-6xl flex-1 px-3 py-6 sm:px-6 sm:py-8 lg:px-8"
        >
            <header class="max-w-2xl space-y-1">
                <h1 class="text-2xl font-black tracking-tight md:text-3xl">
                    {{ t('events.index.hero.title') }}
                </h1>
                <p class="text-sm leading-6 text-slate-500">
                    {{ t('events.index.hero.description') }}
                </p>
            </header>

            <div
                class="mt-6 flex gap-5 overflow-x-auto border-b border-slate-200"
            >
                <button
                    v-for="tab in ['future', 'ongoing', 'past'] as const"
                    :key="tab"
                    class="shrink-0 border-b-2 px-0.5 pb-3 text-xs font-extrabold tracking-wide uppercase transition"
                    :class="
                        activeTab === tab
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-slate-400 hover:text-slate-600'
                    "
                    @click="activeTab = tab"
                >
                    {{ t(`events.index.tabs.${tab}`) }}
                </button>
            </div>

            <section
                v-if="filteredEvents.length"
                class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    v-for="event in filteredEvents"
                    :key="event.id"
                    :href="showEvent({ event: event.slug })"
                    class="group flex min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md sm:block"
                >
                    <div
                        class="relative h-32 w-28 shrink-0 overflow-hidden bg-indigo-50 min-[380px]:w-32 sm:h-44 sm:w-full"
                    >
                        <img
                            v-if="event.cover_path"
                            :src="event.cover_path"
                            :alt="event.title"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                        />
                        <div
                            v-else
                            class="grid h-full place-items-center bg-gradient-to-br from-indigo-950 to-indigo-700 text-indigo-200"
                        >
                            <CalendarDays class="size-9" />
                        </div>
                    </div>
                    <div
                        class="flex min-w-0 flex-1 flex-col justify-between p-4"
                    >
                        <div>
                            <p
                                class="text-[10px] font-extrabold tracking-wider text-indigo-500 uppercase"
                            >
                                {{
                                    event.church?.name ??
                                    t('events.shared.community')
                                }}
                            </p>
                            <h2
                                class="mt-1 line-clamp-2 text-sm font-extrabold text-slate-950 transition group-hover:text-indigo-600"
                            >
                                {{ event.title }}
                            </h2>
                            <p
                                class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500"
                            >
                                {{
                                    event.description ||
                                    t('events.index.no_description')
                                }}
                            </p>
                        </div>
                        <div
                            class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 font-mono text-[10px] text-slate-400"
                        >
                            <span class="flex items-center gap-1"
                                ><Clock3 class="size-3.5" />
                                {{ formatDate(event.start_time) }}</span
                            >
                            <span
                                class="flex items-center gap-0.5 font-bold text-indigo-600"
                                >{{ t('events.index.view_details') }}
                                <ChevronRight class="size-3.5"
                            /></span>
                        </div>
                    </div>
                </Link>
            </section>

            <section
                v-else
                class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center"
            >
                <CalendarDays class="mx-auto size-8 text-slate-300" />
                <p class="mt-3 text-sm font-bold text-slate-700">
                    {{ t('events.index.empty') }}
                </p>
            </section>

            <nav
                v-if="props.events.links.length > 3"
                class="mt-8 flex flex-wrap gap-2"
            >
                <Link
                    v-for="link in props.events.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'rounded-lg border px-3 py-1.5 text-xs',
                        link.active
                            ? 'border-indigo-600 bg-indigo-600 text-white'
                            : 'border-slate-200 bg-white text-slate-600',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                    ><span v-html="link.label"
                /></Link>
            </nav>
        </main>
        <PublicFooter show-locale />
    </div>
</template>
