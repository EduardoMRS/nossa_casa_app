<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BookOpen,
    FileText,
    Pencil,
    Plus,
    Sparkles,
    Tags,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import CategoryManagerModal from '@/components/CategoryManagerModal.vue';
import type { ManagedCategory } from '@/components/CategoryManagerModal.vue';
import CategorySelector from '@/components/CategorySelector.vue';
import { useI18n } from '@/lib/i18n';

type VerseData = {
    book: string;
    book_name: string;
    chapter: string | number;
    verse: string | number;
    content: string;
    version: string;
    has_record: boolean;
};
type BibleVersion = {
    id: string;
    name: string;
    abbreviation: string;
    language: string;
    scope: string;
    copyright: string;
};
type BibleBook = { slug: string; name: string };
type BibleVerse = { verse: number; text: string };
type BibleSettings = {
    catalog: BibleVersion[];
    versions: string[];
    default_version: string | null;
    community_versions: string[];
    community_default_version: string | null;
    available: boolean;
    can_manage_community: boolean;
};
type LibraryItem = {
    id: string;
    title: string;
    description: string | null;
    type: string;
    file_path: string | null;
    preview_url: string | null;
    category_ids: string[];
};
const props = defineProps<{
    verse: VerseData;
    libraries: LibraryItem[];
    categories: ManagedCategory[];
    bible: BibleSettings;
}>();
const { t } = useI18n();
const verseOpen = ref(false);
const libraryOpen = ref(false);
const categoriesOpen = ref(false);
const editing = ref<LibraryItem | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const bibleLoading = ref(false);
const bibleSearch = ref('');
const verseBooks = ref<BibleBook[]>([]);
const verseChapters = ref<number[]>([]);
const verseOptions = ref<BibleVerse[]>([]);
const communityVersions = ref([...props.bible.community_versions]);
const communityDefault = ref(props.bible.community_default_version ?? '');
const churchVersions = ref([...props.bible.versions]);
const churchDefault = ref(props.bible.default_version ?? '');
const inputType = ref<'url' | 'file'>('file');
const selectedFile = ref<File | null>(null);
const localPreview = ref('');
const verseForm = reactive({
    book: props.verse.book,
    chapter: String(props.verse.chapter || ''),
    verse: String(props.verse.verse || ''),
    version: props.verse.version || props.bible.default_version || '',
});
const form = reactive({
    title: '',
    description: '',
    type: '',
    file_path: '',
    category_ids: [] as string[],
});
const preview = computed(
    () =>
        localPreview.value ||
        (inputType.value === 'url' ? form.file_path : '') ||
        editing.value?.preview_url ||
        '',
);
const filteredBibleCatalog = computed(() => {
    const query = bibleSearch.value.trim().toLocaleLowerCase();

    return props.bible.catalog.filter((version) =>
        query
            ? [version.name, version.abbreviation, version.language]
                  .join(' ')
                  .toLocaleLowerCase()
                  .includes(query)
            : true,
    );
});
const communityCatalog = computed(() =>
    props.bible.catalog.filter((version) =>
        communityVersions.value.includes(version.id),
    ),
);
const selectedVerseText = computed(
    () =>
        verseOptions.value.find(
            (item) => item.verse === Number(verseForm.verse),
        )?.text ?? props.verse.content,
);

const getJson = async <T,>(url: string): Promise<T> => {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(t('admin.library.bible.load_error'));
    }

    return response.json() as Promise<T>;
};

const loadVerseChapter = async (): Promise<void> => {
    if (!verseForm.version || !verseForm.book || !verseForm.chapter) {
        verseOptions.value = [];

        return;
    }

    const payload = await getJson<{ verses: BibleVerse[] }>(
        `/api/bible/${encodeURIComponent(verseForm.version)}/books/${encodeURIComponent(verseForm.book)}/chapters/${verseForm.chapter}`,
    );
    verseOptions.value = payload.verses;

    if (
        !payload.verses.some((item) => item.verse === Number(verseForm.verse))
    ) {
        verseForm.verse = String(payload.verses[0]?.verse ?? '');
    }
};

const loadVerseChapters = async (): Promise<void> => {
    const payload = await getJson<{ chapters: number[] }>(
        `/api/bible/${encodeURIComponent(verseForm.version)}/books/${encodeURIComponent(verseForm.book)}/chapters`,
    );
    verseChapters.value = payload.chapters;

    if (!payload.chapters.includes(Number(verseForm.chapter))) {
        verseForm.chapter = String(payload.chapters[0] ?? '');
    }

    await loadVerseChapter();
};

