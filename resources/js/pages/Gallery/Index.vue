<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Upload, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import CategorySelector from '@/components/CategorySelector.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';

interface MediaItem {
    id: string;
    url: string;
    mimetype: string;
    size: number;
    created_at: string;
    uploader?: {
        id: string;
        first_name: string;
        last_name: string;
    } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

type CategoryOption = {
    id: string;
    name: string;
    slug: string;
    type: string;
};

const props = defineProps<{
    media: {
        data: MediaItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    categories: CategoryOption[];
}>();
const { t } = useI18n();

const toMb = (size: number) => `${(size / 1024 / 1024).toFixed(2)} MB`;

const page = usePage();
const canUploadMedia = computed(() => {
    const role = String(page.props.auth?.user?.role ?? '');

    return ['leader', 'media', 'admin', 'superadmin', 'system'].includes(role);
});

const uploadType = ref<'url' | 'file'>('url');
const uploadUrl = ref('');
const selectedUploadFile = ref<File | null>(null);
const uploadPreviewUrl = ref<string>('');
const selectedCategoryIds = ref<string[]>([]);
const uploadProcessing = ref(false);
const uploadError = ref('');
const uploadOpen = ref(false);

const clearUploadPreview = () => {
    if (uploadPreviewUrl.value) {
        URL.revokeObjectURL(uploadPreviewUrl.value);
        uploadPreviewUrl.value = '';
    }
};

const uploadPreview = computed(() => {
    if (uploadType.value === 'file') {
        return uploadPreviewUrl.value;
    }

    return uploadUrl.value.trim();
});

const handleUploadTypeChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    uploadType.value = target.value === 'file' ? 'file' : 'url';

    if (uploadType.value === 'url') {
        selectedUploadFile.value = null;
        clearUploadPreview();
    }
};

const handleUploadFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    selectedUploadFile.value = file;
    clearUploadPreview();

    if (file) {
        uploadPreviewUrl.value = URL.createObjectURL(file);
    }
};

const submitUpload = () => {
    uploadProcessing.value = true;
    uploadError.value = '';

    router.post(
        '/api/media',
        {
            file_path:
                uploadType.value === 'file'
                    ? selectedUploadFile.value
                    : uploadUrl.value,
            file: uploadType.value === 'file' ? selectedUploadFile.value : null,
            gallery: true,
            category_ids: selectedCategoryIds.value,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadUrl.value = '';
                selectedUploadFile.value = null;
                uploadType.value = 'url';
                selectedCategoryIds.value = [];
                clearUploadPreview();
                uploadOpen.value = false;
            },
            onError: (errors) => {
                uploadError.value = String(
                    errors.file_path ??
                        errors.file ??
                        t('gallery.upload_error'),
                );
            },
            onFinish: () => {
                uploadProcessing.value = false;
            },
        },
    );
};

onBeforeUnmount(() => {
    clearUploadPreview();
});
</script>

