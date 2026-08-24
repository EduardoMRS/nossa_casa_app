<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import AdminPageHeader from '@/components/AdminPageHeader.vue';
import AppModal from '@/components/AppModal.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
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
const { confirm } = useConfirmDialog();
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

const remove = async (category: CategoryItem): Promise<void> => {
    if (
        !(await confirm({
            message: t('admin.categories.delete_confirm', {
                name: category.name,
            }),
            confirmLabel: t('actions.delete'),
            intent: 'danger',
        }))
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
        <AdminPageHeader
            :kicker="t('admin.categories.subtitle')"
            :title="t('admin.categories.title')"
            :description="t('admin.categories.description')"
        >
            <button
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-foreground px-4 py-2.5 text-xs font-black text-primary"
                @click="openEditor()"
            >
                <Plus class="size-4" />{{ t('admin.categories.new') }}
            </button>
        </AdminPageHeader>

        <section
            class="overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4"
            >
                <div class="flex items-center gap-2 font-black">
                    <Tags class="size-5 text-indigo-600" />{{
                        t('admin.categories.church_categories')
                    }}
                </div>
                <select
                    v-model="typeFilter"
                    class="rounded-lg border-input bg-background text-sm text-foreground"
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
                        class="bg-muted text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">
                                {{ t('admin.common.name') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ t('admin.common.slug') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ t('admin.common.type') }}
                            </th>
                            <th class="px-5 py-3 text-right">
                                {{ t('posts.index.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="category in filteredCategories"
                            :key="category.id"
                            class="hover:bg-muted/60"
                        >
                            <td
                                class="px-5 py-4 font-bold text-card-foreground"
                            >
                                {{ category.name }}
                            </td>
                            <td
                                class="px-5 py-4 font-mono text-xs text-muted-foreground"
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
                class="p-12 text-center text-sm text-muted-foreground"
            >
                {{ t('admin.categories.empty') }}
            </p>
        </section>

        <AppModal
            v-model:open="modalOpen"
            :title="
                editingId
                    ? t('admin.categories.edit')
                    : t('admin.categories.new')
            "
            scrollable
        >
            <form class="space-y-4" @submit.prevent="save">
                <label class="block text-xs font-bold text-foreground"
                    >{{ t('admin.common.name')
                    }}<input
                        v-model="form.name"
                        required
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                /></label>
                <p v-if="errors.name" class="text-xs text-rose-600">
                    {{ errors.name }}
                </p>
                <label class="block text-xs font-bold text-foreground"
                    >{{ t('admin.common.slug')
                    }}<input
                        v-model="form.slug"
                        required
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground" /></label
                ><label class="block text-xs font-bold text-foreground"
                    >{{ t('admin.common.type')
                    }}<select
                        v-model="form.type"
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
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
                        class="rounded-lg px-4 py-2 text-sm font-bold text-muted-foreground hover:bg-muted"
                        @click="modalOpen = false"
                    >
                        {{ t('actions.cancel') }}</button
                    ><button
                        :disabled="processing"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground disabled:opacity-50"
                    >
                        {{ t('actions.save') }}
                    </button>
                </div>
            </form>
        </AppModal>
    </main>
</template>
