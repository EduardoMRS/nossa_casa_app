<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BookOpen,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CloudDownload,
    Eraser,
    Highlighter,
    LoaderCircle,
    WifiOff,
    X,
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

type BibleReadingPosition = {
    version: string;
    book: string;
    chapter: number;
};

type BibleHighlightColor = 'yellow' | 'green' | 'blue' | 'pink' | 'purple';

const bibleReadingPositionKey = 'nossa-casa:bible-reading-position:v1';
const bibleHighlightsKey = 'nossa-casa:bible-highlights:v1';
const highlightPalette: Array<{
    color: BibleHighlightColor;
    backgroundColor: string;
}> = [
    { color: 'yellow', backgroundColor: 'rgba(250, 204, 21, 0.38)' },
    { color: 'green', backgroundColor: 'rgba(74, 222, 128, 0.32)' },
    { color: 'blue', backgroundColor: 'rgba(96, 165, 250, 0.3)' },
    { color: 'pink', backgroundColor: 'rgba(244, 114, 182, 0.3)' },
    { color: 'purple', backgroundColor: 'rgba(192, 132, 252, 0.3)' },
];

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
const isOnline = ref(true);
const selectorsExpanded = ref(false);
const automaticDownloadStarted = ref(false);
const verseHighlights = ref<Record<string, BibleHighlightColor>>({});
const selectedVerse = ref<number | null>(null);
let lastScrollPosition = 0;
const currentChapterIndex = computed(() =>
    chapters.value.findIndex((item) => item === chapter.value),
);
const selectedVersion = computed(() =>
    props.versions.find((item) => item.id === version.value),
);
const selectableVersions = computed(() =>
    isOnline.value
        ? props.versions
        : props.versions.filter((item) =>
              readyOfflineVersions.value.includes(item.id),
          ),
);
const selectedVersionCanBeDownloaded = computed(() =>
    Boolean(
        selectedVersion.value?.offline_available &&
            selectedVersion.value.offline_url,
    ),
);
const selectedVersionOfflineReady = computed(() =>
    readyOfflineVersions.value.includes(version.value),
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
const isHighlightColor = (value: unknown): value is BibleHighlightColor =>
    highlightPalette.some((item) => item.color === value);
const verseHighlightKey = (verseNumber: number): string =>
    [version.value, book.value, chapter.value, verseNumber]
        .map((part) => encodeURIComponent(String(part)))
        .join(':');
const selectedVerseHighlight = computed(() =>
    selectedVerse.value === null
        ? null
        : (verseHighlights.value[verseHighlightKey(selectedVerse.value)] ??
          null),
);

const readStoredPosition = (): BibleReadingPosition | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const stored = window.localStorage.getItem(bibleReadingPositionKey);

        if (!stored) {
            return null;
        }

        const position = JSON.parse(stored) as Partial<BibleReadingPosition>;

        if (
            typeof position.version !== 'string' ||
            typeof position.book !== 'string' ||
            typeof position.chapter !== 'number' ||
            !Number.isInteger(position.chapter) ||
            position.chapter < 1
        ) {
            return null;
        }

        return position as BibleReadingPosition;
    } catch {
        return null;
    }
};

const restoreReadingPosition = (): void => {
    const position = readStoredPosition();

    if (
        !position ||
        !props.versions.some((item) => item.id === position.version)
    ) {
        return;
    }

    version.value = position.version;
    book.value = position.book;
    chapter.value = position.chapter;
};

const persistReadingPosition = (): void => {
    if (
        typeof window === 'undefined' ||
        !version.value ||
        !book.value ||
        chapter.value === null
    ) {
        return;
    }

    try {
        window.localStorage.setItem(
            bibleReadingPositionKey,
            JSON.stringify({
                version: version.value,
                book: book.value,
                chapter: chapter.value,
            } satisfies BibleReadingPosition),
        );
    } catch {
        // Reading still works when storage is disabled or unavailable.
    }
};

const loadVerseHighlights = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const stored = window.localStorage.getItem(bibleHighlightsKey);
        const parsed = stored
            ? (JSON.parse(stored) as Record<string, unknown>)
            : {};
        const validHighlights: Record<string, BibleHighlightColor> = {};

        Object.entries(parsed).forEach(([key, color]) => {
            if (isHighlightColor(color)) {
                validHighlights[key] = color;
            }
        });

        verseHighlights.value = validHighlights;
    } catch {
        verseHighlights.value = {};
    }
};

const persistVerseHighlights = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            bibleHighlightsKey,
            JSON.stringify(verseHighlights.value),
        );
    } catch {
        // Highlighting remains available for the current session.
    }
};

