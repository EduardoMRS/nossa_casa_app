<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import CategorySelector from '@/components/CategorySelector.vue';

type MediaUploader = {
    id: string;
    first_name: string;
    last_name: string;
} | null;

type MediaItem = {
    id: string;
    file_path: string | null;
    preview_url: string | null;
    mimetype: string | null;
    size: number | null;
    gallery: boolean;
    status: 'pending' | 'approved' | 'rejected' | string;
    created_at: string | null;
    uploader: MediaUploader;
    category_ids: string[];
};

type CategoryOption = {
    id: string;
    name: string;
    slug: string;
    type: string;
};

type StatItem = {
    label: string;
    value: number;
};

type ActionItem = {
    label: string;
    href: string;
};

const props = defineProps<{
    title: string;
    subtitle: string;
    description: string;
    stats: StatItem[];
    actions: ActionItem[];
    media: MediaItem[];
    categories: CategoryOption[];
}>();

const selectedMediaId = ref<string | null>(null);
const sourceType = ref<'url' | 'file'>('url');
const fileUrl = ref('');
const selectedFile = ref<File | null>(null);
const selectedPreviewUrl = ref<string>('');
const galleryEnabled = ref(true);
const statusValue = ref<'pending' | 'approved' | 'rejected'>('pending');
const mimetypeValue = ref('');
const sizeValue = ref('');
const categoryIds = ref<string[]>([]);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const currentMedia = computed(() => {
    if (!selectedMediaId.value) {
        return null;
    }

    return props.media.find((item) => item.id === selectedMediaId.value) ?? null;
});

const previewSource = computed(() => {
    if (sourceType.value === 'file') {
        return selectedPreviewUrl.value || currentMedia.value?.preview_url || '';
    }

    return fileUrl.value.trim() || currentMedia.value?.preview_url || '';
});

const clearPreview = () => {
    if (selectedPreviewUrl.value) {
        URL.revokeObjectURL(selectedPreviewUrl.value);
        selectedPreviewUrl.value = '';
    }
};

const resetForm = () => {
    selectedMediaId.value = null;
    sourceType.value = 'url';
    fileUrl.value = '';
    selectedFile.value = null;
    galleryEnabled.value = true;
    statusValue.value = 'pending';
    mimetypeValue.value = '';
    sizeValue.value = '';
    errors.value = {};
    clearPreview();
};

const editMedia = (item: MediaItem) => {
    selectedMediaId.value = item.id;
    sourceType.value = item.file_path && item.file_path.startsWith('http') ? 'url' : 'file';
    fileUrl.value = item.file_path ?? '';
    selectedFile.value = null;
    galleryEnabled.value = item.gallery;
    statusValue.value = (item.status === 'approved' || item.status === 'rejected' ? item.status : 'pending') as 'pending' | 'approved' | 'rejected';
    mimetypeValue.value = item.mimetype ?? '';
    sizeValue.value = item.size !== null ? String(item.size) : '';
    categoryIds.value = [...item.category_ids];
    errors.value = {};
    clearPreview();
};

const handleTypeChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    sourceType.value = target.value === 'file' ? 'file' : 'url';

    if (sourceType.value === 'url') {
        selectedFile.value = null;
        clearPreview();
    }
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    selectedFile.value = file;
    clearPreview();

    if (file) {
        selectedPreviewUrl.value = URL.createObjectURL(file);
    }
};

