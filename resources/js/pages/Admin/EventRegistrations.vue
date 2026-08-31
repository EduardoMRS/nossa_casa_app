<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Download,
    Eye,
    FileSpreadsheet,
    Plus,
    SlidersHorizontal,
} from '@lucide/vue';
import axios from 'axios';
import { computed, ref } from 'vue';
import AppModal from '@/components/AppModal.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/lib/i18n';
import { index as eventIndex } from '@/routes/admin/events';
import { index as eventContent } from '@/routes/admin/events/content';
import {
    exportMethod as exportRegistrations,
    pdf as registrationPdf,
    store as storeRegistration,
    update as updateRegistration,
} from '@/routes/admin/events/registrations';

type Column = { key: string; label: string };
type FormField = { key: string; label: string; type: string };
type Registration = {
    id: string;
    user_id: string | null;
    first_name: string;
    last_name: string;
    name: string;
    email: string | null;
    phone: string | null;
    status: string;
    registered_at: string;
    answers: Record<string, unknown>;
};

const props = defineProps<{
    event: {
        id: string;
        title: string;
        start_time: string;
        end_time: string;
        church: { id: string; name: string };
    };
    columns: Column[];
    registrations: Registration[];
    formFields: FormField[];
    statuses: string[];
}>();

const { locale, t } = useI18n();
const selectedColumns = ref(props.columns.map((column) => column.key));
const modalOpen = ref(false);
const processing = ref(false);
const editingId = ref<string | null>(null);
const editingHasAccount = ref(false);
const form = ref({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    status: 'pending',
    answers: {} as Record<string, unknown>,
});

const visibleColumns = computed(() =>
    props.columns.filter((column) =>
        selectedColumns.value.includes(column.key),
    ),
);

const isMultilineField = (field: FormField): boolean =>
    ['textarea', 'long_text', 'paragraph'].includes(field.type);

const answerInputType = (field: FormField): string =>
    ['date', 'datetime-local', 'email', 'number', 'tel', 'time', 'url'].includes(
        field.type,
    )
        ? field.type
        : 'text';

const valueAt = (registration: Registration, key: string): unknown => {
    if (key.startsWith('answers.')) {
        return registration.answers[key.slice('answers.'.length)];
    }

    return registration[key as keyof Registration];
};

