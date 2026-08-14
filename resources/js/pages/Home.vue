<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock3,
    HeartHandshake,
    Mail,
    MapPin,
    Phone,
    Send,
} from '@lucide/vue';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';
import { index as eventsIndex, show as eventsShow } from '@/routes/events';
import { index as galleryIndex } from '@/routes/gallery';
import { show as publicPostShow } from '@/routes/posts/public';

type EventCard = {
    id: string;
    title: string;
    slug: string;
    excerpt: string;
    cover_path: string | null;
    start_time: string;
    church?: {
        id: string;
        name: string;
        slug: string;
    } | null;
};

type PostCard = {
    id: string;
    title: string;
    slug: string;
    excerpt: string;
    published_at: string | null;
};

type CalendarEvent = {
    id: string;
    title: string;
    slug: string;
    start_time: string;
    end_time: string;
};

type WeeklySchedule = {
    title: string;
    day_of_week: number;
    start_time: string;
    end_time: string;
};

type BrandingData = {
    brand_name?: string;
    tagline?: string;
    banner_title?: string;
    banner_subtitle?: string;
    logo_url?: string;
    address?: string;
    map_embed?: string;
    contact_phone?: string;
    contact_email?: string;
    weekly_schedule?: WeeklySchedule[];
};

type CalendarItem = {
    id: string;
    title: string;
    start_time: string;
    end_time: string;
    slug?: string;
    recurring: boolean;
};

const props = defineProps<{
    stats: {
        events: number;
        gallery: number;
        posts: number;
    };
    featuredEvents: EventCard[];
    latestPosts: PostCard[];
    calendarEvents: CalendarEvent[];
}>();

const { locale, t } = useI18n();
const page = usePage();
const branding = computed(() => page.props.branding as BrandingData);
const nextEvent = computed(() => props.featuredEvents[0] ?? null);
const brandInitials = computed(() =>
    (branding.value.brand_name || 'Nossa Casa')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join(''),
);
const weeklySchedule = computed<WeeklySchedule[]>(() =>
    Array.isArray(branding.value.weekly_schedule)
        ? branding.value.weekly_schedule
        : [],
);
const scheduleGroups = computed(() => {
    const groups = new Map<string, WeeklySchedule[]>();

    weeklySchedule.value.forEach((schedule) => {
        groups.set(schedule.title, [
            ...(groups.get(schedule.title) ?? []),
            schedule,
        ]);
    });

    return Array.from(groups, ([title, schedules]) => ({
        title,
        schedules: schedules.sort(
            (first, second) =>
                first.day_of_week - second.day_of_week ||
                first.start_time.localeCompare(second.start_time),
        ),
    }));
});
const authenticatedUser = computed(() => page.props.auth?.user);
const prayerContent = ref('');
const prayerAnonymous = ref(false);
const prayerProcessing = ref(false);
const prayerStatus = ref<'idle' | 'success' | 'error'>('idle');
const visibleMonth = ref(
    new Date(new Date().getFullYear(), new Date().getMonth(), 1),
);
const selectedDateKey = ref('');
let restoreDarkMode = false;

onMounted(() => {
    restoreDarkMode = document.documentElement.classList.contains('dark');
    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';
});

onBeforeUnmount(() => {
    if (restoreDarkMode) {
        document.documentElement.classList.add('dark');
    }

    document.documentElement.style.removeProperty('color-scheme');
});

const formatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }),
);

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return formatter.value.format(new Date(value));
};

const dateKey = (date: Date): string =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

selectedDateKey.value = dateKey(new Date());

const weekdayLabel = (day: number, format: 'long' | 'short' = 'long') =>
    new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
        weekday: format,
    }).format(new Date(2024, 0, 7 + day));

const monthLabel = computed(() =>
    new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
        month: 'long',
        year: 'numeric',
    }).format(visibleMonth.value),
);

const calendarItemsForDate = (date: Date): CalendarItem[] => {
    const key = dateKey(date);
    const eventItems = props.calendarEvents
        .filter((event) => dateKey(new Date(event.start_time)) === key)
        .map((event) => ({ ...event, recurring: false }));
    const recurringItems = weeklySchedule.value
        .filter((schedule) => schedule.day_of_week === date.getDay())
        .map((schedule, index) => ({
            id: `schedule-${schedule.title}-${schedule.day_of_week}-${index}`,
            title: schedule.title,
            start_time: schedule.start_time,
            end_time: schedule.end_time,
            recurring: true,
        }));

    return [...eventItems, ...recurringItems];
};

