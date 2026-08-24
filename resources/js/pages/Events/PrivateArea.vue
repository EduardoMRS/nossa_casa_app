<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    Download,
    FileText,
    Image,
    LockKeyhole,
    MapPin,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { usePublicTemplate } from '@/composables/usePublicTemplate';
import { useI18n } from '@/lib/i18n';
import { index as eventsIndex, show as showEvent } from '@/routes/events';

type Tab = 'posts' | 'materials' | 'media';

const props = defineProps<{
    event: {
        title: string;
        slug: string;
        description_html: string;
        start_time: string;
        end_time: string;
        cover_path: string | null;
        church: { name: string; slug: string } | null;
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
        mimetype?: string | null;
        size?: number | null;
    }>;
    media: Array<{
        id: string;
        title: string | null;
        type: string;
        url: string;
    }>;
}>();

const { locale, t } = useI18n();
const publicTemplate = usePublicTemplate('events_show');
const activeTab = ref<Tab>('posts');

const dateRange = computed(() => {
    const formatter = new Intl.DateTimeFormat(
        locale.value === 'pt' ? 'pt-BR' : 'en-US',
        { dateStyle: 'long', timeStyle: 'short' },
    );

    return `${formatter.format(new Date(props.event.start_time))} – ${formatter.format(new Date(props.event.end_time))}`;
});

const address = computed(() =>
    [
        props.event.address?.street,
        props.event.address?.number,
        props.event.address?.neighborhood,
        props.event.address?.city,
        props.event.address?.state,
    ]
        .filter(Boolean)
        .join(', '),
);

const coverStyle = computed(() => {
    if (props.event.cover_path) {
        return `background-image: linear-gradient(120deg, rgba(15, 23, 42, 0.9), rgba(15, 118, 110, 0.72)), url('${props.event.cover_path}'); background-size: cover; background-position: center;`;
    }

    return 'background-image: linear-gradient(120deg, #0f172a 0%, #115e59 55%, #0f766e 100%);';
});

const formatSize = (size?: number | null): string => {
    if (!size) {
        return '';
    }

    const unit =
        size < 1024 ? 'byte' : size < 1024 * 1024 ? 'kilobyte' : 'megabyte';
    const value =
        size < 1024
            ? size
            : size < 1024 * 1024
              ? size / 1024
              : size / (1024 * 1024);

    return new Intl.NumberFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
        style: 'unit',
        unit,
        unitDisplay: 'narrow',
        maximumFractionDigits: 1,
    }).format(value);
};

const formatPublishedAt = (value: string): string =>
    new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
        dateStyle: 'medium',
    }).format(new Date(value));
</script>