const verseHighlightStyle = (
    verseNumber: number,
): Record<string, string> | undefined => {
    const color = verseHighlights.value[verseHighlightKey(verseNumber)];
    const paletteItem = highlightPalette.find((item) => item.color === color);

    return paletteItem
        ? { backgroundColor: paletteItem.backgroundColor }
        : undefined;
};

const selectVerse = (verseNumber: number): void => {
    selectedVerse.value =
        selectedVerse.value === verseNumber ? null : verseNumber;
};

const highlightColorLabel = (color: BibleHighlightColor): string =>
    t(`library.bible.highlight_colors.${color}`);

const setVerseHighlight = (color: BibleHighlightColor): void => {
    if (selectedVerse.value === null) {
        return;
    }

    verseHighlights.value = {
        ...verseHighlights.value,
        [verseHighlightKey(selectedVerse.value)]: color,
    };
    persistVerseHighlights();
};

const removeVerseHighlight = (): void => {
    if (selectedVerse.value === null) {
        return;
    }

    const nextHighlights = { ...verseHighlights.value };

    delete nextHighlights[verseHighlightKey(selectedVerse.value)];
    verseHighlights.value = nextHighlights;
    persistVerseHighlights();
};

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
    selectedVerse.value = null;

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
    persistReadingPosition();
};

const loadChapters = async (
    preferredChapter: number | null = null,
): Promise<void> => {
    if (!version.value || !book.value) {
        chapters.value = [];
        verses.value = [];

        return;
    }

    const payload = await getJson<{ chapters: number[] }>(
        bibleChapters.url({ version: version.value, book: book.value }),
    );
    chapters.value = payload.chapters;
    chapter.value =
        preferredChapter !== null &&
        payload.chapters.includes(preferredChapter)
            ? preferredChapter
            : (payload.chapters[0] ?? null);
    await loadChapter();
};

const runLoad = async (callback: () => Promise<void>): Promise<void> => {
    loading.value = true;
    error.value = '';

    try {
        await callback();
    } catch {
        error.value = isOnline.value
            ? t('library.bible.load_error')
            : t('library.bible.offline_missing');
    } finally {
        loading.value = false;
    }
};

const loadBooks = (
    preferredBook: string = book.value,
    preferredChapter: number | null = chapter.value,
): Promise<void> =>
    runLoad(async () => {
        books.value = [];
        chapters.value = [];
        verses.value = [];
        const payload = await getJson<{ books: BibleBook[] }>(
            bibleBooks.url(version.value),
        );
        books.value = payload.books;
        book.value = payload.books.some(
            (item) => item.slug === preferredBook,
        )
            ? preferredBook
            : (payload.books[0]?.slug ?? '');
        await loadChapters(preferredChapter);
    });

const changeVersion = (): Promise<void> =>
    loadBooks(book.value, chapter.value);
const changeBook = (): Promise<void> =>
    runLoad(() => loadChapters(null));
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

const downloadForOffline = async (
    targetVersions: BibleVersion[],
): Promise<void> => {
    if (!targetVersions.length) {
        return;
    }

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
        versions: targetVersions.map((item) => ({
            id: item.id,
            url: item.offline_url,
        })),
    });
};

const downloadSelectedVersionForOffline = async (): Promise<void> => {
    const selected = selectedVersion.value;

    if (
        !selected?.offline_available ||
        !selected.offline_url ||
        !isOnline.value
    ) {
        return;
    }

    await downloadForOffline([selected]);
};

const downloadMissingOpenVersions = async (): Promise<void> => {
    const missingVersions = downloadableVersions.value.filter(
        (item) => !readyOfflineVersions.value.includes(item.id),
    );

    if (
        automaticDownloadStarted.value ||
        !isOnline.value ||
        !missingVersions.length
    ) {
        return;
    }

    automaticDownloadStarted.value = true;
    await downloadForOffline(missingVersions);
};

const handleScroll = (): void => {
    const currentScrollPosition = window.scrollY;

    if (
        selectorsExpanded.value &&
        currentScrollPosition > 24 &&
        currentScrollPosition > lastScrollPosition + 4
    ) {
        selectorsExpanded.value = false;
    }

    lastScrollPosition = currentScrollPosition;
};

