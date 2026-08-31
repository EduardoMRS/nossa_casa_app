<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import AppModal from '@/components/AppModal.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useI18n } from '@/lib/i18n';

export type ManagedCategory = {
    id: string;
    name: string;
    slug: string;
    type: string;
};

const props = defineProps<{
    open: boolean;
    categoryType: string;
    categories: ManagedCategory[];
    title?: string;
}>();
const emit = defineEmits<{ close: [] }>();
const { t } = useI18n();
const { confirm } = useConfirmDialog();
const editingId = ref<string | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = reactive({ name: '', slug: '' });

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

const reset = (): void => {
    editingId.value = null;
    form.name = '';
    form.slug = '';
    errors.value = {};
};

const edit = (category: ManagedCategory): void => {
    editingId.value = category.id;
    form.name = category.name;
    form.slug = category.slug;
    errors.value = {};
};

const save = (): void => {
    processing.value = true;
    const options = {
        preserveScroll: true,
        onError: (validationErrors: Record<string, string>) => {
            errors.value = validationErrors;
        },
        onSuccess: () => {
            reset();
            router.reload({ only: ['categories'] });
        },
        onFinish: () => {
            processing.value = false;
        },
    };
    const payload = {
        name: form.name,
        slug: form.slug || slugify(form.name),
        type: props.categoryType,
    };

    if (editingId.value) {
        router.put(`/api/categories/${editingId.value}`, payload, options);

        return;
    }

    router.post('/api/categories', payload, options);
};

const remove = async (category: ManagedCategory): Promise<void> => {
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
        onSuccess: () => router.reload({ only: ['categories'] }),
    });
};
</script>

<template>
    <AppModal
        :open="open"
        :title="title ?? t('admin.categories.church_categories')"
        size="xl"
        scrollable
        @update:open="(value) => !value && emit('close')"
    >
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.72fr)]">
            <div class="overflow-hidden rounded-xl border border-border bg-card">
                <table class="w-full text-left text-sm">
                    <thead
                            class="border-b border-border bg-muted/70 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">
                                    {{ t('admin.common.name') }}
                                </th>
                                <th class="px-4 py-3">
                                    {{ t('admin.common.slug') }}
                                </th>
                                <th class="px-4 py-3 text-right">
                                    {{ t('posts.index.actions') }}
                                </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                                v-for="category in categories"
                                :key="category.id"
                                class="transition hover:bg-muted/50"
                            >
                            <td class="px-4 py-3 font-bold text-card-foreground">
                                {{ category.name }}
                            </td>
                                <td
                                    class="px-4 py-3 font-mono text-xs text-muted-foreground"
                                >
                                    {{ category.slug }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <button
                                            class="rounded-lg border border-border p-2 text-primary transition hover:bg-primary/10"
                                            @click="edit(category)"
                                        >
                                            <Pencil class="size-4" /></button
                                        ><button
                                            class="rounded-lg border border-destructive/30 p-2 text-destructive transition hover:bg-destructive/10"
                                            @click="remove(category)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!categories.length"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        {{ t('admin.categories.empty') }}
                    </p>
                </div>
                <form
                    class="h-fit space-y-4 rounded-xl border border-border bg-muted/50 p-4 text-foreground"
                    @submit.prevent="save"
                >
                    <div class="flex items-center justify-between">
                        <h3 class="flex items-center gap-2 font-black">
                            <Tags class="size-4 text-primary" />{{
                                editingId
                                    ? t('admin.categories.edit')
                                    : t('admin.categories.new')
                            }}
                        </h3>
                        <button
                            v-if="editingId"
                            type="button"
                            class="text-xs font-bold text-muted-foreground"
                            @click="reset"
                        >
                            {{ t('admin.common.clear') }}
                        </button>
                    </div>
                    <label class="block text-xs font-bold text-foreground"
                        >{{ t('admin.common.name')
                        }}<input
                            v-model="form.name"
                            required
                            class="mt-1 w-full rounded-lg border border-input bg-background text-sm text-foreground"
                    /></label>
                    <p v-if="errors.name" class="text-xs text-rose-600">
                        {{ errors.name }}
                    </p>
                    <label class="block text-xs font-bold text-foreground"
                        >{{ t('admin.common.slug')
                        }}<input
                            v-model="form.slug"
                            required
                            class="mt-1 w-full rounded-lg border border-input bg-background text-sm text-foreground"
                    /></label>
                    <p v-if="errors.slug" class="text-xs text-rose-600">
                        {{ errors.slug }}
                    </p>
                    <button
                        :disabled="processing"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                    >
                        <Plus class="size-4" />{{
                            editingId
                                ? t('admin.categories.update')
                                : t('admin.categories.create')
                        }}
                </button>
            </form>
        </div>
    </AppModal>
</template>
