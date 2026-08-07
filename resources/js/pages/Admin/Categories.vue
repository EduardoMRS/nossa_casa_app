<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2, X } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from '@/lib/i18n';

type CategoryTypeOption = { value: string; label: string };
type CategoryItem = { id: string; name: string; slug: string; type: string };
const props = defineProps<{
    title: string;
    subtitle: string;
    description: string;
    types: CategoryTypeOption[];
    categories: CategoryItem[];
}>();
const { t } = useI18n();
const modalOpen = ref(false);
const editingId = ref<string | null>(null);
const typeFilter = ref('');
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = reactive({
    name: '',
    slug: '',
    type: props.types[0]?.value ?? 'post',
});
const filteredCategories = computed(() =>
    typeFilter.value
        ? props.categories.filter((item) => item.type === typeFilter.value)
        : props.categories,
);
const slugify = (value: string): string =>
    value
        .toLowerCase()
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
watch(
    () => form.name,
    (value) => {
        if (!editingId.value) {
            form.slug = slugify(value);
        }
    },
);

const openEditor = (category?: CategoryItem): void => {
    editingId.value = category?.id ?? null;
    form.name = category?.name ?? '';
    form.slug = category?.slug ?? '';
    form.type =
        category?.type ?? typeFilter.value ?? props.types[0]?.value ?? 'post';
    errors.value = {};
    modalOpen.value = true;
};

const save = (): void => {
    processing.value = true;
    const options = {
        preserveScroll: true,
        onError: (values: Record<string, string>) => {
            errors.value = values;
        },
        onSuccess: () => {
            modalOpen.value = false;
            router.reload();
        },
        onFinish: () => {
            processing.value = false;
        },
    };
    const payload = { ...form, slug: form.slug || slugify(form.name) };

    if (editingId.value) {
        router.put(`/api/categories/${editingId.value}`, payload, options);

        return;
    }

    router.post('/api/categories', payload, options);
};

const remove = (category: CategoryItem): void => {
    if (
        !window.confirm(
            t('admin.categories.delete_confirm', { name: category.name }),
        )
    ) {
        return;
    }

    router.delete(`/api/categories/${category.id}`, {
        preserveScroll: true,
        onSuccess: () => router.reload(),
    });
};
</script>

<template>
    <Head :title="t('admin.categories.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="flex flex-col justify-between gap-4 rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white shadow-sm md:flex-row md:items-end"
        >
            <div>
                <p
                    class="font-mono text-[10px] font-bold tracking-[0.18em] text-indigo-200 uppercase"
                >
                    {{ t('admin.categories.subtitle') }}
                </p>
                <h1 class="mt-2 text-3xl font-black">
                    {{ t('admin.categories.title') }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-indigo-100">
                    {{ t('admin.categories.description') }}
                </p>
            </div>
            <button
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-2.5 text-xs font-black text-indigo-950"
                @click="openEditor()"
            >
                <Plus class="size-4" />{{ t('admin.categories.new') }}
            </button>
        </header>

        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4"
            >
                <div class="flex items-center gap-2 font-black">
                    <Tags class="size-5 text-indigo-600" />{{
                        t('admin.categories.church_categories')
                    }}
                </div>
                <select
                    v-model="typeFilter"
                    class="rounded-lg border-slate-200 text-sm"
                >
                    <option value="">
                        {{ t('admin.categories.all_types') }}
                    </option>
                    <option
                        v-for="type in types"
                        :key="type.value"
                        :value="type.value"
                    >
                        {{ t(`admin.categories.types.${type.value}`) }}
                    </option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead
                        class="bg-slate-50 text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">
                                {{ t('admin.common.name') }}
                            </th>
                            <th class="px-5 py-3">Slug</th>
                            <th class="px-5 py-3">
                                {{ t('admin.common.type') }}
                            </th>
                            <th class="px-5 py-3 text-right">
                                {{ t('posts.index.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="category in filteredCategories"
                            :key="category.id"
                            class="hover:bg-slate-50"
                        >
                            <td class="px-5 py-4 font-bold text-slate-900">
                                {{ category.name }}
                            </td>
                            <td
                                class="px-5 py-4 font-mono text-xs text-slate-400"
                            >
                                {{ category.slug }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-700"
                                    >{{
                                        t(
                                            `admin.categories.types.${category.type}`,
                                        )
                                    }}</span
                                >
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-1">
                                    <button
                                        class="rounded-lg p-2 text-indigo-600 hover:bg-indigo-50"
                                        @click="openEditor(category)"
                                    >
                                        <Pencil class="size-4" /></button
                                    ><button
                                        class="rounded-lg p-2 text-rose-600 hover:bg-rose-50"
                                        @click="remove(category)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-if="!filteredCategories.length"
                class="p-12 text-center text-sm text-slate-400"
            >
                {{ t('admin.categories.empty') }}
            </p>
        </section>

        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 grid place-items-center bg-slate-950/65 p-4 backdrop-blur-sm"
            @click.self="modalOpen = false"
        >
            <form
                class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-2xl"
                @submit.prevent="save"
            >
                <header class="flex items-center justify-between">
                    <h2 class="text-xl font-black">
                        {{
                            editingId
                                ? t('admin.categories.edit')
                                : t('admin.categories.new')
                        }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100"
                        @click="modalOpen = false"
                    >
                        <X class="size-5" />
                    </button>
                </header>
                <label class="block text-xs font-bold text-slate-600"
                    >{{ t('admin.common.name')
                    }}<input
                        v-model="form.name"
                        required
                        class="mt-1 w-full rounded-lg border-slate-200"
                /></label>
                <p v-if="errors.name" class="text-xs text-rose-600">
                    {{ errors.name }}
                </p>
                <label class="block text-xs font-bold text-slate-600"
                    >Slug<input
                        v-model="form.slug"
                        required
                        class="mt-1 w-full rounded-lg border-slate-200" /></label
                ><label class="block text-xs font-bold text-slate-600"
                    >{{ t('admin.common.type')
                    }}<select
                        v-model="form.type"
                        class="mt-1 w-full rounded-lg border-slate-200"
                    >
                        <option
                            v-for="type in types"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ t(`admin.categories.types.${type.value}`) }}
                        </option>
                    </select></label
                >
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-slate-500"
                        @click="modalOpen = false"
                    >
                        {{ t('actions.cancel') }}</button
                    ><button
                        :disabled="processing"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                    >
                        {{ t('actions.save') }}
                    </button>
                </div>
            </form>
        </div>
    </main>
</template>
