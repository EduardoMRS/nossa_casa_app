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
    chapter: string | number;
    verse: string | number;
    content: string;
    version: string;
    has_record: boolean;
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
}>();
const { t } = useI18n();
const verseOpen = ref(false);
const libraryOpen = ref(false);
const categoriesOpen = ref(false);
const editing = ref<LibraryItem | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const inputType = ref<'url' | 'file'>('file');
const selectedFile = ref<File | null>(null);
const localPreview = ref('');
const verseForm = reactive({
    book: props.verse.book,
    chapter: String(props.verse.chapter || ''),
    verse: String(props.verse.verse || ''),
    content: props.verse.content,
    version: props.verse.version,
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
            `/admin/biblioteca-versiculo/library/${editing.value.id}`,
            payload,
            options,
        );
    } else {
        router.post('/admin/biblioteca-versiculo/library', payload, options);
    }
};
const saveVerse = (): void => {
    processing.value = true;
    router.put(
        '/admin/biblioteca-versiculo/verse',
        {
            ...verseForm,
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
        router.delete(`/admin/biblioteca-versiculo/library/${item.id}`, {
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
                    @click="verseOpen = true"
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
                                <option value="url">URL</option>
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
                    <label class="block text-xs font-bold"
                        >{{ t('admin.library.verse_text')
                        }}<textarea
                            v-model="verseForm.content"
                            required
                            rows="5"
                            class="mt-1 w-full rounded-lg border-slate-300"
                        />
                    </label>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="text-xs font-bold"
                            >{{ t('admin.library.book')
                            }}<input
                                v-model="verseForm.book"
                                required
                                class="mt-1 w-full rounded-lg border-slate-300" /></label
                        ><label class="text-xs font-bold"
                            >{{ t('admin.library.chapter')
                            }}<input
                                v-model="verseForm.chapter"
                                required
                                type="number"
                                min="1"
                                class="mt-1 w-full rounded-lg border-slate-300" /></label
                        ><label class="text-xs font-bold"
                            >{{ t('admin.library.verse')
                            }}<input
                                v-model="verseForm.verse"
                                required
                                type="number"
                                min="1"
                                class="mt-1 w-full rounded-lg border-slate-300"
                        /></label>
                    </div>
                    <label class="block text-xs font-bold"
                        >{{ t('admin.library.version')
                        }}<input
                            v-model="verseForm.version"
                            required
                            class="mt-1 w-full rounded-lg border-slate-300" /></label
                    ><button
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
