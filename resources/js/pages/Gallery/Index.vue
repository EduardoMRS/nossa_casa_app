<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import CategorySelector from '@/components/CategorySelector.vue';
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

    <div class="min-h-screen bg-[#fbf8f1] text-[#33271f]">
        <PublicHeader active="gallery" />

        <main class="mx-auto max-w-7xl px-5 py-8 md:px-8 md:py-10">
            <section
                class="mb-8 rounded-3xl bg-gradient-to-r from-[#a84d24] via-[#cc6c33] to-[#d68e3f] p-7 text-white md:p-10"
            >
                <p
                    class="mb-2 text-xs tracking-[0.2em] text-[#ffe6c3] uppercase"
                >
                    {{ t('gallery.kicker') }}
                </p>
                <h1
                    class="mb-2 [font-family:Manrope,ui-sans-serif] text-3xl font-black md:text-4xl"
                >
                    {{ t('gallery.title') }}
                </h1>
                <p class="max-w-3xl text-sm text-[#fff2df] md:text-base">
                    {{ t('gallery.description') }}
                </p>
            </section>

            <section
                v-if="canUploadMedia"
                class="mb-8 rounded-3xl border border-[#eadfce] bg-white p-5 shadow-sm md:p-6"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.18em] text-[#91573a] uppercase"
                        >
                            {{ t('gallery.upload_kicker') }}
                        </p>
                        <h2 class="text-xl font-black text-[#2f241c]">
                            {{ t('gallery.upload_title') }}
                        </h2>
                    </div>

                    <select
                        :value="uploadType"
                        class="rounded-xl border border-[#e0d2c3] bg-white px-3 py-2 text-sm font-semibold text-[#5a402f]"
                        @change="handleUploadTypeChange"
                    >
                        <option value="url">URL</option>
                        <option value="file">{{ t('gallery.file') }}</option>
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
                        class="rounded-2xl border border-dashed border-[#e0d2c3] bg-[#fcf8f2] p-3"
                    >
                        <p
                            class="mb-2 text-xs font-bold tracking-[0.16em] text-[#91573a] uppercase"
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
                        class="rounded-full bg-[#a84d24] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#8f421d] disabled:cursor-not-allowed disabled:opacity-60"
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

            <section
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6"
            >
                <article
                    v-for="item in props.media.data"
                    :key="item.id"
                    class="group overflow-hidden rounded-xl border border-[#eadfce] bg-white shadow-sm"
                >
                    <div
                        class="relative aspect-square overflow-hidden bg-[#f6ede0]"
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
    </div>
</template>