const ensureSelectableVersion = async (): Promise<void> => {
    if (isOnline.value) {
        if (!version.value && props.versions.length) {
            version.value =
                props.versions.find(
                    (item) => item.id === props.defaultVersion,
                )?.id ??
                props.versions[0]?.id ??
                '';
            await loadBooks();
        }

        return;
    }

    const availableVersions = selectableVersions.value;

    if (!availableVersions.length) {
        version.value = '';
        books.value = [];
        chapters.value = [];
        verses.value = [];
        error.value = t('library.bible.offline_no_versions');

        return;
    }

    const nextVersion = availableVersions.some(
        (item) => item.id === version.value,
    )
        ? version.value
        : availableVersions[0].id;
    const shouldLoad = nextVersion !== version.value || !books.value.length;

    version.value = nextVersion;
    error.value = '';

    if (shouldLoad) {
        await loadBooks();
    }
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
        void downloadMissingOpenVersions();

        if (!isOnline.value) {
            void ensureSelectableVersion();
        }
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
        offlineState.value =
            offlineReadyCount.value === downloadableVersions.value.length
                ? 'ready'
                : 'idle';

        if (!isOnline.value) {
            void ensureSelectableVersion();
        }
    }

    if (message.type === 'BIBLE_CACHE_ERROR') {
        offlineState.value = 'error';
    }
};

const handleOnline = (): void => {
    isOnline.value = true;
    automaticDownloadStarted.value = false;
    error.value = '';
    void ensureSelectableVersion();
    void checkOfflineStatus();
};

const handleOffline = (): void => {
    isOnline.value = false;
    void ensureSelectableVersion();
    void checkOfflineStatus();
};

onMounted(() => {
    restoreReadingPosition();
    loadVerseHighlights();
    isOnline.value = navigator.onLine;
    selectorsExpanded.value = window.matchMedia('(min-width: 640px)').matches;
    lastScrollPosition = window.scrollY;
    navigator.serviceWorker?.addEventListener(
        'message',
        handleServiceWorkerMessage,
    );
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (version.value && isOnline.value) {
        void loadBooks();
    } else if (!isOnline.value) {
        void ensureSelectableVersion();
    }

    void checkOfflineStatus();
});