const displayValue = (registration: Registration, key: string): string => {
    const value = valueAt(registration, key);

    if (key === 'registered_at' && typeof value === 'string') {
        return new Intl.DateTimeFormat(locale.value, {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(new Date(value));
    }

    if (Array.isArray(value)) {
        return value.join(', ');
    }

    if (typeof value === 'boolean') {
        return value ? t('actions.yes') : t('actions.no');
    }

    return String(value ?? '—');
};

const openNew = (): void => {
    editingId.value = null;
    editingHasAccount.value = false;
    form.value = {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        status: 'pending',
        answers: {},
    };
    modalOpen.value = true;
};

const openRegistration = (registration: Registration): void => {
    editingId.value = registration.id;
    editingHasAccount.value = Boolean(registration.user_id);
    form.value = {
        first_name: registration.first_name ?? '',
        last_name: registration.last_name ?? '',
        email: registration.email ?? '',
        phone: registration.phone ?? '',
        status: registration.status,
        answers: { ...registration.answers },
    };
    modalOpen.value = true;
};

const save = async (): Promise<void> => {
    processing.value = true;

    try {
        if (editingId.value) {
            await axios.put(
                updateRegistration.url({
                    event: props.event.id,
                    registration: editingId.value,
                }),
                form.value,
            );
        } else {
            await axios.post(storeRegistration.url(props.event.id), form.value);
        }

        modalOpen.value = false;
        router.reload({ only: ['registrations'] });
    } finally {
        processing.value = false;
    }
};

const exportUrl = (format: 'pdf' | 'xlsx'): string => {
    const query = new URLSearchParams();
    selectedColumns.value.forEach((column) =>
        query.append('columns[]', column),
    );

    return `${exportRegistrations.url({ event: props.event.id, format })}?${query.toString()}`;
};
</script>

<template>
    <Head :title="`${t('admin.event_registrations.title')} - ${event.title}`" />

    <main class="space-y-6 p-4 md:p-8">
        <header
            class="flex flex-col justify-between gap-5 rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white md:flex-row md:items-end"
        >
            <div>
                <Link
                    :href="eventIndex()"
                    class="inline-flex items-center gap-2 text-sm text-indigo-100"
                >
                    <ArrowLeft class="size-4" /> {{ t('actions.back') }}
                </Link>
                <Link
                    :href="eventContent({ event: event.id })"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold"
                >
                    {{ t('admin.event_content.title') }}
                </Link>
                <p
                    class="mt-5 text-xs font-black tracking-widest text-cyan-200 uppercase"
                >
                    {{ event.church.name }}
                </p>
                <h1 class="mt-1 text-3xl font-black">{{ event.title }}</h1>
                <p class="mt-2 text-sm text-indigo-100">
                    {{
                        t('admin.event_registrations.total', {
                            count: registrations.length,
                        })
                    }}
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-black text-indigo-950"
                @click="openNew"
            >
                <Plus class="size-4" />
                {{ t('admin.event_registrations.add') }}
            </button>
        </header>

        <section
            class="relative rounded-2xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b p-4"
            >
                <details class="relative z-30">
                    <summary
                        class="inline-flex cursor-pointer list-none items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm font-bold text-card-foreground transition hover:bg-muted"
                    >
                        <SlidersHorizontal class="size-4" />
                        {{ t('admin.event_registrations.columns') }}
                    </summary>
                    <div
                        class="absolute left-0 z-50 mt-2 max-h-80 w-[min(18rem,calc(100vw-3rem))] space-y-2 overflow-y-auto rounded-xl border border-border bg-popover p-4 text-popover-foreground shadow-xl"
                    >
                        <label
                            v-for="column in columns"
                            :key="column.key"
                            class="flex cursor-pointer items-center gap-2 rounded-md px-1 py-1.5 text-sm hover:bg-muted"
                        >
                            <input
                                v-model="selectedColumns"
                                type="checkbox"
                                :value="column.key"
                                class="rounded"
                            />
                            {{ column.label }}
                        </label>
                    </div>
                </details>
                <div class="flex flex-wrap gap-2">
                    <a
                        :href="exportUrl('pdf')"
                        class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-bold text-rose-700"
                    >
                        <Download class="size-4" /> PDF
                    </a>
                    <a
                        :href="exportUrl('xlsx')"
                        class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-bold text-emerald-700"
                    >
                        <FileSpreadsheet class="size-4" /> XLSX
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto rounded-b-2xl">
                <table class="min-w-full text-left text-sm">
                    <thead
                        class="border-b bg-slate-50 text-xs text-slate-500 uppercase"
                    >
                        <tr>
                            <th
                                v-for="column in visibleColumns"
                                :key="column.key"
                                class="px-4 py-3 whitespace-nowrap"
                            >
                                {{ column.label }}
                            </th>
                            <th class="px-4 py-3 text-right">
                                {{ t('posts.index.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="registration in registrations"
                            :key="registration.id"
                            class="transition hover:bg-muted/60"
                        >
                            <td
                                v-for="column in visibleColumns"
                                :key="column.key"
                                class="max-w-72 px-4 py-3"
                            >
                                {{ displayValue(registration, column.key) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border p-2 text-indigo-700"
                                        :title="
                                            t('admin.event_registrations.view')
                                        "
                                        @click="openRegistration(registration)"
                                    >
                                        <Eye class="size-4" />
                                    </button>
                                    <a
                                        :href="
                                            registrationPdf.url({
                                                event: event.id,
                                                registration: registration.id,
                                            })
                                        "
                                        class="rounded-lg border p-2 text-rose-700"
                                        :title="
                                            t(
                                                'admin.event_registrations.individual_pdf',
                                            )
                                        "
                                    >
                                        <Download class="size-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-if="!registrations.length"
                class="p-12 text-center text-sm text-slate-500"
            >
                {{ t('admin.event_registrations.empty') }}
            </p>
        </section>

        <AppModal
            v-model:open="modalOpen"
            :title="
                editingId
                    ? t('admin.event_registrations.edit')
                    : t('admin.event_registrations.add')
            "
            size="lg"
            scrollable
        >
            <form class="space-y-5" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="space-y-1.5 text-sm font-bold text-foreground">
                        {{ t('admin.event_registrations.first_name') }}
                        <Input
                            v-model="form.first_name"
                            required
                            :readonly="editingHasAccount"
                            class="bg-background read-only:cursor-default read-only:bg-muted/50 read-only:text-foreground"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-foreground">
                        {{ t('admin.event_registrations.last_name') }}
                        <Input
                            v-model="form.last_name"
                            required
                            :readonly="editingHasAccount"
                            class="bg-background read-only:cursor-default read-only:bg-muted/50 read-only:text-foreground"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-foreground">
                        {{ t('admin.event_registrations.email') }}
                        <Input
                            v-model="form.email"
                            type="email"
                            :readonly="editingHasAccount"
                            class="bg-background read-only:cursor-default read-only:bg-muted/50 read-only:text-foreground"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-foreground">
                        {{ t('admin.event_registrations.phone') }}
                        <PhoneInput
                            v-model="form.phone"
                            :disabled="editingHasAccount"
                            class="min-w-0"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-foreground sm:col-span-2">
                        {{ t('admin.event_registrations.status') }}
                        <select
                            v-model="form.status"
                            class="border-input bg-background focus:border-ring focus:ring-ring/50 h-9 w-full min-w-0 rounded-md border px-3 text-sm shadow-xs outline-none focus:ring-[3px]"
                        >
                            <option
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                            >
                                {{
                                    t(
                                        `admin.event_registrations.statuses.${status}`,
                                    )
                                }}
                            </option>
                        </select>
                    </label>
                    <label
                        v-for="field in formFields"
                        :key="field.key"
                        class="space-y-1.5 text-sm font-bold text-foreground sm:col-span-2"
                    >
                        {{ field.label }}
                        <textarea
                            v-if="isMultilineField(field)"
                            :value="String(form.answers[field.key] ?? '')"
                            rows="4"
                            class="border-input bg-background focus:border-ring focus:ring-ring/50 min-h-24 w-full min-w-0 resize-y rounded-md border px-3 py-2 text-sm font-normal shadow-xs outline-none focus:ring-[3px]"
                            @input="
                                form.answers[field.key] = (
                                    $event.target as HTMLTextAreaElement
                                ).value
                            "
                        />
                        <Input
                            v-else
                            :model-value="String(form.answers[field.key] ?? '')"
                            :type="answerInputType(field)"
                            class="bg-background font-normal"
                            @update:model-value="
                                form.answers[field.key] = $event
                            "
                        />
                    </label>
                </div>
                <footer class="flex justify-end gap-2 border-t pt-4">
                    <button
                        type="button"
                        class="rounded-lg border px-4 py-2 text-sm font-bold"
                        @click="modalOpen = false"
                    >
                        {{ t('actions.cancel') }}
                    </button>
                    <button
                        :disabled="processing"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                    >
                        {{ t('actions.save') }}
                    </button>
                </footer>
            </form>
        </AppModal>
    </main>
</template>