<template>
    <Head :title="`${event.title} - ${t('events.private.title')}`" />
    <div
        class="public-template-page flex min-h-screen flex-col bg-[var(--church-surface,#f8fafc)] text-slate-950"
        :data-public-template="publicTemplate"
    >
        <PublicHeader active="events" />

        <main
            class="mx-auto w-full max-w-6xl flex-1 px-3 py-5 sm:px-6 sm:py-8 lg:px-8"
        >
            <nav
                class="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-500"
                :aria-label="t('events.private.navigation')"
            >
                <Link
                    :href="eventsIndex()"
                    class="inline-flex items-center gap-1 transition hover:text-teal-700"
                >
                    <ChevronLeft class="size-4" />
                    {{ t('events.private.back_to_events') }}
                </Link>
                <span aria-hidden="true">/</span>
                <Link
                    :href="showEvent({ event: event.slug })"
                    class="truncate transition hover:text-teal-700"
                >
                    {{ t('events.private.back') }}
                </Link>
            </nav>

            <header
                class="relative mt-5 overflow-hidden rounded-2xl p-5 text-white shadow-lg sm:rounded-3xl sm:p-8 md:p-10"
                :style="coverStyle"
            >
                <div class="relative max-w-3xl">
                    <div
                        class="flex flex-wrap items-center gap-2 text-xs font-black tracking-[0.16em] text-teal-100 uppercase"
                    >
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1.5"
                        >
                            <LockKeyhole class="size-3.5" />
                            {{ t('events.private.title') }}
                        </span>
                        <span v-if="event.church">{{ event.church.name }}</span>
                    </div>
                    <h1
                        class="mt-4 text-3xl font-black tracking-tight sm:text-4xl"
                    >
                        {{ event.title }}
                    </h1>
                    <p class="mt-3 text-sm leading-6 text-teal-50 sm:text-base">
                        {{ t('events.private.participant_intro') }}
                    </p>
                    <div
                        class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-xs font-semibold text-white/90"
                    >
                        <span class="inline-flex items-center gap-2"
                            ><CalendarDays class="size-4" />
                            {{ dateRange }}</span
                        >
                        <span
                            v-if="address"
                            class="inline-flex items-center gap-2"
                            ><MapPin class="size-4" /> {{ address }}</span
                        >
                    </div>
                </div>
            </header>

            <section class="mt-6 grid gap-5 lg:grid-cols-[1.4fr_0.6fr]">
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:rounded-3xl sm:p-7"
                >
                    <p
                        class="text-xs font-black tracking-[0.16em] text-teal-700 uppercase"
                    >
                        {{ t('events.private.details') }}
                    </p>
                    <div
                        class="prose prose-slate mt-3 max-w-none"
                        v-html="event.description_html"
                    />
                </article>
                <aside
                    class="rounded-2xl border border-teal-100 bg-teal-50/70 p-5 sm:rounded-3xl sm:p-6"
                >
                    <LockKeyhole class="size-6 text-teal-700" />
                    <h2 class="mt-3 text-lg font-black text-slate-900">
                        {{ t('events.private.access_title') }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ t('events.private.access_description') }}
                    </p>
                    <Link
                        :href="showEvent({ event: event.slug })"
                        class="mt-5 inline-flex rounded-full bg-teal-700 px-4 py-2.5 text-xs font-black text-white transition hover:bg-teal-800"
                    >
                        {{ t('events.private.back') }}
                    </Link>
                </aside>
            </section>

            <nav
                class="mt-8 flex gap-2 overflow-x-auto border-b border-slate-200 pb-2"
                :aria-label="t('events.private.sections')"
            >
                <button
                    v-for="tab in ['posts', 'materials', 'media'] as Tab[]"
                    :key="tab"
                    class="shrink-0 rounded-full px-4 py-2 text-xs font-black transition"
                    :class="
                        activeTab === tab
                            ? 'bg-teal-700 text-white'
                            : 'bg-white text-slate-600 hover:bg-teal-50 hover:text-teal-800'
                    "
                    @click="activeTab = tab"
                >
                    {{ t(`events.private.${tab}`) }}
                </button>
            </nav>

            <section class="mt-5 pb-8">
                <div v-if="activeTab === 'posts'" class="space-y-4">
                    <article
                        v-for="post in posts"
                        :key="post.id"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:rounded-3xl sm:p-7"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-xl font-black text-slate-900">
                                {{ post.title }}
                            </h2>
                            <span
                                class="shrink-0 text-[10px] font-bold text-slate-400"
                                >{{
                                    formatPublishedAt(post.published_at)
                                }}</span
                            >
                        </div>
                        <div
                            class="prose prose-slate mt-4 max-w-none"
                            v-html="post.content_html"
                        />
                    </article>
                    <p
                        v-if="!posts.length"
                        class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500"
                    >
                        {{ t('events.private.empty') }}
                    </p>
                </div>

                <div
                    v-else-if="activeTab === 'materials'"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <a
                        v-for="material in materials"
                        :key="material.id"
                        :href="material.download_url ?? undefined"
                        target="_blank"
                        rel="noreferrer"
                        class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-teal-300 hover:shadow-md"
                        :class="
                            !material.download_url &&
                            'pointer-events-none opacity-60'
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <FileText class="size-6 text-teal-700" />
                            <Download
                                v-if="material.download_url"
                                class="size-4 text-slate-400 transition group-hover:text-teal-700"
                            />
                        </div>
                        <h2 class="mt-4 font-black text-slate-900">
                            {{ material.title }}
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ material.mimetype || material.type
                            }}<span v-if="formatSize(material.size)">
                                · {{ formatSize(material.size) }}</span
                            >
                        </p>
                        <span
                            v-if="material.download_url"
                            class="mt-4 inline-block text-xs font-black text-teal-700"
                            >{{ t('events.private.download') }}</span
                        >
                    </a>
                    <p
                        v-if="!materials.length"
                        class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 sm:col-span-2 lg:col-span-3"
                    >
                        {{ t('events.private.empty') }}
                    </p>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="item in media"
                        :key="item.id"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
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
                        <p
                            v-if="item.title"
                            class="flex items-center gap-2 p-4 text-sm font-bold text-slate-800"
                        >
                            <Image class="size-4 text-teal-700" />
                            {{ item.title }}
                        </p>
                    </article>
                    <p
                        v-if="!media.length"
                        class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 sm:col-span-2 lg:col-span-3"
                    >
                        {{ t('events.private.empty') }}
                    </p>
                </div>
            </section>
        </main>
        <PublicFooter show-locale />
    </div>
</template>
