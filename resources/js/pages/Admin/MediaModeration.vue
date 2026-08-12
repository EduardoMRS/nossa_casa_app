<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Check,
    Image,
    Pencil,
    Plus,
    Tags,
    Trash2,
    Upload,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import CategoryManagerModal from '@/components/CategoryManagerModal.vue';
import type { ManagedCategory } from '@/components/CategoryManagerModal.vue';
import CategorySelector from '@/components/CategorySelector.vue';
import { useI18n } from '@/lib/i18n';

type MediaItem = {
    id: string;
    title: string | null;
    description: string | null;
    file_path: string | null;
    preview_url: string | null;
    mimetype: string | null;
    size: number | null;
    gallery: boolean;
    status: string;
    created_at: string | null;
    uploader: { first_name: string; last_name: string } | null;
    category_ids: string[];
};
type ActionItem = { label: string; href: string };
type StatItem = { label: string; value: number };
defineProps<{
    title: string;
    subtitle: string;
    description: string;
    stats: StatItem[];
    actions: ActionItem[];
    media: MediaItem[];
    categories: ManagedCategory[];
}>();
const { t } = useI18n();
const editorOpen = ref(false);
const categoriesOpen = ref(false);
const selected = ref<MediaItem | null>(null);
const sourceType = ref<'url' | 'file'>('file');
const fileUrl = ref('');
const selectedFile = ref<File | null>(null);
const localPreview = ref('');
const mediaTitle = ref('');
const mediaDescription = ref('');
const gallery = ref(true);
const status = ref('pending');
const categoryIds = ref<string[]>([]);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const preview = computed(
    () =>
        localPreview.value ||
        (sourceType.value === 'url' ? fileUrl.value : '') ||
        selected.value?.preview_url ||
        '',
);
const previewKind = computed<'image' | 'video' | 'pdf'>(() => {
    const type = selectedFile.value?.type || selected.value?.mimetype || '';

    if (type.startsWith('video/')) {
        return 'video';
    }

    if (type === 'application/pdf' || preview.value.match(/\.pdf(\?.*)?$/i)) {
        return 'pdf';
    }

    return 'image';
});

