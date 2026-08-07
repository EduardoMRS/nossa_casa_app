<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarClock, Save } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { store, update } from '@/actions/App/Http/Controllers/EventController';
import CategorySelector from '@/components/CategorySelector.vue';
import MarkdownWysiwyg from '@/components/MarkdownWysiwyg.vue';
import { useI18n } from '@/lib/i18n';

type EventResource = {
    id?: string;
    title: string;
    slug: string;
    description: string | null;
    start_time: string;
    end_time: string;
    cover_path?: string | null;
    category_ids?: string[];
};

type CategoryOption = {
    id: string;
    name: string;
    slug: string;
    type: string;
};

const props = defineProps<{
    event?: EventResource;
    categories: CategoryOption[];
    returnUrl: string;
}>();

const { t } = useI18n();

const isEditing = computed(() => Boolean(props.event?.id));
const coverInputType = ref<'url' | 'file'>(
    props.event?.cover_path?.startsWith('http') ? 'url' : 'file',
);
const coverPreview = ref<string>(props.event?.cover_path ?? '');
const selectedCoverFile = ref<File | null>(null);
const selectedCoverPreviewUrl = ref<string>('');

const toDateTimeLocal = (value?: string | null) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const tzOffset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - tzOffset).toISOString().slice(0, 16);
};

const form = useForm({
    title: props.event?.title ?? '',
    slug: props.event?.slug ?? '',
    description: props.event?.description ?? '',
    start_time: toDateTimeLocal(props.event?.start_time),
    end_time: toDateTimeLocal(props.event?.end_time),
    cover_path: null as File | string | null,
    category_ids: props.event?.category_ids
        ? [...props.event.category_ids]
        : [],
});

const slugify = (value: string): string => {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
};

watch(
    () => form.title,
    (value) => {
        if (!isEditing.value && !form.slug) {
            form.slug = slugify(value);
        }
    },
);

const revokeCoverPreview = () => {
    if (selectedCoverPreviewUrl.value) {
        URL.revokeObjectURL(selectedCoverPreviewUrl.value);
        selectedCoverPreviewUrl.value = '';
    }
};

const onCoverChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    selectedCoverFile.value = file;
    form.cover_path = file;
    revokeCoverPreview();

    if (file) {
        selectedCoverPreviewUrl.value = URL.createObjectURL(file);
        coverPreview.value = selectedCoverPreviewUrl.value;
    } else {
        coverPreview.value = props.event?.cover_path ?? '';
    }
};

const onCoverTypeChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    coverInputType.value = target.value === 'file' ? 'file' : 'url';

    if (coverInputType.value === 'url') {
        revokeCoverPreview();
        selectedCoverFile.value = null;
        form.cover_path = props.event?.cover_path ?? '';
        coverPreview.value = props.event?.cover_path ?? '';
    } else {
        form.cover_path = selectedCoverFile.value ?? null;
        coverPreview.value =
            selectedCoverPreviewUrl.value || props.event?.cover_path || '';
    }
};

const onCoverUrlInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.cover_path = target.value;
    coverPreview.value = target.value;
};

const submit = () => {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.clearErrors();
        },
    };

    if (isEditing.value && props.event?.id) {
        form.post(update.url({ event: props.event.id }), options);

        return;
    }

    form.post(store.url(), {
        ...options,
        onSuccess: () => {
            form.reset();
            coverPreview.value = '';
            revokeCoverPreview();
        },
    });
};

onBeforeUnmount(() => {
    revokeCoverPreview();
});
</script>