<template>
    <Head :title="t('gallery.meta_title')" />

    <div class="flex min-h-screen flex-col bg-[#f8fafc] text-slate-950">
        <PublicHeader active="gallery" />

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
            <section
                class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1 class="text-2xl font-black tracking-tight md:text-3xl">
                        {{ t('gallery.title') }}
                    </h1>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                        {{ t('gallery.description') }}
                    </p>
                </div>
                <button
                    v-if="canUploadMedia"
                    class="inline-flex shrink-0 items-center justify-center gap-2 self-start rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 sm:self-center"
                    @click="uploadOpen = true"
                >
                    <Upload class="size-4" /> {{ t('gallery.upload') }}
                </button>
            </section>

            <div
                v-if="canUploadMedia && uploadOpen"
                class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
                @click.self="uploadOpen = false"
            >
                <section
                    class="relative my-6 w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl md:p-6"
                >
                    <button
                        class="absolute top-4 right-4 rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        @click="uploadOpen = false"
                    >
                        <X class="size-4" />
                    </button>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-indigo-500 uppercase"
                            >
                                {{ t('gallery.upload_kicker') }}
                            </p>
                            <h2 class="text-xl font-black text-slate-950">
                                {{ t('gallery.upload_title') }}
                            </h2>
                        </div>

                        <select
                            :value="uploadType"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700"
                            @change="handleUploadTypeChange"
                        >
                            <option value="url">URL</option>
                            <option value="file">
                                {{ t('gallery.file') }}
                            </option>
                        </select>
                    </div>

                    <div
                        class="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_240px] md:items-start"
                    >
                        <div class="space-y-3">
                            <input
                                v-if="uploadType === 'file'"
                                type="file"
                                accept="image/*,video/*,application/pdf"
                                class="w-full rounded-xl border border-[#e0d2c3] px-3 py-2.5 text-sm"
                                @change="handleUploadFileChange"
                            />
                            <input
                                v-else
                                v-model="uploadUrl"
                                type="text"
                                :placeholder="t('gallery.file_placeholder')"
                                class="w-full rounded-xl border border-[#e0d2c3] px-3 py-2.5 text-sm"
                            />

                            <p class="text-xs text-[#7d695b]">
                                {{ t('gallery.upload_hint') }}
                            </p>
                            <p
                                v-if="uploadError"
                                class="text-xs font-semibold text-red-600"
                            >
                                {{ uploadError }}
                            </p>

                            <CategorySelector
                                v-model="selectedCategoryIds"
                                :categories="categories"
                                :label="t('gallery.categories')"
                                :hint="t('gallery.categories_hint')"
                            />
                        </div>

                        <div
                            class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-3"
                        >
                            <p
                                class="mb-2 text-xs font-bold tracking-[0.16em] text-indigo-500 uppercase"
                            >
                                {{ t('gallery.preview') }}
                            </p>
                            <img
                                v-if="uploadPreview"
                                :src="uploadPreview"
                                :alt="t('gallery.preview_alt')"
                                class="h-44 w-full rounded-xl object-cover"
                            />
                            <div
                                v-else
                                class="flex h-44 items-center justify-center rounded-xl bg-white text-sm text-[#907a6b]"
                            >
                                {{ t('gallery.no_selection') }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            :disabled="uploadProcessing"
                            class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="submitUpload"
                        >
                            {{
                                uploadProcessing
                                    ? t('gallery.uploading')
                                    : t('gallery.upload')
                            }}
                        </button>
                    </div>
                </section>
            </div>

            <section
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
            >
                <article
                    v-for="item in props.media.data"
                    :key="item.id"
                    class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div
                        class="relative aspect-square overflow-hidden bg-slate-100"
                    >
                        <img
                            :src="item.url"
                            :alt="t('gallery.item_alt')"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                            loading="lazy"
                        />
                    </div>
                    <div class="space-y-1 p-2.5">
                        <p
                            class="truncate text-[11px] font-semibold text-[#91573a] uppercase"
                        >
                            {{ item.mimetype }}
                        </p>
                        <p class="truncate text-xs text-[#5f4838]">
                            {{
                                item.uploader
                                    ? `${item.uploader.first_name} ${item.uploader.last_name}`
                                    : t('gallery.anonymous')
                            }}
                        </p>
                        <p class="text-[11px] text-[#8a7566]">
                            {{ toMb(item.size) }}
                        </p>
                    </div>
                </article>
            </section>

            <section
                v-if="props.media.links.length > 3"
                class="mt-8 flex flex-wrap items-center justify-between gap-3"
            >
                <p class="text-sm text-[#7d695b]">
                    {{
                        t('gallery.pagination', {
                            from: props.media.from ?? 0,
                            to: props.media.to ?? 0,
                            total: props.media.total,
                        })
                    }}
                </p>

                <div class="flex flex-wrap gap-2">
                    <template
                        v-for="(link, index) in props.media.links"
                        :key="index"
                    >
                        <span
                            v-if="!link.url"
                            class="rounded-lg border border-[#e4d7c8] bg-white px-3 py-1.5 text-sm text-[#b4a393]"
                            v-html="link.label"
                        />
                        <Link
                            v-else
                            :href="link.url"
                            class="rounded-lg border px-3 py-1.5 text-sm"
                            :class="
                                link.active
                                    ? 'border-[#a84d24] bg-[#a84d24] text-white'
                                    : 'border-[#e4d7c8] bg-white text-[#5a402f]'
                            "
                        >
                            <span v-html="link.label" />
                        </Link>
                    </template>
                </div>
            </section>
        </main>
        <PublicFooter />
    </div>
</template>