const loadVerseBooks = async (): Promise<void> => {
    bibleLoading.value = true;
    errors.value = {};

    try {
        const payload = await getJson<{ books: BibleBook[] }>(
            `/api/bible/${encodeURIComponent(verseForm.version)}/books`,
        );
        verseBooks.value = payload.books;

        if (!payload.books.some((item) => item.slug === verseForm.book)) {
            verseForm.book = payload.books[0]?.slug ?? '';
        }

        await loadVerseChapters();
    } catch {
        errors.value = { bible: t('admin.library.bible.load_error') };
    } finally {
        bibleLoading.value = false;
    }
};

const openVerse = (): void => {
    verseOpen.value = true;
    void loadVerseBooks();
};

const changeVerseBook = async (): Promise<void> => {
    bibleLoading.value = true;

    try {
        await loadVerseChapters();
    } finally {
        bibleLoading.value = false;
    }
};

const changeVerseChapter = async (): Promise<void> => {
    bibleLoading.value = true;

    try {
        await loadVerseChapter();
    } finally {
        bibleLoading.value = false;
    }
};

const saveBiblePreferences = (scope: 'community' | 'church'): void => {
    const versions =
        scope === 'community' ? communityVersions.value : churchVersions.value;
    const defaultVersion =
        scope === 'community' ? communityDefault.value : churchDefault.value;

    router.put(
        '/dashboard/biblioteca-versiculo/bible',
        {
            scope,
            versions,
            default_version: defaultVersion,
        },
        { preserveScroll: true },
    );
};
const revokePreview = (): void => {
    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value);
    }

    localPreview.value = '';
};
const openLibrary = (item: LibraryItem | null = null): void => {
    editing.value = item;
    form.title = item?.title ?? '';
    form.description = item?.description ?? '';
    form.type = item?.type ?? '';
    form.file_path = item?.file_path?.startsWith('http') ? item.file_path : '';
    form.category_ids = [...(item?.category_ids ?? [])];
    inputType.value = item?.file_path?.startsWith('http') ? 'url' : 'file';
    selectedFile.value = null;
    errors.value = {};
    revokePreview();
    libraryOpen.value = true;
};
const chooseFile = (event: Event): void => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    selectedFile.value = file;
    revokePreview();

    if (file) {
        localPreview.value = URL.createObjectURL(file);
    }
};
const saveLibrary = (): void => {
    processing.value = true;
    const payload: Record<string, any> = {
        title: form.title,
        description: form.description,
        type: form.type,
        category_ids: form.category_ids,
    };

    if (inputType.value === 'file' && selectedFile.value) {
        payload.file_path = selectedFile.value;
    }

    if (inputType.value === 'url') {
        payload.file_path = form.file_path;
    }

    const options = {
        forceFormData: true,
        preserveScroll: true,
        onError: (value: Record<string, string>) => {
            errors.value = value;
        },
        onSuccess: () => {
            libraryOpen.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    if (editing.value) {
        router.put(
            `/dashboard/biblioteca-versiculo/library/${editing.value.id}`,
            payload,
            options,
        );
    } else {
        router.post(
            '/dashboard/biblioteca-versiculo/library',
            payload,
            options,
        );
    }
};
const saveVerse = (): void => {
    processing.value = true;
    router.put(
        '/dashboard/biblioteca-versiculo/verse',
        {
            version: verseForm.version,
            book: verseForm.book,
            chapter: Number(verseForm.chapter),
            verse: Number(verseForm.verse),
        },
        {
            preserveScroll: true,
            onError: (value) => {
                errors.value = value;
            },
            onSuccess: () => {
                verseOpen.value = false;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
const remove = (item: LibraryItem): void => {
    if (confirm(t('admin.library.delete_confirm', { title: item.title }))) {
        router.delete(`/dashboard/biblioteca-versiculo/library/${item.id}`, {
            preserveScroll: true,
        });
    }
};
onBeforeUnmount(revokePreview);
</script>

<template>
    <Head :title="t('admin.library.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="flex flex-col justify-between gap-4 rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white md:flex-row md:items-end"
        >
            <div>
                <p
                    class="font-mono text-[10px] font-bold tracking-widest text-amber-300 uppercase"
                >
                    {{ t('admin.library.collection') }}
                </p>
                <h1 class="mt-2 text-3xl font-black">
                    {{ t('admin.library.heading') }}
                </h1>
                <p class="mt-2 max-w-3xl text-sm text-indigo-100">
                    {{ t('admin.library.description') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    class="inline-flex items-center gap-2 rounded-xl border border-white/30 px-4 py-3 text-sm font-black"
                    :disabled="!bible.available || !bible.versions.length"
                    @click="openVerse"
                >
                    <Sparkles class="size-4" />{{
                        t('admin.library.verse_of_day')
                    }}</button
                ><button
                    class="inline-flex items-center gap-2 rounded-xl border border-white/30 px-4 py-3 text-sm font-black"
                    @click="categoriesOpen = true"
                >
                    <Tags class="size-4" />{{
                        t('admin.categories.title')
                    }}</button
                ><button
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-black text-indigo-950"
                    @click="openLibrary()"
                >
                    <Plus class="size-4" />{{ t('admin.library.new_item') }}
                </button>
            </div>
        </header>

        <section class="grid gap-5 xl:grid-cols-2">
            <article class="rounded-2xl border bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-black">
                            {{ t('admin.library.bible.community_title') }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ t('admin.library.bible.community_description') }}
                        </p>
                    </div>
                    <BookOpen class="size-5 text-indigo-600" />
                </div>

                <template v-if="bible.can_manage_community">
                    <input
                        v-model="bibleSearch"
                        class="mt-4 w-full rounded-lg border-slate-300 text-sm"
                        :placeholder="t('admin.library.bible.search_versions')"
                    />
                    <div
                        class="mt-3 max-h-56 space-y-2 overflow-y-auto rounded-xl border p-3"
                    >
                        <label
                            v-for="version in filteredBibleCatalog"
                            :key="version.id"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input
                                v-model="communityVersions"
                                type="checkbox"
                                :value="version.id"
                                class="mt-1 rounded border-slate-300"
                            />
                            <span>
                                <strong>{{ version.abbreviation }}</strong>
                                — {{ version.name }}
                                <small class="block text-slate-500">
                                    {{ version.language }} · {{ version.scope }}
                                </small>
                                <small
                                    v-if="version.copyright"
                                    class="block text-slate-400"
                                >
                                    {{ version.copyright }}
                                </small>
                            </span>
                        </label>
                    </div>
                    <label class="mt-4 block text-xs font-bold">
                        {{ t('admin.library.bible.default_version') }}
                        <select
                            v-model="communityDefault"
                            class="mt-1 w-full rounded-lg border-slate-300"
                        >
                            <option
                                v-for="version in bible.catalog.filter((item) =>
                                    communityVersions.includes(item.id),
                                )"
                                :key="version.id"
                                :value="version.id"
                            >
                                {{ version.abbreviation }} — {{ version.name }}
                            </option>
                        </select>
                    </label>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-black text-white"
                        @click="saveBiblePreferences('community')"
                    >
                        {{ t('admin.library.bible.save_community') }}
                    </button>
                </template>
                <div v-else class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-for="version in communityCatalog"
                        :key="version.id"
                        class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700"
                    >
                        {{ version.abbreviation }} · {{ version.language }}
                    </span>
                </div>
            </article>

            <article class="rounded-2xl border bg-white p-5 shadow-sm">
                <h2 class="font-black">
                    {{ t('admin.library.bible.church_title') }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ t('admin.library.bible.church_description') }}
                </p>
                <div class="mt-4 space-y-2 rounded-xl border p-3">
                    <label
                        v-for="version in communityCatalog"
                        :key="version.id"
                        class="flex items-start gap-2 text-sm"
                    >
                        <input
                            v-model="churchVersions"
                            type="checkbox"
                            :value="version.id"
                            class="mt-1 rounded border-slate-300"
                        />
                        <span>
                            <strong>{{ version.abbreviation }}</strong>
                            — {{ version.name }}
                            <small class="block text-slate-500">
                                {{ version.language }} · {{ version.scope }}
                            </small>
                            <small
                                v-if="version.copyright"
                                class="block text-slate-400"
                            >
                                {{ version.copyright }}
                            </small>
                        </span>
                    </label>
                </div>
                <label class="mt-4 block text-xs font-bold">
                    {{ t('admin.library.bible.default_version') }}
                    <select
                        v-model="churchDefault"
                        class="mt-1 w-full rounded-lg border-slate-300"
                    >
                        <option
                            v-for="version in communityCatalog.filter((item) =>
                                churchVersions.includes(item.id),
                            )"
                            :key="version.id"
                            :value="version.id"
                        >
                            {{ version.abbreviation }} — {{ version.name }}
                        </option>
                    </select>
                </label>
                <button
                    type="button"
                    class="mt-4 w-full rounded-lg bg-slate-950 px-4 py-3 text-sm font-black text-white"
                    @click="saveBiblePreferences('church')"
                >
                    {{ t('admin.library.bible.save_church') }}
                </button>
            </article>
        </section>

        <section
            class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <article
                v-for="item in libraries"
                :key="item.id"
                class="overflow-hidden rounded-2xl border bg-white shadow-sm"
            >
                <button
                    class="grid aspect-[4/3] w-full place-items-center overflow-hidden bg-indigo-50"
                    @click="openLibrary(item)"
                >
                    <img
                        v-if="
                            item.preview_url?.match(
                                /\.(png|jpe?g|webp|gif|svg)(\?.*)?$/i,
                            )
                        "
                        :src="item.preview_url"
                        class="h-full w-full object-cover"
                    /><BookOpen v-else class="size-16 text-indigo-300" />
                </button>
                <div class="p-4">
                    <span
                        class="rounded-full bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-700"
                        >{{ item.type }}</span
                    >
                    <h2 class="mt-3 line-clamp-2 font-black">
                        {{ item.title }}
                    </h2>
                    <p
                        class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500"
                    >
                        {{
                            item.description ||
                            t('admin.library.no_description')
                        }}
                    </p>
                    <div class="mt-4 flex gap-2">
                        <button
                            class="flex-1 rounded-lg border px-3 py-2 text-xs font-bold text-indigo-700"
                            @click="openLibrary(item)"
                        >
                            <Pencil class="mr-1 inline size-3" />{{
                                t('actions.edit')
                            }}</button
                        ><button
                            class="rounded-lg border px-3 py-2 text-rose-600"
                            @click="remove(item)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
            </article>
            <button
                class="grid min-h-64 place-items-center rounded-2xl border-2 border-dashed border-slate-300 bg-white text-sm font-bold text-slate-500 hover:border-indigo-400 hover:text-indigo-700"
                @click="openLibrary()"
            >
                <span class="flex flex-col items-center gap-3"
                    ><Plus class="size-8" />{{
                        t('admin.library.new_item')
                    }}</span
                >
            </button>
        </section>

        <div
            v-if="libraryOpen"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
            @click.self="libraryOpen = false"
        >
            <form
                class="my-6 w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl"
                @submit.prevent="saveLibrary"
            >
                <header
                    class="flex items-center justify-between border-b px-5 py-4"
                >
                    <h2 class="text-xl font-black">
                        {{
                            editing
                                ? t('admin.library.edit_item')
                                : t('admin.library.new_item')
                        }}
                    </h2>
                    <button
                        type="button"
                        class="p-2"
                        @click="libraryOpen = false"
                    >
                        <X class="size-5" />
                    </button>
                </header>
                <div class="grid gap-6 p-5 lg:grid-cols-2">
                    <div
                        class="grid min-h-72 place-items-center overflow-hidden rounded-xl border bg-indigo-50"
                    >
                        <img
                            v-if="
                                preview?.match(
                                    /\.(png|jpe?g|webp|gif|svg)(\?.*)?$/i,
                                )
                            "
                            :src="preview"
                            class="max-h-[28rem] w-full object-contain"
                        /><FileText v-else class="size-20 text-indigo-300" />
                    </div>
                    <div class="space-y-4">
                        <label class="block text-xs font-bold"
                            >{{ t('admin.common.title')
                            }}<input
                                v-model="form.title"
                                required
                                class="mt-1 w-full rounded-lg border-slate-300" /></label
                        ><label class="block text-xs font-bold"
                            >{{ t('admin.library.category_type')
                            }}<input
                                v-model="form.type"
                                required
                                class="mt-1 w-full rounded-lg border-slate-300" /></label
                        ><label class="block text-xs font-bold"
                            >{{ t('admin.common.description')
                            }}<textarea
                                v-model="form.description"
                                rows="4"
                                class="mt-1 w-full rounded-lg border-slate-300"
                            /></label
                        ><label class="block text-xs font-bold"
                            >{{ t('admin.library.file_type')
                            }}<select
                                v-model="inputType"
                                class="mt-1 w-full rounded-lg border-slate-300"
                            >
                                <option value="file">
                                    {{ t('gallery.file') }}
                                </option>
                                <option value="url">
                                    {{ t('admin.common.url') }}
                                </option>
                            </select></label
                        ><input
                            v-if="inputType === 'file'"
                            type="file"
                            class="w-full rounded-lg border p-2 text-sm"
                            @change="chooseFile"
                        /><input
                            v-else
                            v-model="form.file_path"
                            class="w-full rounded-lg border-slate-300"
                        /><CategorySelector
                            v-model="form.category_ids"
                            :categories="categories"
                            :label="t('admin.library.categories')"
                        />
                        <p
                            v-for="message in errors"
                            :key="message"
                            class="text-xs text-rose-600"
                        >
                            {{ message }}
                        </p>
                        <button
                            :disabled="processing"
                            class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-black text-white"
                        >
                            {{
                                processing
                                    ? t('admin.common.saving')
                                    : t('admin.common.save')
                            }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div
            v-if="verseOpen"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
            @click.self="verseOpen = false"
        >
            <form
                class="my-6 w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl"
                @submit.prevent="saveVerse"
            >
                <header class="mb-5 flex items-center justify-between">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-emerald-600 uppercase"
                        >
                            {{ t('admin.library.devotional_home') }}
                        </p>
                        <h2 class="text-xl font-black">
                            {{ t('admin.library.verse_of_day') }}
                        </h2>
                    </div>
                    <button type="button" @click="verseOpen = false">
                        <X class="size-5" />
                    </button>
                </header>
                <div class="space-y-4">
                    <label class="block text-xs font-bold">
                        {{ t('admin.library.version') }}
                        <select
                            v-model="verseForm.version"
                            required
                            class="mt-1 w-full rounded-lg border-slate-300"
                            @change="loadVerseBooks"
                        >
                            <option
                                v-for="version in bible.catalog.filter((item) =>
                                    bible.versions.includes(item.id),
                                )"
                                :key="version.id"
                                :value="version.id"
                            >
                                {{ version.abbreviation }} — {{ version.name }}
                            </option>
                        </select>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="text-xs font-bold"
                            >{{ t('admin.library.book')
                            }}<select
                                v-model="verseForm.book"
                                required
                                :disabled="bibleLoading"
                                class="mt-1 w-full rounded-lg border-slate-300"
                                @change="changeVerseBook"
                            >
                                <option
                                    v-for="book in verseBooks"
                                    :key="book.slug"
                                    :value="book.slug"
                                >
                                    {{ book.name }}
                                </option>
                            </select></label
                        ><label class="text-xs font-bold"
                            >{{ t('admin.library.chapter')
                            }}<select
                                v-model="verseForm.chapter"
                                required
                                :disabled="bibleLoading"
                                class="mt-1 w-full rounded-lg border-slate-300"
                                @change="changeVerseChapter"
                            >
                                <option
                                    v-for="chapter in verseChapters"
                                    :key="chapter"
                                    :value="String(chapter)"
                                >
                                    {{ chapter }}
                                </option>
                            </select></label
                        ><label class="text-xs font-bold"
                            >{{ t('admin.library.verse')
                            }}<select
                                v-model="verseForm.verse"
                                required
                                class="mt-1 w-full rounded-lg border-slate-300"
                            >
                                <option
                                    v-for="verse in verseOptions"
                                    :key="verse.verse"
                                    :value="String(verse.verse)"
                                >
                                    {{ verse.verse }}
                                </option>
                            </select></label
                        >
                    </div>
                    <div
                        class="rounded-xl bg-emerald-50 p-4 text-sm leading-6 text-emerald-950"
                    >
                        <p
                            class="text-xs font-black text-emerald-700 uppercase"
                        >
                            {{ t('admin.library.verse_text') }}
                        </p>
                        <p class="mt-2">{{ selectedVerseText }}</p>
                    </div>
                    <p
                        v-for="message in errors"
                        :key="message"
                        class="text-xs font-bold text-rose-600"
                    >
                        {{ message }}
                    </p>
                    <button
                        :disabled="processing"
                        class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-black text-white"
                    >
                        {{ t('admin.library.update_verse') }}
                    </button>
                </div>
            </form>
        </div>
        <CategoryManagerModal
            :open="categoriesOpen"
            category-type="library"
            :categories="categories"
            @close="categoriesOpen = false"
        />
    </main>
</template>
