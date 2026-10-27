<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2, Users } from '@lucide/vue';
import { reactive, ref } from 'vue';
import AdminPageHeader from '@/components/AdminPageHeader.vue';
import AppModal from '@/components/AppModal.vue';
import CategoryManagerModal from '@/components/CategoryManagerModal.vue';
import CategorySelector from '@/components/CategorySelector.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useI18n } from '@/lib/i18n';

type Option = { id: string; name: string };
type CategoryOption = { id: string; name: string; slug: string; type: string };
type GroupAddress = {
    country?: string | null;
    state?: string | null;
    city?: string | null;
    neighborhood?: string | null;
    street?: string | null;
    number?: string | null;
    complement?: string | null;
    zipcode?: string | null;
};
type GroupItem = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    social_link: string | null;
    is_public: boolean;
    category_ids: string[];
    responsible_ids: string[];
    address: GroupAddress | null;
};

const props = defineProps<{
    groups: GroupItem[];
    categories: CategoryOption[];
    responsibleOptions: Option[];
}>();

const { t } = useI18n();
const { confirm } = useConfirmDialog();

const modalOpen = ref(false);
const editingId = ref<string | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const categoriesOpen = ref(false);

const emptyAddress = (): GroupAddress => ({
    country: '',
    state: '',
    city: '',
    neighborhood: '',
    street: '',
    number: '',
    complement: '',
    zipcode: '',
});

const form = reactive<{
    name: string;
    description: string;
    social_link: string;
    is_public: boolean;
    category_ids: string[];
    responsible_ids: string[];
    address: GroupAddress;
}>({
    name: '',
    description: '',
    social_link: '',
    is_public: true,
    category_ids: [],
    responsible_ids: [],
    address: emptyAddress(),
});

const openEditor = (group?: GroupItem): void => {
    editingId.value = group?.id ?? null;
    form.name = group?.name ?? '';
    form.description = group?.description ?? '';
    form.social_link = group?.social_link ?? '';
    form.is_public = group?.is_public ?? true;
    form.category_ids = [...(group?.category_ids ?? [])];
    form.responsible_ids = [...(group?.responsible_ids ?? [])];
    form.address = { ...emptyAddress(), ...(group?.address ?? {}) };
    errors.value = {};
    modalOpen.value = true;
};

const save = (): void => {
    processing.value = true;
    const payload = { ...form };
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

    if (editingId.value) {
        router.put(`/dashboard/groups/${editingId.value}`, payload, options);

        return;
    }

    router.post('/dashboard/groups', payload, options);
};

const remove = async (group: GroupItem): Promise<void> => {
    if (
        !(await confirm({
            message: t('admin.groups.delete_confirm', { name: group.name }),
            confirmLabel: t('actions.delete'),
            intent: 'danger',
        }))
    ) {
        return;
    }

    router.delete(`/dashboard/groups/${group.id}`, {
        preserveScroll: true,
        onSuccess: () => router.reload(),
    });
};

const responsibleNames = (group: GroupItem): string =>
    group.responsible_ids
        .map(
            (id) =>
                props.responsibleOptions.find((option) => option.id === id)
                    ?.name,
        )
        .filter(Boolean)
        .join(', ');
</script>

