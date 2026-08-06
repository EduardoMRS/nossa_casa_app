<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

type CategoryTypeOption = {
    value: string;
    label: string;
};

type CategoryItem = {
    id: string;
    name: string;
    slug: string;
    type: string;
};

const props = defineProps<{
    title: string;
    subtitle: string;
    description: string;
    types: CategoryTypeOption[];
    categories: CategoryItem[];
}>();

const editingCategoryId = ref<string | null>(null);
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const form = reactive({
    name: '',
    slug: '',
    type: props.types[0]?.value ?? 'post',
});

const slugify = (value: string): string => {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
};

const groupedCategories = computed(() => {
    return props.types.map((type) => ({
        ...type,
        items: props.categories.filter((category) => category.type === type.value),
    }));
});

watch(
    () => form.name,
    (value) => {
        if (!editingCategoryId.value && !form.slug) {
            form.slug = slugify(value);
        }
    },
);

const resetForm = () => {
    editingCategoryId.value = null;
    form.name = '';
    form.slug = '';
    form.type = props.types[0]?.value ?? 'post';
    errors.value = {};
};

const fillForm = (category: CategoryItem) => {
    editingCategoryId.value = category.id;
    form.name = category.name;
    form.slug = category.slug;
    form.type = category.type;
    errors.value = {};
};

const refreshPage = () => {
    router.reload({ preserveScroll: true });
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    const payload = {
        name: form.name,
        slug: form.slug || slugify(form.name),
        type: form.type,
    };

    const options = {
        preserveScroll: true,
        onError: (validationErrors: Record<string, string>) => {
            errors.value = validationErrors;
        },
        onSuccess: () => {
            resetForm();
            refreshPage();
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    if (editingCategoryId.value) {
        router.put(`/api/categories/${editingCategoryId.value}`, payload, options);
        return;
    }

    router.post('/api/categories', payload, options);
};

const removeCategory = (category: CategoryItem) => {
    if (!confirm(`Excluir categoria "${category.name}"?`)) {
        return;
    }

    router.delete(`/api/categories/${category.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingCategoryId.value === category.id) {
                resetForm();
            }

            refreshPage();
        },
    });
};
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

                    <Link href="/dashboard" class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-bold text-slate-700 transition hover:border-slate-300 hover:bg-white">
                        Voltar ao dashboard
                    </Link>
                </div>
            </header>

            <section class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                <article class="rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-600">Categorias</p>
                            <h2 class="text-xl font-black text-slate-900">{{ editingCategoryId ? 'Editar categoria' : 'Nova categoria' }}</h2>
                        </div>

                        <button type="button" class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600" @click="resetForm">
                            Limpar
                        </button>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <div class="grid gap-2">
                            <label class="text-sm font-semibold text-slate-700">Nome</label>
                            <input v-model="form.name" type="text" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <p v-if="errors.name" class="text-xs text-red-600">{{ errors.name }}</p>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-semibold text-slate-700">Slug</label>
                            <input v-model="form.slug" type="text" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <p v-if="errors.slug" class="text-xs text-red-600">{{ errors.slug }}</p>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-semibold text-slate-700">Tipo</label>
                            <select v-model="form.type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option v-for="type in types" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                            <p v-if="errors.type" class="text-xs text-red-600">{{ errors.type }}</p>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                type="button"
                                :disabled="processing"
                                class="rounded-full bg-slate-900 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                                @click="submit"
                            >
                                {{ processing ? 'Salvando...' : (editingCategoryId ? 'Atualizar categoria' : 'Criar categoria') }}
                            </button>
                        </div>
                    </div>
                </article>

                <article class="rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Categorias da igreja</p>
                            <h2 class="text-xl font-black text-slate-900">Organizadas por tipo</h2>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ categories.length }} itens</span>
                    </div>

                    <div class="mt-5 space-y-5">
                        <section v-for="group in groupedCategories" :key="group.value" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold uppercase tracking-[0.12em] text-slate-500">{{ group.label }}</h3>
                                <span class="text-xs text-slate-400">{{ group.items.length }} categorias</span>
                            </div>

                            <div v-if="group.items.length" class="grid gap-3">
                                <article
                                    v-for="category in group.items"
                                    :key="category.id"
                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-bold text-slate-900">{{ category.name }}</p>
                                            <p class="text-xs text-slate-500">{{ category.slug }}</p>
                                        </div>

                                        <div class="flex flex-wrap gap-2 text-xs font-bold">
                                            <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-slate-700" @click="fillForm(category)">Editar</button>
                                            <button type="button" class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-rose-700" @click="removeCategory(category)">Excluir</button>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                Nenhuma categoria cadastrada neste tipo.
                            </div>
                        </section>
                    </div>
                </article>
            </section>
        </section>
    </div>
</template>