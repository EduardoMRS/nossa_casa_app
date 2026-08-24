<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Tags } from '@lucide/vue';
import { ref } from 'vue';
import { destroy } from '@/actions/App/Http/Controllers/FormController';
import AdminPageHeader from '@/components/AdminPageHeader.vue';
import CategoryManagerModal from '@/components/CategoryManagerModal.vue';
import type { ManagedCategory } from '@/components/CategoryManagerModal.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useI18n } from '@/lib/i18n';
import { create, edit } from '@/routes/admin/forms';

type Linkable = { id: string; title: string };
type Category = { id: string; name: string };
type ManagedForm = {
    id: string;
    title: string;
    description: string | null;
    events: Linkable[];
    posts: Linkable[];
    categories?: Category[];
    responses_count: number;
};
type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    forms: {
        data: ManagedForm[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    categories: ManagedCategory[];
}>();
const { t } = useI18n();
const { confirm } = useConfirmDialog();
const categoriesOpen = ref(false);

const remove = async (form: ManagedForm): Promise<void> => {
    if (
        !(await confirm({
            message: t('admin.forms.delete_confirm', { title: form.title }),
            confirmLabel: t('actions.delete'),
            intent: 'danger',
        }))
    ) {
        return;
    }

    router.delete(destroy.url({ form: form.id }));
};
</script>

<template>
    <Head :title="t('admin.forms.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <AdminPageHeader
            :kicker="t('navigation.ministries')"
            :title="t('admin.forms.title')"
            :description="t('admin.forms.description')"
        >
            <button
                class="inline-flex items-center gap-2 rounded-xl border border-white/30 px-4 py-3 text-sm font-black text-white"
                @click="categoriesOpen = true"
            >
                <Tags class="size-4" />
                {{ t('admin.categories.title') }}
            </button>
            <Link
                :href="create()"
                class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-black text-indigo-950"
            >
                <Plus class="size-4" />
                {{ t('admin.forms.new') }}
            </Link>
        </AdminPageHeader>

        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead
                        class="border-b bg-slate-50 text-xs font-bold tracking-wide text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">
                                {{ t('admin.forms.title') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ t('admin.forms.links') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ t('admin.forms.responses') }}
                            </th>
                            <th class="px-5 py-3 text-right">
                                {{ t('posts.index.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="form in props.forms.data"
                            :key="form.id"
                            class="transition hover:bg-muted/60"
                        >
                            <td class="px-5 py-4">
                                <p class="font-bold text-slate-900">
                                    {{ form.title }}
                                </p>
                                <p
                                    class="mt-1 max-w-md truncate text-xs text-slate-500"
                                >
                                    {{
                                        form.description ||
                                        t('admin.forms.no_description')
                                    }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                {{ form.events.length + form.posts.length }}
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                {{ form.responses_count }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="edit({ form: form.id })"
                                        class="rounded-lg border p-2 text-indigo-600"
                                    >
                                        {{ t('actions.edit') }}
                                    </Link>
                                    <button
                                        class="rounded-lg border p-2 text-rose-600"
                                        @click="remove(form)"
                                    >
                                        {{ t('actions.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-if="!props.forms.data.length"
                class="p-12 text-center text-sm text-slate-500"
            >
                {{ t('admin.forms.empty') }}
            </p>
            <nav class="flex flex-wrap gap-2 border-t p-4">
                <Link
                    v-for="link in props.forms.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'rounded-lg border px-3 py-1.5 text-xs',
                        link.active
                            ? 'bg-indigo-700 text-white'
                            : 'bg-white text-slate-600',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                >
                    <span v-html="link.label" />
                </Link>
            </nav>
        </section>
        <CategoryManagerModal
            :open="categoriesOpen"
            category-type="form"
            :categories="categories"
            @close="categoriesOpen = false"
        />
    </main>
</template>