const submitMedia = () => {
    processing.value = true;
    errors.value = {};

    const payload = {
        file_path: sourceType.value === 'file' ? selectedFile.value : fileUrl.value,
        file: sourceType.value === 'file' ? selectedFile.value : null,
        gallery: galleryEnabled.value,
        status: statusValue.value,
        mimetype: mimetypeValue.value || null,
        size: sizeValue.value ? Number(sizeValue.value) : null,
        category_ids: categoryIds.value,
    };

    const options = {
        forceFormData: true,
        preserveScroll: true,
        onError: (validationErrors: Record<string, string>) => {
            errors.value = validationErrors;
        },
        onSuccess: () => {
            resetForm();
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    if (selectedMediaId.value) {
        router.put(`/api/media/${selectedMediaId.value}`, payload, options);
        return;
    }

    router.post('/api/media', payload, options);
};

const removeMedia = (item: MediaItem) => {
    if (!confirm(`Excluir esta midia?`)) {
        return;
    }

    router.delete(`/api/media/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedMediaId.value === item.id) {
                resetForm();
            }
        },
    });
};

const setStatus = (item: MediaItem, status: 'approved' | 'rejected') => {
    router.put(`/api/admin/media/${item.id}/status`, { status }, {
        preserveScroll: true,
    });
};

onBeforeUnmount(() => {
    clearPreview();
});
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-[#f4f7fb] p-4 text-slate-800 md:p-6">
        <section class="mx-auto max-w-7xl space-y-6">
            <header class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ subtitle }}</p>
                        <h1 class="mt-1 text-3xl font-black text-slate-900">{{ title }}</h1>
                        <p class="mt-2 max-w-3xl text-sm text-slate-600">{{ description }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-for="action in actions"
                            :key="action.href"
                            :href="action.href"
                            class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-bold text-slate-700 transition hover:border-slate-300 hover:bg-white"
                        >
                            {{ action.label }}
                        </Link>
                    </div>
                </div>
            </header>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="stat in stats"
                    :key="stat.label"
                    class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm"
                >
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">{{ stat.label }}</p>
                    <p class="mt-2 text-3xl font-black text-slate-900">{{ stat.value }}</p>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                <article class="rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-600">CRUD de midia</p>
                            <h2 class="text-xl font-black text-slate-900">{{ selectedMediaId ? 'Editar midia' : 'Enviar nova midia' }}</h2>
                        </div>
                        <button
                            type="button"
                            class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600"
                            @click="resetForm"
                        >
                            Limpar
                        </button>
                    </div>

                    <div class="mt-4 grid gap-4">
                        <div class="grid gap-2">
                            <label class="text-sm font-semibold text-slate-700">Origem</label>
                            <select
                                :value="sourceType"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                @change="handleTypeChange"
                            >
                                <option value="url">URL</option>
                                <option value="file">Arquivo</option>
                            </select>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-semibold text-slate-700">Arquivo / URL</label>
                            <input
                                v-if="sourceType === 'file'"
                                type="file"
                                accept="image/*,video/*,application/pdf"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                @change="handleFileChange"
                            />
                            <input
                                v-else
                                v-model="fileUrl"
                                type="text"
                                placeholder="https://... ou caminho do arquivo"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                            <p v-if="errors.file_path" class="text-xs text-red-600">{{ errors.file_path }}</p>
                            <p v-if="errors.file" class="text-xs text-red-600">{{ errors.file }}</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-3 py-3 text-sm text-slate-700">
                                <input v-model="galleryEnabled" type="checkbox" class="rounded border-slate-300" />
                                Visivel na galeria publica
                            </label>

                            <div class="grid gap-2">
                                <label class="text-sm font-semibold text-slate-700">Status</label>
                                <select v-model="statusValue" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    <option value="pending">Pendente</option>
                                    <option value="approved">Aprovada</option>
                                    <option value="rejected">Rejeitada</option>
                                </select>
                            </div>
                        </div>

                        <CategorySelector
                            v-model="categoryIds"
                            :categories="categories"
                            label="Categorias da midia"
                            hint="Filtre pela igreja atual"
                        />

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <label class="text-sm font-semibold text-slate-700">Mimetype</label>
                                <input v-model="mimetypeValue" type="text" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-semibold text-slate-700">Tamanho (bytes)</label>
                                <input v-model="sizeValue" type="number" min="0" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            </div>
                        </div>

                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-3">
                            <p class="mb-2 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Preview</p>
                            <img v-if="previewSource" :src="previewSource" alt="Preview da midia" class="h-48 w-full rounded-xl object-cover" />
                            <div v-else class="flex h-48 items-center justify-center rounded-xl bg-white text-sm text-slate-500">Selecione uma midia para visualizar</div>
                        </div>

                        <p v-if="errors.status" class="text-xs text-red-600">{{ errors.status }}</p>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                :disabled="processing"
                                class="rounded-full bg-slate-900 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                                @click="submitMedia"
                            >
                                {{ processing ? 'Salvando...' : (selectedMediaId ? 'Atualizar midia' : 'Enviar midia') }}
                            </button>
                            <p class="text-xs text-slate-500">Upload, edicao e aprovacao usam o mesmo fluxo para simplificar a moderação.</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Fila de moderação</p>
                            <h2 class="text-xl font-black text-slate-900">Midias recentes</h2>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ media.length }} itens</span>
                    </div>

                    <div class="mt-4 grid gap-3">
                        <article
                            v-for="item in media"
                            :key="item.id"
                            class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50"
                        >
                            <div class="grid gap-3 p-3 md:grid-cols-[100px_1fr] md:items-start">
                                <img
                                    :src="item.preview_url || ''"
                                    alt="Preview da midia"
                                    class="h-24 w-full rounded-xl object-cover md:h-24"
                                />

                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-bold text-slate-900">{{ item.mimetype || 'Arquivo' }}</p>
                                            <p class="text-xs text-slate-500">{{ item.uploader ? `${item.uploader.first_name} ${item.uploader.last_name}` : 'Sem autor' }}</p>
                                        </div>
                                        <span
                                            class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.12em]"
                                            :class="item.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : item.status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'"
                                        >
                                            {{ item.status }}
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                        <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-slate-700" @click="editMedia(item)">Editar</button>
                                        <button type="button" class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-emerald-700" @click="setStatus(item, 'approved')">Aprovar</button>
                                        <button type="button" class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-rose-700" @click="setStatus(item, 'rejected')">Rejeitar</button>
                                        <button type="button" class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-slate-600" @click="removeMedia(item)">Excluir</button>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <div v-if="media.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500">
                            Nenhuma midia cadastrada ainda.
                        </div>
                    </div>
                </article>
            </section>
        </section>
    </div>
</template>