const calendarDays = computed(() => {
    const year = visibleMonth.value.getFullYear();
    const month = visibleMonth.value.getMonth();
    const firstWeekday = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const cells = Math.ceil((firstWeekday + daysInMonth) / 7) * 7;

    return Array.from({ length: cells }, (_, index) => {
        const date = new Date(year, month, index - firstWeekday + 1);

        return {
            date,
            key: dateKey(date),
            currentMonth: date.getMonth() === month,
            items: calendarItemsForDate(date),
        };
    });
});

const selectedCalendarItems = computed(() => {
    const selectedDate = calendarDays.value.find(
        (day) => day.key === selectedDateKey.value,
    );

    return selectedDate?.items ?? [];
});

const changeMonth = (offset: number): void => {
    visibleMonth.value = new Date(
        visibleMonth.value.getFullYear(),
        visibleMonth.value.getMonth() + offset,
        1,
    );
    selectedDateKey.value = dateKey(visibleMonth.value);
};

const formatScheduleTime = (value: string): string => {
    if (/^\d{2}:\d{2}/.test(value)) {
        return value.slice(0, 5);
    }

    return new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const submitPrayer = async (): Promise<void> => {
    if (!prayerContent.value.trim()) {
        return;
    }

    prayerProcessing.value = true;
    prayerStatus.value = 'idle';

    try {
        await axios.post('/api/prayer-requests', {
            content: prayerContent.value,
            is_anonymous: !authenticatedUser.value || prayerAnonymous.value,
        });
        prayerContent.value = '';
        prayerAnonymous.value = false;
        prayerStatus.value = 'success';
    } catch {
        prayerStatus.value = 'error';
    } finally {
        prayerProcessing.value = false;
    }
};
</script>

<template>
    <Head :title="t('home.meta_title')" />

    <div
        class="public-welcome-light flex min-h-screen flex-col bg-[#f8fafc] text-slate-950"
        :style="{
            backgroundColor: 'var(--church-surface, #f8fafc)',
            fontFamily: 'var(--church-font, Manrope, ui-sans-serif)',
        }"
    >
        <PublicHeader active="home" :show-locale="false" />

        <main
            class="mx-auto w-full max-w-7xl flex-1 space-y-7 px-4 py-7 sm:px-6 lg:px-8 lg:py-9"
        >
            <section
                class="relative isolate grid overflow-hidden rounded-[2rem] border p-6 text-white shadow-xl shadow-slate-900/10 md:grid-cols-[1.2fr_0.8fr] md:gap-10 md:p-9 lg:p-12"
                :style="{
                    borderColor:
                        'color-mix(in srgb, var(--church-primary) 75%, black)',
                    background:
                        'linear-gradient(130deg, color-mix(in srgb, var(--church-primary) 78%, black), var(--church-primary) 58%, color-mix(in srgb, var(--church-primary) 68%, white))',
                }"
            >
                <div
                    class="pointer-events-none absolute -top-32 -right-20 -z-10 size-80 rounded-full border border-white/15 bg-white/10 blur-sm"
                />
                <div
                    class="pointer-events-none absolute -bottom-28 left-1/3 -z-10 size-64 rounded-full bg-cyan-300/10 blur-3xl"
                />

                <div
                    class="relative z-10 flex flex-col justify-center"
                    :class="{ 'md:col-span-2': !nextEvent }"
                >
                    <div class="mb-7 flex items-center gap-3">
                        <span
                            class="grid size-12 place-items-center overflow-hidden rounded-2xl border border-white/25 bg-white/10 text-lg font-black shadow-lg backdrop-blur"
                        >
                            <img
                                v-if="branding.logo_url"
                                :src="branding.logo_url"
                                :alt="branding.brand_name || 'Nossa Casa'"
                                class="h-full w-full bg-white object-contain p-1.5"
                            />
                            <template v-else>{{ brandInitials }}</template>
                        </span>
                        <span>
                            <strong class="block text-sm font-black">{{
                                branding.brand_name || 'Nossa Casa'
                            }}</strong>
                            <small class="text-xs text-white/65">{{
                                branding.tagline || t('home.hero.kicker')
                            }}</small>
                        </span>
                    </div>
                    <p
                        class="mb-3 font-mono text-[10px] font-bold tracking-[0.22em] text-amber-300 uppercase"
                    >
                        {{ t('home.hero.kicker') }}
                    </p>
                    <h1
                        class="max-w-3xl text-4xl leading-[1.05] font-black tracking-tight text-balance sm:text-5xl lg:text-6xl"
                    >
                        {{ branding.banner_title || t('home.hero.title') }}
                    </h1>
                    <p
                        class="mt-5 max-w-2xl text-sm leading-7 text-white/75 sm:text-base"
                    >
                        {{
                            branding.banner_subtitle ||
                            t('home.hero.description')
                        }}
                    </p>

                    <div class="mt-7 flex flex-wrap gap-3">
                        <Link
                            :href="eventsIndex()"
                            class="inline-flex items-center gap-2 rounded-full bg-white px-5 py-3 text-xs font-extrabold text-indigo-950 shadow-lg transition hover:-translate-y-0.5"
                        >
                            {{ t('home.hero.cta_events') }}
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            :href="galleryIndex()"
                            class="rounded-full border border-white/30 bg-white/5 px-5 py-3 text-xs font-extrabold text-white backdrop-blur transition hover:bg-white/15"
                        >
                            {{ t('home.hero.cta_gallery') }}
                        </Link>
                    </div>
                </div>

                <Link
                    v-if="nextEvent"
                    :href="eventsShow({ event: nextEvent.slug })"
                    class="group relative z-10 min-h-72 overflow-hidden rounded-3xl border border-white/20 bg-slate-950/25 shadow-2xl backdrop-blur"
                >
                    <img
                        v-if="nextEvent.cover_path"
                        :src="nextEvent.cover_path"
                        :alt="nextEvent.title"
                        class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105"
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent"
                    />
                    <div
                        class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-4 p-6"
                    >
                        <div>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-amber-400 px-3 py-1 text-[10px] font-black tracking-wide text-amber-950 uppercase"
                            >
                                <CalendarDays class="size-3" />
                                {{ t('home.hero.next_event') }}
                            </span>
                            <h2 class="mt-3 text-2xl font-black text-balance">
                                {{ nextEvent.title }}
                            </h2>
                            <p class="mt-2 text-xs text-white/70">
                                {{ formatDate(nextEvent.start_time) }}
                            </p>
                        </div>
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-full bg-white text-indigo-950 transition group-hover:translate-x-1"
                        >
                            <ArrowRight class="size-5" />
                        </span>
                    </div>
                </Link>
            </section>

            <section class="grid gap-8 lg:grid-cols-[1.4fr_1fr]">
                <div>
                    <div class="mb-4 flex items-end justify-between">
                        <div>
                            <p
                                class="font-mono text-[10px] font-bold tracking-[0.16em] text-indigo-500 uppercase"
                            >
                                {{ t('home.featured.kicker') }}
                            </p>
                            <h2
                                class="[font-family:Manrope,ui-sans-serif] text-2xl font-black"
                            >
                                {{ t('home.featured.title') }}
                            </h2>
                        </div>
                        <Link
                            :href="eventsIndex()"
                            class="text-xs font-bold text-indigo-600"
                            >{{ t('home.featured.view_all') }}</Link
                        >
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="event in props.featuredEvents"
                            :key="event.id"
                            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <div
                                class="relative h-36 overflow-hidden bg-[#d6e3ef]"
                            >
                                <img
                                    v-if="event.cover_path"
                                    :src="event.cover_path"
                                    :alt="event.title"
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="absolute inset-0 bg-gradient-to-br from-indigo-950 to-indigo-700"
                                />
                            </div>
                            <div class="space-y-2 p-4">
                                <p
                                    class="text-[10px] font-semibold tracking-[0.12em] text-indigo-500 uppercase"
                                >
                                    {{
                                        event.church?.name ??
                                        t('events.shared.community')
                                    }}
                                </p>
                                <h3
                                    class="line-clamp-2 [font-family:Manrope,ui-sans-serif] text-lg font-black"
                                >
                                    {{ event.title }}
                                </h3>
                                <p class="line-clamp-2 text-sm text-[#53687d]">
                                    {{ event.excerpt }}
                                </p>
                                <p class="text-xs text-[#6b8096]">
                                    {{ formatDate(event.start_time) }}
                                </p>
                                <Link
                                    :href="eventsShow({ event: event.slug })"
                                    class="inline-flex rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white"
                                >
                                    {{ t('home.featured.view_detail') }}
                                </Link>
                            </div>
                        </article>
                    </div>
                </div>

                <div>
                    <div class="mb-4">
                        <p
                            class="font-mono text-[10px] font-bold tracking-[0.16em] text-indigo-500 uppercase"
                        >
                            {{ t('home.latest_posts.kicker') }}
                        </p>
                        <h2
                            class="[font-family:Manrope,ui-sans-serif] text-2xl font-black"
                        >
                            {{ t('home.latest_posts.title') }}
                        </h2>
                    </div>

                    <div class="space-y-3">
                        <Link
                            v-for="post in props.latestPosts"
                            :key="post.id"
                            :href="publicPostShow({ slug: post.slug })"
                            class="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md"
                        >
                            <h3
                                class="[font-family:Manrope,ui-sans-serif] text-base font-black"
                            >
                                {{ post.title }}
                            </h3>
                            <p class="mt-1 line-clamp-2 text-sm text-[#53687d]">
                                {{ post.excerpt }}
                            </p>
                            <p class="mt-2 text-xs text-[#6b8096]">
                                {{ formatDate(post.published_at) }}
                            </p>
                        </Link>
                    </div>
                </div>
            </section>

            <section
                class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.7fr)]"
            >
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-6"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p
                                class="font-mono text-[10px] font-bold tracking-[0.16em] uppercase"
                                :style="{ color: 'var(--church-primary)' }"
                            >
                                {{ t('home.calendar.kicker') }}
                            </p>
                            <h2 class="mt-1 text-2xl font-black">
                                {{ t('home.calendar.title') }}
                            </h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                                :aria-label="t('home.calendar.previous')"
                                @click="changeMonth(-1)"
                            >
                                <ChevronLeft class="size-4" />
                            </button>
                            <strong
                                class="min-w-36 text-center text-sm capitalize"
                                >{{ monthLabel }}</strong
                            >
                            <button
                                type="button"
                                class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                                :aria-label="t('home.calendar.next')"
                                @click="changeMonth(1)"
                            >
                                <ChevronRight class="size-4" />
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-7 gap-1.5 text-center">
                        <span
                            v-for="day in 7"
                            :key="day"
                            class="py-1 text-[10px] font-black tracking-wide text-slate-400 uppercase"
                        >
                            {{ weekdayLabel(day - 1, 'short') }}
                        </span>
                        <button
                            v-for="day in calendarDays"
                            :key="day.key"
                            type="button"
                            class="relative min-h-12 rounded-xl border p-2 text-sm font-bold transition sm:min-h-16"
                            :class="[
                                day.currentMonth
                                    ? 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'
                                    : 'border-transparent bg-slate-50 text-slate-300',
                                selectedDateKey === day.key
                                    ? 'ring-2 ring-offset-1'
                                    : '',
                            ]"
                            :style="
                                selectedDateKey === day.key
                                    ? {
                                          '--tw-ring-color':
                                              'var(--church-primary)',
                                      }
                                    : undefined
                            "
                            @click="selectedDateKey = day.key"
                        >
                            {{ day.date.getDate() }}
                            <span
                                v-if="day.items.length"
                                class="absolute right-1.5 bottom-1.5 flex gap-0.5"
                            >
                                <span
                                    v-for="item in day.items.slice(0, 3)"
                                    :key="item.id"
                                    class="size-1.5 rounded-full"
                                    :style="{
                                        backgroundColor: item.recurring
                                            ? 'var(--church-accent)'
                                            : 'var(--church-primary)',
                                    }"
                                />
                            </span>
                        </button>
                    </div>

                    <div
                        class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <p
                            class="text-[10px] font-black tracking-wide text-slate-500 uppercase"
                        >
                            {{ t('home.calendar.selected_day') }} ·
                            {{ selectedDateKey }}
                        </p>
                        <div
                            v-if="selectedCalendarItems.length"
                            class="mt-3 grid gap-2"
                        >
                            <component
                                :is="item.slug ? Link : 'div'"
                                v-for="item in selectedCalendarItems"
                                :key="item.id"
                                :href="
                                    item.slug
                                        ? `/events/${item.slug}`
                                        : undefined
                                "
                                class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-sm"
                            >
                                <span class="min-w-0">
                                    <strong class="block truncate">{{
                                        item.title
                                    }}</strong>
                                    <small class="text-slate-500">{{
                                        item.recurring
                                            ? t('home.calendar.weekly')
                                            : t('home.calendar.event')
                                    }}</small>
                                </span>
                                <span
                                    class="shrink-0 text-xs font-bold text-slate-500"
                                >
                                    {{ formatScheduleTime(item.start_time) }}–{{
                                        formatScheduleTime(item.end_time)
                                    }}
                                </span>
                            </component>
                        </div>
                        <p v-else class="mt-2 text-sm text-slate-500">
                            {{ t('home.calendar.empty') }}
                        </p>
                    </div>
                </article>

                <article
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-6"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600"
                        >
                            <HeartHandshake class="size-5" />
                        </span>
                        <div>
                            <h2 class="text-lg font-black">
                                {{ t('home.prayer.title') }}
                            </h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                {{ t('home.prayer.description') }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="authenticatedUser"
                        class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-600"
                    >
                        {{
                            t('home.prayer.sending_as', {
                                name: authenticatedUser.name,
                            })
                        }}
                    </div>

                    <form
                        class="mt-4 grid gap-3"
                        @submit.prevent="submitPrayer"
                    >
                        <textarea
                            v-model="prayerContent"
                            required
                            maxlength="2000"
                            rows="6"
                            class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm"
                            :placeholder="t('home.prayer.placeholder')"
                        />
                        <label
                            v-if="authenticatedUser"
                            class="flex items-center gap-2 text-xs text-slate-600"
                        >
                            <input
                                v-model="prayerAnonymous"
                                type="checkbox"
                                class="rounded border-slate-300"
                            />
                            {{ t('home.prayer.anonymous') }}
                        </label>
                        <p
                            v-if="prayerStatus === 'success'"
                            class="text-xs font-bold text-emerald-600"
                        >
                            {{ t('home.prayer.success') }}
                        </p>
                        <p
                            v-if="prayerStatus === 'error'"
                            class="text-xs font-bold text-rose-600"
                        >
                            {{ t('home.prayer.error') }}
                        </p>
                        <button
                            :disabled="prayerProcessing"
                            class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-black text-white disabled:opacity-50"
                            :style="{
                                backgroundColor: 'var(--church-primary)',
                            }"
                        >
                            <Send class="size-4" />
                            {{
                                prayerProcessing
                                    ? t('home.prayer.sending')
                                    : t('home.prayer.submit')
                            }}
                        </button>
                    </form>
                </article>
            </section>

            <section
                class="grid gap-6 lg:grid-cols-[minmax(20rem,0.75fr)_minmax(0,1.25fr)]"
            >
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-6"
                >
                    <p
                        class="font-mono text-[10px] font-bold tracking-[0.16em] uppercase"
                        :style="{ color: 'var(--church-primary)' }"
                    >
                        {{ t('home.visit.kicker') }}
                    </p>
                    <h2 class="mt-1 text-2xl font-black">
                        {{ t('home.visit.title') }}
                    </h2>

                    <div class="mt-5 grid gap-3 text-sm text-slate-600">
                        <p
                            v-if="branding.address"
                            class="flex items-start gap-3"
                        >
                            <MapPin
                                class="mt-0.5 size-4 shrink-0"
                                :style="{ color: 'var(--church-primary)' }"
                            />
                            <span class="whitespace-pre-wrap">{{
                                branding.address
                            }}</span>
                        </p>
                        <a
                            v-if="branding.contact_phone"
                            :href="`tel:${branding.contact_phone}`"
                            class="flex items-center gap-3 hover:text-slate-950"
                        >
                            <Phone
                                class="size-4"
                                :style="{ color: 'var(--church-primary)' }"
                            />{{ branding.contact_phone }}
                        </a>
                        <a
                            v-if="branding.contact_email"
                            :href="`mailto:${branding.contact_email}`"
                            class="flex items-center gap-3 hover:text-slate-950"
                        >
                            <Mail
                                class="size-4"
                                :style="{ color: 'var(--church-primary)' }"
                            />{{ branding.contact_email }}
                        </a>
                    </div>

                    <div
                        v-if="scheduleGroups.length"
                        class="mt-6 border-t border-slate-100 pt-5"
                    >
                        <h3 class="flex items-center gap-2 font-black">
                            <Clock3
                                class="size-4"
                                :style="{ color: 'var(--church-primary)' }"
                            />{{ t('home.schedule.title') }}
                        </h3>
                        <div class="mt-3 grid gap-3">
                            <article
                                v-for="group in scheduleGroups"
                                :key="group.title"
                                class="rounded-xl bg-slate-50 p-3"
                            >
                                <strong class="text-sm">{{
                                    group.title
                                }}</strong>
                                <p
                                    v-for="schedule in group.schedules"
                                    :key="`${schedule.day_of_week}-${schedule.start_time}`"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    {{ weekdayLabel(schedule.day_of_week) }} ·
                                    {{ schedule.start_time }}–{{
                                        schedule.end_time
                                    }}
                                </p>
                            </article>
                        </div>
                    </div>
                </article>

                <article
                    v-if="branding.map_embed"
                    class="min-h-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <iframe
                        :src="String(branding.map_embed)"
                        :title="t('home.visit.map_title')"
                        class="h-full min-h-80 w-full border-0"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                    />
                </article>
            </section>
        </main>
        <PublicFooter show-locale />
    </div>
</template>