onBeforeUnmount(() => {
    navigator.serviceWorker?.removeEventListener(
        'message',
        handleServiceWorkerMessage,
    );
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
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
                class="mt-3 text-xs font-bold uppercase tracking-[0.22em] text-[var(--reader-muted)]"
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
            class="bg-[color:var(--reader-surface)]/95 sticky top-14 z-30 mt-5 overflow-hidden rounded-2xl border border-[var(--reader-border)] shadow-lg shadow-black/5 backdrop-blur sm:top-16 sm:mt-7"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left sm:px-4"
                :aria-expanded="selectorsExpanded"
                @click="selectorsExpanded = !selectorsExpanded"
            >
                <span class="min-w-0">
                    <span
                        class="block text-xs font-bold uppercase text-[var(--reader-muted)]"
                    >
                        {{ t('library.bible.navigation') }}
                    </span>
                    <span class="block truncate text-sm font-semibold">
                        {{ selectedVersion?.abbreviation
                        }}<template v-if="verses[0]">
                            · {{ verses[0].book }} {{ chapter }}</template
                        >
                    </span>
                </span>
                <ChevronDown
                    class="size-5 shrink-0 transition-transform"
                    :class="{ 'rotate-180': selectorsExpanded }"
                />
            </button>

            <div
                v-show="selectorsExpanded"
                class="grid gap-3 border-t border-[var(--reader-border)] p-3 sm:p-4 md:grid-cols-[1.5fr_1fr_0.65fr_auto]"
            >
                <label
                    class="grid gap-1 text-xs font-bold text-[var(--reader-muted)]"
                >
                    {{ t('library.bible.version') }}
                    <select
                        v-model="version"
                        :disabled="!selectableVersions.length"
                        class="h-10 rounded-lg border border-[var(--reader-border)] bg-[var(--reader-bg)] px-3 text-sm text-[var(--reader-text)] disabled:opacity-50"
                        @change="changeVersion"
                    >
                        <option
                            v-if="!selectableVersions.length"
                            disabled
                            value=""
                        >
                            {{ t('library.bible.offline_no_versions') }}
                        </option>
                        <option
                            v-for="item in selectableVersions"
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
                    v-if="selectedVersionCanBeDownloaded"
                    type="button"
                    class="mt-auto inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[var(--church-primary,#6750a4)] px-3 text-xs font-bold text-white transition hover:brightness-110 disabled:cursor-default disabled:opacity-70"
                    :disabled="
                        offlineState === 'downloading' ||
                        !isOnline ||
                        selectedVersionOfflineReady
                    "
                    :title="t('library.bible.offline_download_description')"
                    @click="downloadSelectedVersionForOffline"
                >
                    <LoaderCircle
                        v-if="offlineState === 'downloading'"
                        class="size-4 animate-spin"
                    />
                    <CheckCircle2
                        v-else-if="selectedVersionOfflineReady"
                        class="size-4"
                    />
                    <CloudDownload v-else class="size-4" />
                    <span>
                        {{
                            selectedVersionOfflineReady
                                ? t('library.bible.offline_ready')
                                : t('library.bible.offline_download')
                        }}
                    </span>
                </button>
            </div>

            <div
                v-if="offlineState === 'downloading'"
                class="mx-3 mb-3 grid gap-1 sm:mx-4 sm:mb-4"
            >
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
                class="mx-3 mb-3 flex items-center gap-2 text-xs font-semibold text-rose-600 sm:mx-4 sm:mb-4"
            >
                <WifiOff class="size-4" />
                {{ t('library.bible.offline_error') }}
            </p>
            <p
                v-else-if="downloadableVersions.length < versions.length && selectorsExpanded"
                class="mx-3 mb-3 text-xs leading-5 text-[var(--reader-muted)] sm:mx-4 sm:mb-4"
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
        <article
            v-else-if="verses.length"
            class="mx-auto mt-7 max-w-3xl sm:mt-10"
        >
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
                        class="text-xs font-bold uppercase text-[var(--reader-muted)]"
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
                class="mt-6 font-serif text-[1.08rem] leading-8 sm:mt-8 sm:text-xl sm:leading-10"
            >
                <p class="text-pretty">
                    <span
                        v-for="item in verses"
                        :key="item.verse"
                        role="button"
                        tabindex="0"
                        class="mr-1.5 inline cursor-pointer rounded px-0.5 py-0.5 transition-[background-color,box-shadow] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--church-primary,#6750a4)]"
                        :class="{
                            'ring-2 ring-[var(--church-primary,#6750a4)] ring-offset-2 ring-offset-[var(--reader-bg)]':
                                selectedVerse === item.verse,
                        }"
                        :style="verseHighlightStyle(item.verse)"
                        :aria-label="
                            t('library.bible.highlight_verse', {
                                verse: item.verse,
                            })
                        "
                        @click="selectVerse(item.verse)"
                        @keydown.enter.prevent="selectVerse(item.verse)"
                        @keydown.space.prevent="selectVerse(item.verse)"
                    >
                        <sup
                            class="mr-1.5 font-sans text-xs font-black text-[var(--church-primary,#6750a4)]"
                        >
                            {{ item.verse }}
                        </sup>
                        {{ item.text }}
                    </span>
                </p>
            </div>

            <aside
                v-if="selectedVerse !== null"
                class="sticky bottom-[calc(var(--pwa-bottom-navigation-height,0px)+0.75rem)] z-20 mt-6 rounded-2xl border border-[var(--reader-border)] bg-[var(--reader-surface)] p-3 shadow-xl shadow-black/10 sm:bottom-4 sm:p-4"
                data-test="bible-highlight-toolbar"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 text-sm font-bold">
                            <Highlighter
                                class="size-4 text-[var(--church-primary,#6750a4)]"
                            />
                            {{
                                t('library.bible.highlight_verse', {
                                    verse: selectedVerse,
                                })
                            }}
                        </p>
                        <p
                            class="mt-1 text-xs leading-5 text-[var(--reader-muted)]"
                        >
                            {{ t('library.bible.highlight_saved_local') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-full p-1.5 text-[var(--reader-muted)] transition hover:bg-[var(--reader-border)]"
                        :title="t('library.bible.highlight_close')"
                        @click="selectedVerse = null"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        v-for="paletteItem in highlightPalette"
                        :key="paletteItem.color"
                        type="button"
                        class="size-9 rounded-full border-2 transition hover:scale-105"
                        :class="
                            selectedVerseHighlight === paletteItem.color
                                ? 'border-[var(--church-primary,#6750a4)] ring-2 ring-[var(--church-primary,#6750a4)]/25'
                                : 'border-[var(--reader-border)]'
                        "
                        :style="{
                            backgroundColor: paletteItem.backgroundColor,
                        }"
                        :title="
                            highlightColorLabel(paletteItem.color)
                        "
                        :aria-label="
                            highlightColorLabel(paletteItem.color)
                        "
                        @click="setVerseHighlight(paletteItem.color)"
                    />
                    <button
                        v-if="selectedVerseHighlight"
                        type="button"
                        class="ml-auto inline-flex h-9 items-center gap-2 rounded-full border border-[var(--reader-border)] px-3 text-xs font-bold text-[var(--reader-muted)] transition hover:text-rose-600"
                        @click="removeVerseHighlight"
                    >
                        <Eraser class="size-4" />
                        {{ t('library.bible.highlight_remove') }}
                    </button>
                </div>
            </aside>

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
