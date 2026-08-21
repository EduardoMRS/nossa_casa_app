<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BookOpen,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CloudDownload,
    LoaderCircle,
    WifiOff,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from '@/lib/i18n';
import {
    books as bibleBooks,
    chapter as bibleChapter,
    chapters as bibleChapters,
} from '@/routes/bible';

type BibleVersion = {
    id: string;
    name: string;
    abbreviation: string;
    language: string;
    language_code: string;
    scope: string;
    copyright: string;
    offline_available: boolean;
    offline_url: string | null;
};

type BibleBook = { slug: string; name: string };
type BibleVerse = {
    book: string;
    chapter: number;
    verse: number;
    text: string;
};

type BibleCacheMessage = {
    type: string;
    completed?: number;
    total?: number;
    readyVersions?: string[];
};

const props = defineProps<{
    versions: BibleVersion[];
    defaultVersion: string | null;
}>();

const { t } = useI18n();
const version = ref(
    props.versions.some((item) => item.id === props.defaultVersion)
        ? (props.defaultVersion ?? '')
        : (props.versions[0]?.id ?? ''),
);
const book = ref('');
const chapter = ref<number | null>(null);
const books = ref<BibleBook[]>([]);
const chapters = ref<number[]>([]);
const verses = ref<BibleVerse[]>([]);
const loading = ref(false);
const error = ref('');
const offlineState = ref<
    'checking' | 'idle' | 'downloading' | 'ready' | 'error'
>('checking');
const offlineProgress = ref({ completed: 0, total: 0 });
const readyOfflineVersions = ref<string[]>([]);
const currentChapterIndex = computed(() =>
    chapters.value.findIndex((item) => item === chapter.value),
);
const selectedVersion = computed(() =>
    props.versions.find((item) => item.id === version.value),
);
const downloadableVersions = computed(() =>
    props.versions.filter((item) => item.offline_available && item.offline_url),
);
const offlineReadyCount = computed(
    () =>
        downloadableVersions.value.filter((item) =>
            readyOfflineVersions.value.includes(item.id),
        ).length,
);
const offlineProgressPercentage = computed(() =>
    offlineProgress.value.total > 0
        ? Math.round(
              (offlineProgress.value.completed / offlineProgress.value.total) *
                  100,
          )
        : 0,
);

const getJson = async <T,>(url: string): Promise<T> => {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(t('library.bible.load_error'));
    }

    return response.json() as Promise<T>;
};

const loadChapter = async (): Promise<void> => {
    if (!version.value || !book.value || chapter.value === null) {
        verses.value = [];

        return;
    }

    const payload = await getJson<{ verses: BibleVerse[] }>(
        bibleChapter.url({
            version: version.value,
            book: book.value,
            chapter: chapter.value,
        }),
    );
    verses.value = payload.verses;
};

const loadChapters = async (): Promise<void> => {
    if (!version.value || !book.value) {
        chapters.value = [];
        verses.value = [];

        return;
    }

    const payload = await getJson<{ chapters: number[] }>(
        bibleChapters.url({ version: version.value, book: book.value }),
    );
    chapters.value = payload.chapters;
    chapter.value = payload.chapters[0] ?? null;
    await loadChapter();
};

const runLoad = async (callback: () => Promise<void>): Promise<void> => {
    loading.value = true;
    error.value = '';

    try {
        await callback();
    } catch {
        error.value = navigator.onLine
            ? t('library.bible.load_error')
            : t('library.bible.offline_missing');
    } finally {
        loading.value = false;
    }
};

const loadBooks = (): Promise<void> =>
    runLoad(async () => {
        books.value = [];
        chapters.value = [];
        verses.value = [];
        const payload = await getJson<{ books: BibleBook[] }>(
            bibleBooks.url(version.value),
        );
        books.value = payload.books;
        book.value = payload.books[0]?.slug ?? '';
        await loadChapters();
    });

const changeBook = (): Promise<void> => runLoad(loadChapters);
const changeChapter = (): Promise<void> => runLoad(loadChapter);