<template>
    <div class="min-h-screen bg-[#f4f6fa] text-[#14263a]">
        <Head
            :title="
                isEditing
                    ? t('events.form.edit_title')
                    : t('events.form.create_title')
            "
        />

        <main class="mx-auto max-w-5xl px-5 py-8 md:px-8 md:py-10">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-[#2b6e7d] uppercase"
                    >
                        {{ t('events.form.studio') }}
                    </p>
                    <h1
                        class="[font-family:Manrope,ui-sans-serif] text-3xl font-black"
                    >
                        {{
                            isEditing
                                ? t('events.form.edit_title')
                                : t('events.form.create_title')
                        }}
                    </h1>
                    <p class="mt-1 text-sm text-[#516478]">
                        {{ t('events.form.subtitle') }}
                    </p>
                </div>

                <Link
                    :href="props.returnUrl"
                    class="inline-flex items-center gap-2 rounded-xl border border-[#c4d2df] bg-white px-4 py-2 text-sm font-semibold text-[#23384f]"
                >
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('nav.back') }}
                </Link>
            </div>

            <form
                @submit.prevent="submit"
                class="space-y-6 rounded-3xl border border-[#d8e2ec] bg-white p-6 shadow-sm md:p-8"
            >
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-[#22374d]">{{
                            t('events.form.title')
                        }}</label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="w-full rounded-xl border border-[#d4e0ea] px-3 py-2.5 text-sm"
                        />
                        <p
                            v-if="form.errors.title"
                            class="text-xs text-red-600"
                        >
                            {{ form.errors.title }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-[#22374d]">{{
                            t('events.form.slug')
                        }}</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            class="w-full rounded-xl border border-[#d4e0ea] px-3 py-2.5 text-sm"
                        />
                        <p v-if="form.errors.slug" class="text-xs text-red-600">
                            {{ form.errors.slug }}
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-bold text-[#22374d]">{{
                            t('events.form.description')
                        }}</label>
                        <span class="text-xs text-[#678096]">{{
                            t('events.form.description_hint')
                        }}</span>
                    </div>
                    <MarkdownWysiwyg v-model="form.description" />
                    <p
                        v-if="form.errors.description"
                        class="text-xs text-red-600"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <CategorySelector
                    v-model="form.category_ids"
                    :categories="categories"
                    label="Categorias do evento"
                    hint="Selecione as categorias disponiveis para a sua igreja"
                />

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="space-y-2">
                        <label
                            class="inline-flex items-center gap-2 text-sm font-bold text-[#22374d]"
                        >
                            <CalendarClock class="h-4 w-4 text-[#2b6e7d]" />
                            {{ t('events.form.start_time') }}
                        </label>
                        <input
                            v-model="form.start_time"
                            type="datetime-local"
                            class="w-full rounded-xl border border-[#d4e0ea] px-3 py-2.5 text-sm"
                        />
                        <p
                            v-if="form.errors.start_time"
                            class="text-xs text-red-600"
                        >
                            {{ form.errors.start_time }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label
                            class="inline-flex items-center gap-2 text-sm font-bold text-[#22374d]"
                        >
                            <CalendarClock class="h-4 w-4 text-[#2b6e7d]" />
                            {{ t('events.form.end_time') }}
                        </label>
                        <input
                            v-model="form.end_time"
                            type="datetime-local"
                            class="w-full rounded-xl border border-[#d4e0ea] px-3 py-2.5 text-sm"
                        />
                        <p
                            v-if="form.errors.end_time"
                            class="text-xs text-red-600"
                        >
                            {{ form.errors.end_time }}
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-sm font-bold text-[#22374d]">{{
                            t('events.form.cover')
                        }}</label>
                        <select
                            :value="coverInputType"
                            class="rounded-xl border border-[#d4e0ea] bg-white px-3 py-2 text-xs font-semibold text-[#23384f]"
                            @change="onCoverTypeChange"
                        >
                            <option value="url">URL</option>
                            <option value="file">
                                {{ t('gallery.file') }}
                            </option>
                        </select>
                    </div>

                    <input
                        v-if="coverInputType === 'file'"
                        type="file"
                        accept="image/*"
                        class="w-full rounded-xl border border-[#d4e0ea] px-3 py-2 text-sm"
                        @change="onCoverChange"
                    />
                    <input
                        v-else
                        v-model="coverPreview"
                        type="text"
                        class="w-full rounded-xl border border-[#d4e0ea] px-3 py-2 text-sm"
                        :placeholder="t('gallery.file_placeholder')"
                        @input="onCoverUrlInput"
                    />

                    <p class="text-xs text-[#678096]">
                        {{ t('events.form.cover_hint') }}
                    </p>
                    <p
                        v-if="form.errors.cover_path"
                        class="text-xs text-red-600"
                    >
                        {{ form.errors.cover_path }}
                    </p>
                    <img
                        v-if="coverPreview"
                        :src="coverPreview"
                        :alt="t('gallery.preview_alt')"
                        class="mt-2 h-44 w-full rounded-xl object-cover ring-1 ring-[#d7e1ea]"
                    />
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#0f5564] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#0c4652] disabled:opacity-60"
                    >
                        <Save class="h-4 w-4" />
                        {{
                            isEditing
                                ? t('events.form.update')
                                : t('events.form.save')
                        }}
                    </button>
                </div>
            </form>
        </main>
    </div>
</template>