const revokePreview = (): void => {
    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value);
    }

    localPreview.value = '';
};
const openUpload = (): void => {
    selected.value = null;
    sourceType.value = 'file';
    fileUrl.value = '';
    selectedFile.value = null;
    mediaTitle.value = '';
    mediaDescription.value = '';
    gallery.value = true;
    status.value = 'pending';
    categoryIds.value = [];
    errors.value = {};
    revokePreview();
    editorOpen.value = true;
};
const openEditor = (item: MediaItem): void => {
    selected.value = item;
    sourceType.value = item.file_path?.startsWith('http') ? 'url' : 'file';
    fileUrl.value = item.file_path?.startsWith('http') ? item.file_path : '';
    selectedFile.value = null;
    mediaTitle.value = item.title ?? '';
    mediaDescription.value = item.description ?? '';
    gallery.value = item.gallery;
    status.value = item.status;
    categoryIds.value = [...item.category_ids];
    errors.value = {};
    revokePreview();
    editorOpen.value = true;
};
const chooseFile = (event: Event): void => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    selectedFile.value = file;
    revokePreview();

    if (file) {
        localPreview.value = URL.createObjectURL(file);
    }
};
const save = (): void => {
    processing.value = true;
    const payload: Record<string, any> = {
        title: mediaTitle.value || null,
        description: mediaDescription.value || null,
        gallery: gallery.value,
        status: status.value,
        category_ids: categoryIds.value,
    };

    if (!selected.value) {
        if (sourceType.value === 'url' && fileUrl.value.trim()) {
            payload.file_path = fileUrl.value.trim();
        }

        if (sourceType.value === 'file' && selectedFile.value) {
            payload.file = selectedFile.value;
        }
    }

    const options = {
        forceFormData: true,
        preserveScroll: true,
        onError: (value: Record<string, string>) => {
            errors.value = value;
        },
        onSuccess: () => {
            editorOpen.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    if (selected.value) {
        router.put(`/api/media/${selected.value.id}`, payload, options);
    } else {
        router.post('/api/media', payload, options);
    }
};
const remove = (item: MediaItem): void => {
    if (confirm(t('admin.media.delete_confirm'))) {
        router.delete(`/api/media/${item.id}`, { preserveScroll: true });
    }
};
const moderate = (item: MediaItem, value: 'approved' | 'rejected'): void =>
    router.put(
        `/api/admin/media/${item.id}/status`,
        { status: value },
        { preserveScroll: true },
    );
onBeforeUnmount(revokePreview);
</script>

<template>
    <Head :title="t('admin.media.title')" />
    <main class="min-h-screen bg-background p-4 text-foreground md:p-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header
                class="flex flex-col justify-between gap-4 rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white md:flex-row md:items-end"
            >
                <div>
                    <p
                        class="font-mono text-[10px] font-bold tracking-widest text-cyan-200 uppercase"
                    >
                        {{ t('admin.media.subtitle') }}
                    </p>
                    <h1 class="mt-2 text-3xl font-black">
                        {{ t('admin.media.title') }}
                    </h1>
                    <p class="mt-2 max-w-3xl text-sm text-indigo-100">
                        {{ t('admin.media.description') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        class="inline-flex items-center gap-2 rounded-xl border border-white/30 px-4 py-3 text-sm font-black"
                        @click="categoriesOpen = true"
                    >
                        <Tags class="size-4" />{{
                            t('admin.categories.title')
                        }}</button
                    ><button
                        class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-black text-indigo-950"
                        @click="openUpload"
                    >
                        <Upload class="size-4" />{{ t('admin.media.new') }}
                    </button>
                </div>
            </header>
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="stat in stats"
                    :key="stat.label"
                    class="rounded-xl border bg-white p-4 shadow-sm"
                >
                    <p class="text-xs font-bold text-slate-400 uppercase">
                        {{ stat.label }}
                    </p>
                    <p class="mt-2 text-3xl font-black">{{ stat.value }}</p>
                </article>
            </section>
            <section
                class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <article
                    v-for="item in media"
                    :key="item.id"
                    class="group overflow-hidden rounded-2xl border bg-white shadow-sm"
                >
                    <button
                        class="relative block aspect-square w-full overflow-hidden bg-slate-100"
                        @click="openEditor(item)"
                    >
                        <video
                            v-if="
                                item.preview_url &&
                                item.mimetype?.startsWith('video/')
                            "
                            :src="item.preview_url"
                            muted
                            class="h-full w-full object-cover"
                        />
                        <img
                            v-else-if="item.preview_url"
                            :src="item.preview_url"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                        /><Image
                            v-else
                            class="absolute inset-0 m-auto size-10 text-slate-300"
                        /><span
                            class="absolute top-3 left-3 rounded-full bg-slate-950/75 px-2.5 py-1 text-[10px] font-bold text-white uppercase"
                            >{{ t(`admin.media.status.${item.status}`) }}</span
                        ><span
                            v-if="item.gallery"
                            class="absolute top-3 right-3 rounded-full bg-emerald-500 p-1.5 text-white"
                            ><Check class="size-3"
                        /></span>
                    </button>
                    <div class="p-4">
                        <p class="truncate text-sm font-black">
                            {{ item.file_path }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ item.uploader?.first_name }}
                            {{ item.uploader?.last_name }}
                        </p>
                        <div class="mt-4 flex gap-2">
                            <button
                                class="flex-1 rounded-lg border px-3 py-2 text-xs font-bold text-indigo-700"
                                @click="openEditor(item)"
                            >
                                <Pencil class="mr-1 inline size-3" />{{
                                    t('actions.edit')
                                }}</button
                            ><button
                                class="rounded-lg border px-3 py-2 text-xs font-bold text-emerald-700"
                                @click="moderate(item, 'approved')"
                            >
                                {{ t('admin.media.approve') }}</button
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
                    @click="openUpload"
                >
                    <span class="flex flex-col items-center gap-3"
                        ><Plus class="size-8" />{{ t('admin.media.new') }}</span
                    >
                </button>
            </section>
        </div>

        <div
            v-if="editorOpen"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
            @click.self="editorOpen = false"
        >
            <form
                class="my-6 w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl"
                @submit.prevent="save"
            >
                <header
                    class="flex items-center justify-between border-b px-5 py-4"
                >
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-indigo-600 uppercase"
                        >
                            {{
                                selected
                                    ? t('admin.media.edit')
                                    : t('admin.media.new')
                            }}
                        </p>
                        <h2 class="text-xl font-black">
                            {{ selected?.file_path ?? t('admin.media.crud') }}
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 hover:bg-slate-100"
                        @click="editorOpen = false"
                    >
                        <X class="size-5" />
                    </button>
                </header>
                <div class="grid gap-6 p-5 lg:grid-cols-2">
                    <div
                        class="grid min-h-72 place-items-center overflow-hidden rounded-xl border bg-slate-100"
                    >
                        <video
                            v-if="preview && previewKind === 'video'"
                            :src="preview"
                            controls
                            class="max-h-[28rem] w-full"
                        />
                        <iframe
                            v-else-if="preview && previewKind === 'pdf'"
                            :src="preview"
                            class="h-[28rem] w-full"
                        />
                        <img
                            v-else-if="preview"
                            :src="preview"
                            class="max-h-[28rem] w-full object-contain"
                        /><Image v-else class="size-16 text-slate-300" />
                    </div>
                    <div class="space-y-4">
                        <div
                            v-if="selected"
                            class="rounded-xl border border-amber-200 bg-amber-50 p-3"
                        >
                            <p class="text-xs font-black text-amber-950">
                                {{ t('admin.media.original_source') }}
                            </p>
                            <p class="mt-1 text-xs break-all text-amber-900">
                                {{ selected.file_path }}
                            </p>
                            <p class="mt-2 text-xs text-amber-800">
                                {{ t('admin.media.source_locked') }}
                            </p>
                            <dl
                                class="mt-3 grid grid-cols-2 gap-2 text-[11px] text-amber-950"
                            >
                                <div>
                                    <dt class="font-bold">MIME</dt>
                                    <dd>{{ selected.mimetype || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-bold">
                                        {{ t('admin.media.size') }}
                                    </dt>
                                    <dd>{{ selected.size ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                        <template v-else>
                            <label class="block text-xs font-bold"
                                >{{ t('admin.media.source')
                                }}<select
                                    v-model="sourceType"
                                    class="mt-1 w-full rounded-lg border-slate-300 text-sm"
                                >
                                    <option value="file">
                                        {{ t('gallery.file') }}
                                    </option>
                                    <option value="url">URL</option>
                                </select></label
                            ><label class="block text-xs font-bold"
                                >{{ t('admin.media.file_url')
                                }}<input
                                    v-if="sourceType === 'file'"
                                    type="file"
                                    accept="image/*,video/*,application/pdf"
                                    class="mt-1 w-full rounded-lg border p-2 text-sm"
                                    @change="chooseFile" /><input
                                    v-else
                                    v-model="fileUrl"
                                    class="mt-1 w-full rounded-lg border-slate-300 text-sm"
                            /></label>
                        </template>
                        <p
                            v-for="message in errors"
                            :key="message"
                            class="text-xs text-rose-600"
                        >
                            {{ message }}
                        </p>
                        <label class="block text-xs font-bold"
                            >{{ t('admin.media.record_title')
                            }}<input
                                v-model="mediaTitle"
                                maxlength="255"
                                class="mt-1 w-full rounded-lg border-slate-300 text-sm"
                        /></label>
                        <label class="block text-xs font-bold"
                            >{{ t('admin.media.record_description')
                            }}<textarea
                                v-model="mediaDescription"
                                maxlength="5000"
                                rows="4"
                                class="mt-1 w-full rounded-lg border-slate-300 text-sm"
                            />
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block text-xs font-bold"
                                >{{ t('admin.common.status')
                                }}<select
                                    v-model="status"
                                    class="mt-1 w-full rounded-lg border-slate-300 text-sm"
                                >
                                    <option value="pending">
                                        {{ t('admin.media.status.pending') }}
                                    </option>
                                    <option value="approved">
                                        {{ t('admin.media.status.approved') }}
                                    </option>
                                    <option value="rejected">
                                        {{ t('admin.media.status.rejected') }}
                                    </option>
                                </select></label
                            ><label
                                class="flex items-center gap-2 self-end rounded-lg border p-2.5 text-xs font-bold"
                                ><input v-model="gallery" type="checkbox" />{{
                                    t('admin.media.public_gallery')
                                }}</label
                            >
                        </div>
                        <CategorySelector
                            v-model="categoryIds"
                            :categories="categories"
                            :label="t('gallery.categories')"
                        />
                        <button
                            :disabled="processing"
                            class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-black text-white disabled:opacity-50"
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
        <CategoryManagerModal
            :open="categoriesOpen"
            category-type="media"
            :categories="categories"
            @close="categoriesOpen = false"
        />
    </main>
</template>