<template>
    <Head :title="t('admin.groups.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <AdminPageHeader
            :kicker="t('admin.groups.kicker')"
            :title="t('admin.groups.title')"
            :description="t('admin.groups.description')"
        >
            <button
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-primary-foreground/30 px-4 py-2.5 text-xs font-black text-primary-foreground"
                @click="categoriesOpen = true"
            >
                <Tags class="size-4" />{{ t('admin.categories.title') }}
            </button>
            <button
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-foreground px-4 py-2.5 text-xs font-black text-primary"
                @click="openEditor()"
            >
                <Plus class="size-4" />{{ t('admin.groups.new') }}
            </button>
        </AdminPageHeader>

        <section
            class="overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div
                class="flex items-center gap-2 border-b border-border p-4 font-black"
            >
                <Users class="size-5 text-indigo-600" />{{
                    t('admin.groups.title')
                }}
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead
                        class="bg-muted text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">
                                {{ t('admin.groups.name') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ t('admin.groups.responsibles') }}
                            </th>
                            <th class="px-5 py-3 text-right">
                                {{ t('posts.index.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="group in groups"
                            :key="group.id"
                            class="hover:bg-muted/60"
                        >
                            <td
                                class="px-5 py-4 font-bold text-card-foreground"
                            >
                                {{ group.name }}
                            </td>
                            <td class="px-5 py-4 text-muted-foreground">
                                {{ responsibleNames(group) || '—' }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-1">
                                    <button
                                        class="rounded-lg p-2 text-indigo-600 hover:bg-indigo-50"
                                        @click="openEditor(group)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <button
                                        class="rounded-lg p-2 text-rose-600 hover:bg-rose-50"
                                        @click="remove(group)"
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
                v-if="!groups.length"
                class="p-12 text-center text-sm text-muted-foreground"
            >
                {{ t('admin.groups.empty') }}
            </p>
        </section>

        <AppModal
            v-model:open="modalOpen"
            :title="editingId ? t('admin.groups.edit') : t('admin.groups.new')"
            scrollable
        >
            <form class="space-y-4" @submit.prevent="save">
                <label class="block text-xs font-bold text-foreground"
                    >{{ t('admin.groups.name')
                    }}<input
                        v-model="form.name"
                        required
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                /></label>
                <p v-if="errors.name" class="text-xs text-rose-600">
                    {{ errors.name }}
                </p>

                <label class="block text-xs font-bold text-foreground"
                    >{{ t('admin.groups.description_field')
                    }}<textarea
                        v-model="form.description"
                        rows="3"
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                    ></textarea>
                </label>

                <label class="block text-xs font-bold text-foreground"
                    >{{ t('admin.groups.social_link')
                    }}<input
                        v-model="form.social_link"
                        type="url"
                        inputmode="url"
                        placeholder="https://chat.whatsapp.com/..."
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                /></label>
                <p v-if="errors.social_link" class="text-xs text-rose-600">
                    {{ errors.social_link }}
                </p>

                <div class="space-y-1">
                    <CategorySelector
                        v-model="form.category_ids"
                        :categories="categories"
                        :label="t('admin.groups.category')"
                    />
                    <a
                        href="/dashboard/categories"
                        class="inline-block text-xs font-bold text-[color:var(--dashboard-primary,#4338ca)] underline"
                    >
                        {{ t('admin.groups.manage_categories') }}
                    </a>
                </div>

                <label class="block text-xs font-bold text-foreground"
                    >{{ t('admin.groups.responsibles')
                    }}<select
                        v-model="form.responsible_ids"
                        multiple
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                    >
                        <option
                            v-for="option in responsibleOptions"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.name }}
                        </option>
                    </select></label
                >

                <fieldset class="grid grid-cols-2 gap-2">
                    <legend class="text-xs font-bold text-foreground">
                        {{ t('admin.groups.location') }}
                    </legend>
                    <input
                        v-model="form.address.street"
                        :placeholder="t('admin.groups.location')"
                        class="col-span-2 rounded-lg border-input bg-background text-sm text-foreground"
                    />
                    <input
                        v-model="form.address.number"
                        placeholder="Nº"
                        class="rounded-lg border-input bg-background text-sm text-foreground"
                    />
                    <input
                        v-model="form.address.neighborhood"
                        class="rounded-lg border-input bg-background text-sm text-foreground"
                    />
                    <input
                        v-model="form.address.city"
                        class="rounded-lg border-input bg-background text-sm text-foreground"
                    />
                    <input
                        v-model="form.address.state"
                        class="rounded-lg border-input bg-background text-sm text-foreground"
                    />
                    <input
                        v-model="form.address.zipcode"
                        placeholder="CEP"
                        class="rounded-lg border-input bg-background text-sm text-foreground"
                    />
                </fieldset>

                <label
                    class="flex items-center gap-2 text-xs font-bold text-foreground"
                >
                    <input v-model="form.is_public" type="checkbox" />
                    {{ t('admin.groups.visibility') }}
                </label>

                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-muted-foreground hover:bg-muted"
                        @click="modalOpen = false"
                    >
                        {{ t('actions.cancel') }}
                    </button>
                    <button
                        :disabled="processing"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground disabled:opacity-50"
                    >
                        {{ t('actions.save') }}
                    </button>
                </div>
            </form>
        </AppModal>
        <CategoryManagerModal
            :open="categoriesOpen"
            category-type="group"
            :categories="categories"
            @close="categoriesOpen = false"
        />
    </main>
</template>