const moveChapter = (direction: -1 | 1): void => {
    const target = chapters.value[currentChapterIndex.value + direction];

    if (target !== undefined) {
        chapter.value = target;
        void changeChapter();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const serviceWorker = async (): Promise<ServiceWorker | null> => {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) {
        return null;
    }

    const registration = await navigator.serviceWorker.ready;

    return navigator.serviceWorker.controller ?? registration.active;
};

const checkOfflineStatus = async (): Promise<void> => {
    const worker = await serviceWorker();

    if (!worker || !downloadableVersions.value.length) {
        offlineState.value = 'idle';

        return;
    }

    worker.postMessage({
        type: 'CHECK_BIBLE_CACHE',
        versions: downloadableVersions.value.map((item) => ({ id: item.id })),
    });
};

const downloadForOffline = async (): Promise<void> => {
    const worker = await serviceWorker();

    if (!worker) {
        offlineState.value = 'error';

        return;
    }

    offlineState.value = 'downloading';
    offlineProgress.value = { completed: 0, total: 0 };
    worker.postMessage({
        type: 'CACHE_BIBLES',
        readerUrl: window.location.pathname,
        versions: downloadableVersions.value.map((item) => ({
            id: item.id,
            url: item.offline_url,
        })),
    });
};

const handleServiceWorkerMessage = (event: MessageEvent): void => {
    const message = event.data as BibleCacheMessage;

    if (!message || typeof message.type !== 'string') {
        return;
    }

    if (message.type === 'BIBLE_CACHE_STATUS') {
        readyOfflineVersions.value = message.readyVersions ?? [];
        offlineState.value =
            offlineReadyCount.value === downloadableVersions.value.length &&
            downloadableVersions.value.length > 0
                ? 'ready'
                : 'idle';
    }

    if (message.type === 'BIBLE_CACHE_PROGRESS') {
        offlineState.value = 'downloading';
        offlineProgress.value = {
            completed: message.completed ?? 0,
            total: message.total ?? 0,
        };
    }

    if (message.type === 'BIBLE_CACHE_READY') {
        readyOfflineVersions.value = message.readyVersions ?? [];
        offlineState.value = 'ready';
    }

    if (message.type === 'BIBLE_CACHE_ERROR') {
        offlineState.value = 'error';
    }
};

onMounted(() => {
    navigator.serviceWorker?.addEventListener(
        'message',
        handleServiceWorkerMessage,
    );

    if (version.value) {
        void loadBooks();
    }

    void checkOfflineStatus();
});

onBeforeUnmount(() => {
    navigator.serviceWorker?.removeEventListener(
        'message',
        handleServiceWorkerMessage,
    );
});
</script>

<template>
    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 sm:py-10">
        <Head :title="t('library.bible.title')" />

        <header class="mx-auto max-w-3xl text-center">
            <BookOpen
                class="mx-auto size-9 text-[var(--church-primary,#6750a4)]"
            />
            <p
                class="mt-3 text-xs font-bold tracking-[0.22em] text-[var(--reader-muted)] uppercase"
            >
                {{ t('library.bible.type') }}
            </p>
            <h1 class="mt-2 font-serif text-3xl font-bold sm:text-4xl">
                {{ t('library.bible.title') }}
            </h1>
            <p class="mt-3 text-sm leading-6 text-[var(--reader-muted)]">
                {{ t('library.bible.description') }}
            </p>
        </header>

        <section
            v-if="versions.length"
            class="sticky top-16 z-30 mt-7 rounded-2xl border border-[var(--reader-border)] bg-[color:var(--reader-surface)]/95 p-3 shadow-lg shadow-black/5 backdrop-blur sm:p-4"
        >
            <div class="grid gap-3 md:grid-cols-[1.5fr_1fr_0.65fr_auto]">
                <label
                    class="grid gap-1 text-xs font-bold text-[var(--reader-muted)]"
                >
                    {{ t('library.bible.version') }}
                    <select
                        v-model="version"
                        class="h-10 rounded-lg border border-[var(--reader-border)] bg-[var(--reader-bg)] px-3 text-sm text-[var(--reader-text)]"
                        @change="loadBooks"
                    >
                        <option
                            v-for="item in versions"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.abbreviation }} · {{ item.name }}
                        </option>
                    </select>
                </label>
                <label
                    class="grid gap-1 text-xs font-bold text-[var(--reader-muted)]"
                >
                    {{ t('library.bible.book') }}
                    <select
                        v-model="book"
                        :disabled="loading || !books.length"
                        class="h-10 rounded-lg border border-[var(--reader-border)] bg-[var(--reader-bg)] px-3 text-sm text-[var(--reader-text)] disabled:opacity-50"
                        @change="changeBook"
                    >
                        <option
                            v-for="item in books"
                            :key="item.slug"
                            :value="item.slug"
                        >
                            {{ item.name }}
                        </option>
                    </select>
                </label>
                <label
                    class="grid gap-1 text-xs font-bold text-[var(--reader-muted)]"
                >
                    {{ t('library.bible.chapter') }}
                    <select
                        v-model="chapter"
                        :disabled="loading || !chapters.length"
                        class="h-10 rounded-lg border border-[var(--reader-border)] bg-[var(--reader-bg)] px-3 text-sm text-[var(--reader-text)] disabled:opacity-50"
                        @change="changeChapter"
                    >
                        <option
                            v-for="item in chapters"
                            :key="item"
                            :value="item"
                        >
                            {{ item }}
                        </option>
                    </select>
                </label>

                <button
                    v-if="downloadableVersions.length"
                    type="button"
                    class="mt-auto inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[var(--church-primary,#6750a4)] px-3 text-xs font-bold text-white transition hover:brightness-110 disabled:cursor-wait disabled:opacity-70"
                    :disabled="offlineState === 'downloading'"
                    :title="t('library.bible.offline_download_description')"
                    @click="downloadForOffline"
                >
                    <LoaderCircle
                        v-if="offlineState === 'downloading'"
                        class="size-4 animate-spin"
                    />
                    <CheckCircle2
                        v-else-if="offlineState === 'ready'"
                        class="size-4"
                    />
                    <CloudDownload v-else class="size-4" />
                    <span>
                        {{
                            offlineState === 'ready'
                                ? t('library.bible.offline_ready')
                                : t('library.bible.offline_download')
                        }}
                    </span>
                </button>
            </div>

            <div v-if="offlineState === 'downloading'" class="mt-3 grid gap-1">
                <div class="flex justify-between gap-3 text-xs font-semibold">
                    <span>{{ t('library.bible.offline_downloading') }}</span>
                    <span>{{ offlineProgressPercentage }}%</span>
                </div>
                <div
                    class="h-1.5 overflow-hidden rounded-full bg-[var(--reader-border)]"
                >
                    <div
                        class="h-full rounded-full bg-[var(--church-primary,#6750a4)] transition-[width]"
                        :style="{ width: `${offlineProgressPercentage}%` }"
                    />
                </div>
            </div>
            <p
                v-else-if="offlineState === 'error'"
                class="mt-3 flex items-center gap-2 text-xs font-semibold text-rose-600"
            >
                <WifiOff class="size-4" />
                {{ t('library.bible.offline_error') }}
            </p>
            <p
                v-else-if="downloadableVersions.length < versions.length"
                class="mt-3 text-xs leading-5 text-[var(--reader-muted)]"
            >
                {{
                    t('library.bible.offline_open_versions', {
                        count: downloadableVersions.length,
                    })
                }}
            </p>
        </section>

        <p
            v-if="selectedVersion?.copyright"
            class="mx-auto mt-3 max-w-3xl text-center text-xs leading-5 text-[var(--reader-muted)]"
        >
            {{ t('library.bible.copyright_notice') }}:
            {{ selectedVersion.copyright }}
        </p>

        <p
            v-if="!versions.length"
            class="mt-10 rounded-2xl border border-dashed border-[var(--reader-border)] p-10 text-center text-[var(--reader-muted)]"
        >
            {{ t('library.bible.no_versions') }}
        </p>
        <p
            v-else-if="error"
            class="mt-8 rounded-xl border border-rose-300/50 bg-rose-500/10 p-4 text-center text-sm font-bold text-rose-600"
        >
            {{ error }}
        </p>
        <div v-else-if="loading" class="grid min-h-80 place-items-center">
            <LoaderCircle
                class="size-8 animate-spin text-[var(--church-primary,#6750a4)]"
            />
        </div>
        <article v-else-if="verses.length" class="mx-auto mt-10 max-w-3xl">
            <header class="flex items-center justify-between gap-4">
                <button
                    type="button"
                    :disabled="currentChapterIndex <= 0"
                    class="rounded-full border border-[var(--reader-border)] bg-[var(--reader-surface)] p-2.5 disabled:opacity-25"
                    :title="t('library.bible.previous_chapter')"
                    @click="moveChapter(-1)"
                >
                    <ChevronLeft class="size-5" />
                </button>
                <div class="text-center">
                    <p
                        class="text-xs font-bold text-[var(--reader-muted)] uppercase"
                    >
                        {{ selectedVersion?.abbreviation }}
                    </p>
                    <h2 class="mt-1 font-serif text-2xl font-bold sm:text-3xl">
                        {{ verses[0]?.book }} {{ chapter }}
                    </h2>
                </div>
                <button
                    type="button"
                    :disabled="currentChapterIndex >= chapters.length - 1"
                    class="rounded-full border border-[var(--reader-border)] bg-[var(--reader-surface)] p-2.5 disabled:opacity-25"
                    :title="t('library.bible.next_chapter')"
                    @click="moveChapter(1)"
                >
                    <ChevronRight class="size-5" />
                </button>
            </header>

            <div
                class="mt-8 space-y-5 font-serif text-lg leading-9 sm:text-xl sm:leading-10"
            >
                <p v-for="item in verses" :key="item.verse">
                    <sup
                        class="mr-1.5 font-sans text-xs font-black text-[var(--church-primary,#6750a4)]"
                    >
                        {{ item.verse }}
                    </sup>
                    {{ item.text }}
                </p>
            </div>

            <footer
                class="mt-12 flex items-center justify-between gap-4 border-t border-[var(--reader-border)] pt-6"
            >
                <button
                    type="button"
                    :disabled="currentChapterIndex <= 0"
                    class="inline-flex items-center gap-2 rounded-full border border-[var(--reader-border)] bg-[var(--reader-surface)] px-4 py-2 text-sm font-bold disabled:opacity-25"
                    @click="moveChapter(-1)"
                >
                    <ChevronLeft class="size-4" />
                    {{ t('library.bible.previous_chapter') }}
                </button>
                <button
                    type="button"
                    :disabled="currentChapterIndex >= chapters.length - 1"
                    class="inline-flex items-center gap-2 rounded-full border border-[var(--reader-border)] bg-[var(--reader-surface)] px-4 py-2 text-sm font-bold disabled:opacity-25"
                    @click="moveChapter(1)"
                >
                    {{ t('library.bible.next_chapter') }}
                    <ChevronRight class="size-4" />
                </button>
            </footer>
        </article>
    </div>
</template>
