<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { store, update } from '@/actions/App/Http/Controllers/FormController';
import CategorySelector from '@/components/CategorySelector.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { useI18n } from '@/lib/i18n';
import { index } from '@/routes/admin/forms';

type Linkable = { id: string; title: string };
type Category = { id: string; name: string; slug: string; type: string };
type Width = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12;
type FormElement = {
    id: string;
    type: string;
    name?: string;
    label?: string;
    required?: boolean;
    options?: string[];
    width?: Width;
    mobile_width?: Width;
    size?: 'auto' | 'fixed';
    height?: number;
    placeholder?: string;
    help_text?: string;
};
type FormResource = {
    id: string;
    title: string;
    description: string | null;
    schema: { fields: FormElement[] };
    events: Linkable[];
    posts: Linkable[];
    categories?: Category[];
};

const props = defineProps<{
    form?: FormResource;
    events: Linkable[];
    posts: Linkable[];
    categories: Category[];
}>();
const { t } = useI18n();
const saving = ref(false);
const error = ref('');
const fields = ref<FormElement[]>([]);
const formState = ref({
    title: props.form?.title ?? '',
    description: props.form?.description ?? '',
    category_ids: props.form?.categories?.map((category) => category.id) ?? [],
    event_ids: props.form?.events.map((event) => event.id) ?? [],
    post_ids: props.form?.posts.map((post) => post.id) ?? [],
});

const fieldTypes = computed(
    () =>
        [
            ['text', t('admin.forms.types.text')],
            ['email', t('admin.forms.types.email')],
            ['phone', t('admin.forms.types.phone')],
            ['money', t('admin.forms.types.money')],
            ['number', t('admin.forms.types.number')],
            ['date', t('admin.forms.types.date')],
            ['textarea', t('admin.forms.types.textarea')],
            ['select', t('admin.forms.types.select')],
            ['radio', t('admin.forms.types.radio')],
            ['checkbox', t('admin.forms.types.checkbox')],
            ['heading', t('admin.forms.types.heading')],
            ['divider', t('admin.forms.types.divider')],
            ['line_break', t('admin.forms.types.line_break')],
        ] as const,
);
const inputTypes = new Set([
    'text',
    'email',
    'phone',
    'money',
    'number',
    'date',
    'textarea',
    'select',
    'radio',
    'checkbox',
]);
const widths: Width[] = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
const mobileSpanClasses: Record<Width, string> = {
    1: 'col-span-1',
    2: 'col-span-2',
    3: 'col-span-3',
    4: 'col-span-4',
    5: 'col-span-5',
    6: 'col-span-6',
    7: 'col-span-7',
    8: 'col-span-8',
    9: 'col-span-9',
    10: 'col-span-10',
    11: 'col-span-11',
    12: 'col-span-12',
};
const desktopSpanClasses: Record<Width, string> = {
    1: 'md:col-span-1',
    2: 'md:col-span-2',
    3: 'md:col-span-3',
    4: 'md:col-span-4',
    5: 'md:col-span-5',
    6: 'md:col-span-6',
    7: 'md:col-span-7',
    8: 'md:col-span-8',
    9: 'md:col-span-9',
    10: 'md:col-span-10',
    11: 'md:col-span-11',
    12: 'md:col-span-12',
};

function normalizeWidth(value: unknown): Width {
    if (typeof value === 'number' && value >= 1 && value <= 12) {
        return value as Width;
    }

    return (
        ({ full: 12, half: 6, third: 4 } as Record<string, Width>)[
            String(value)
        ] ?? 12
    );
}

function emptyElement(type = 'text'): FormElement {
    return {
        id: crypto.randomUUID(),
        type,
        name:
            type === 'heading' || type === 'divider' || type === 'line_break'
                ? undefined
                : `campo_${fields.value.length + 1}`,
        label:
            type === 'heading'
                ? t('admin.forms.new_heading')
                : type === 'divider' || type === 'line_break'
                  ? undefined
                  : t('admin.forms.new_field'),
        required: false,
        options: ['select', 'radio'].includes(type)
            ? [t('admin.forms.option_one')]
            : [],
        width: 12,
        mobile_width: 12,
        size: 'auto',
        height: 4,
        placeholder: '',
        help_text: '',
    };
}

fields.value = props.form?.schema?.fields?.map((field) => ({
    ...emptyElement(field.type),
    ...field,
    id: field.id || crypto.randomUUID(),
    width: normalizeWidth(field.width),
    mobile_width: normalizeWidth(field.mobile_width),
})) ?? [emptyElement()];

function addField(type: string): void {
    fields.value.push(emptyElement(type));
}

function removeField(index: number): void {
    fields.value.splice(index, 1);
}

function moveField(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target >= 0 && target < fields.value.length) {
        [fields.value[index], fields.value[target]] = [
            fields.value[target],
            fields.value[index],
        ];
    }
}

function needsOptions(field: FormElement): boolean {
    return ['select', 'radio'].includes(field.type);
}

function fieldWidthClass(field: FormElement): string {
    const mobile = normalizeWidth(field.mobile_width);
    const desktop = normalizeWidth(field.width);

    return `${mobileSpanClasses[mobile]} ${desktopSpanClasses[desktop]}`;
}

async function save(): Promise<void> {
    try {
        saving.value = true;
        error.value = '';
        const payload = {
            ...formState.value,
            schema: { fields: fields.value },
        };

        if (props.form) {
            await axios.put(update.url({ form: props.form.id }), payload);
        } else {
            await axios.post(store.url(), payload);
        }

        router.visit(index());
    } catch (caught) {
        error.value = axios.isAxiosError(caught)
            ? Object.values(caught.response?.data?.errors ?? {})
                  .flat()
                  .join(' ') || t('admin.forms.save_error')
            : t('admin.forms.save_error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head :title="props.form ? t('admin.forms.edit') : t('admin.forms.new')" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="flex flex-col justify-between gap-4 rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white shadow-sm md:flex-row md:items-end"
        >
            <div>
                <p
                    class="text-xs font-bold tracking-[0.2em] text-blue-200 uppercase"
                >
                    {{ t('navigation.ministries') }}
                </p>
                <h1 class="mt-2 text-3xl font-black">
                    {{
                        props.form
                            ? t('admin.forms.edit')
                            : t('admin.forms.new')
                    }}
                </h1>
                <p class="mt-2 text-sm text-blue-100">
                    {{ t('admin.forms.editor_description') }}
                </p>
            </div>
            <Link
                :href="index()"
                class="rounded-xl border border-white/30 px-4 py-3 text-sm font-black text-white"
            >
                {{ t('nav.back') }}
            </Link>
        </header>

        <form
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]"
            @submit.prevent="save"
        >
            <section
                class="space-y-5 rounded-2xl border bg-white p-5 text-slate-900 shadow-sm md:p-7"
            >
                <p
                    v-if="error"
                    class="rounded-lg bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ error }}
                </p>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="text-sm font-semibold">
                        {{ t('admin.common.title') }}
                        <input
                            v-model="formState.title"
                            required
                            class="mt-1 w-full rounded-lg border-slate-300"
                        />
                    </label>
                    <label class="text-sm font-semibold">
                        {{ t('admin.common.description') }}
                        <input
                            v-model="formState.description"
                            class="mt-1 w-full rounded-lg border-slate-300"
                        />
                    </label>
                </div>

                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-sm font-bold">
                        {{ t('admin.forms.add_element') }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="[value, label] in fieldTypes"
                            :key="value"
                            type="button"
                            class="rounded-full border bg-white px-3 py-1 text-xs font-semibold"
                            @click="addField(value)"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>

                <section class="space-y-3">
                    <article
                        v-for="(field, fieldIndex) in fields"
                        :key="field.id"
                        class="rounded-xl border p-4"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <span
                                class="text-xs font-bold text-slate-500 uppercase"
                                >{{ field.type }}</span
                            >
                            <div class="flex gap-2 text-xs">
                                <button
                                    type="button"
                                    @click="moveField(fieldIndex, -1)"
                                >
                                    ↑
                                </button>
                                <button
                                    type="button"
                                    @click="moveField(fieldIndex, 1)"
                                >
                                    ↓
                                </button>
                                <button
                                    type="button"
                                    class="text-red-600"
                                    @click="removeField(fieldIndex)"
                                >
                                    {{ t('admin.common.remove') }}
                                </button>
                            </div>
                        </div>
                        <template v-if="inputTypes.has(field.type)">
                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="text-sm">
                                    {{ t('admin.forms.label') }}
                                    <input
                                        v-model="field.label"
                                        class="mt-1 w-full rounded-lg border-slate-300"
                                    />
                                </label>
                                <label class="text-sm">
                                    {{ t('admin.forms.identifier') }}
                                    <input
                                        v-model="field.name"
                                        class="mt-1 w-full rounded-lg border-slate-300"
                                    />
                                </label>
                                <label class="text-sm">
                                    {{ t('admin.forms.width') }}
                                    <select
                                        v-model.number="field.width"
                                        class="mt-1 w-full rounded-lg border-slate-300"
                                    >
                                        <option
                                            v-for="width in widths"
                                            :key="width"
                                            :value="width"
                                        >
                                            {{ width }}/12
                                        </option>
                                    </select>
                                </label>
                                <label class="text-sm">
                                    {{ t('admin.forms.mobile_width') }}
                                    <select
                                        v-model.number="field.mobile_width"
                                        class="mt-1 w-full rounded-lg border-slate-300"
                                    >
                                        <option
                                            v-for="width in widths"
                                            :key="width"
                                            :value="width"
                                        >
                                            {{ width }}/12
                                        </option>
                                    </select>
                                </label>
                                <label class="text-sm">
                                    {{ t('admin.forms.size') }}
                                    <select
                                        v-model="field.size"
                                        class="mt-1 w-full rounded-lg border-slate-300"
                                    >
                                        <option value="auto">
                                            {{ t('admin.forms.dynamic') }}
                                        </option>
                                        <option value="fixed">
                                            {{ t('admin.forms.fixed') }}
                                        </option>
                                    </select>
                                </label>
                            </div>
                            <label class="mt-3 flex gap-2 text-sm">
                                <input
                                    v-model="field.required"
                                    type="checkbox"
                                />
                                {{ t('admin.forms.required') }}
                            </label>
                            <label
                                v-if="
                                    field.type === 'textarea' &&
                                    field.size === 'fixed'
                                "
                                class="mt-3 block text-sm"
                            >
                                {{ t('admin.forms.height') }}
                                <input
                                    v-model.number="field.height"
                                    type="number"
                                    min="1"
                                    max="20"
                                    class="mt-1 w-32 rounded-lg border-slate-300"
                                />
                            </label>
                            <label
                                v-if="needsOptions(field)"
                                class="mt-3 block text-sm"
                            >
                                {{ t('admin.forms.options') }}
                                <textarea
                                    :value="field.options?.join('\n')"
                                    rows="3"
                                    class="mt-1 w-full rounded-lg border-slate-300"
                                    @input="
                                        field.options = (
                                            $event.target as HTMLTextAreaElement
                                        ).value
                                            .split('\n')
                                            .filter(Boolean)
                                    "
                                />
                            </label>
                        </template>
                        <template v-else-if="field.type === 'heading'">
                            <label class="block text-sm">
                                {{ t('admin.common.title') }}
                                <input
                                    v-model="field.label"
                                    class="mt-1 w-full rounded-lg border-slate-300"
                                />
                            </label>
                        </template>
                        <p v-else class="text-sm text-slate-500">
                            {{ t('admin.forms.visual_element') }}
                        </p>
                    </article>
                </section>

                <fieldset class="grid gap-3 md:grid-cols-3">
                    <label class="text-sm font-semibold">
                        {{ t('nav.events') }}
                        <select
                            v-model="formState.event_ids"
                            multiple
                            class="mt-1 h-28 w-full rounded-lg border-slate-300"
                        >
                            <option
                                v-for="event in props.events"
                                :key="event.id"
                                :value="event.id"
                            >
                                {{ event.title }}
                            </option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">
                        {{ t('posts.index.title') }}
                        <select
                            v-model="formState.post_ids"
                            multiple
                            class="mt-1 h-28 w-full rounded-lg border-slate-300"
                        >
                            <option
                                v-for="post in props.posts"
                                :key="post.id"
                                :value="post.id"
                            >
                                {{ post.title }}
                            </option>
                        </select>
                    </label>
                    <CategorySelector
                        v-model="formState.category_ids"
                        :categories="props.categories"
                        :label="t('admin.categories.title')"
                        :hint="t('admin.forms.category_hint')"
                    />
                </fieldset>

                <button
                    :disabled="saving"
                    class="w-full rounded-lg bg-slate-900 py-2 text-sm font-bold text-white disabled:opacity-50"
                >
                    {{
                        saving
                            ? t('admin.common.saving')
                            : props.form
                              ? t('admin.forms.save_changes')
                              : t('admin.forms.create')
                    }}
                </button>
            </section>

            <aside
                class="h-fit rounded-2xl border bg-white p-5 text-slate-900 shadow-sm"
            >
                <h2 class="font-black">{{ t('admin.common.preview') }}</h2>
                <div class="mt-4 grid grid-cols-12 gap-3">
                    <template
                        v-for="field in fields"
                        :key="`preview-${field.id}`"
                    >
                        <h3
                            v-if="field.type === 'heading'"
                            class="col-span-12 text-lg font-black"
                        >
                            {{ field.label }}
                        </h3>
                        <hr
                            v-else-if="field.type === 'divider'"
                            class="col-span-12 border-slate-200"
                        />
                        <div
                            v-else-if="field.type !== 'line_break'"
                            :class="fieldWidthClass(field)"
                        >
                            <label class="text-sm font-semibold">
                                {{ field.label
                                }}<span
                                    v-if="field.required"
                                    class="text-red-600"
                                >
                                    *</span
                                >
                            </label>
                            <textarea
                                v-if="field.type === 'textarea'"
                                :rows="
                                    field.size === 'fixed' ? field.height : 3
                                "
                                disabled
                                class="mt-1 w-full rounded-lg border-slate-300"
                            />
                            <select
                                v-else-if="field.type === 'select'"
                                disabled
                                class="mt-1 w-full rounded-lg border-slate-300"
                            >
                                <option>{{ t('admin.common.select') }}</option>
                            </select>
                            <PhoneInput
                                v-else-if="field.type === 'phone'"
                                disabled
                                class="mt-1"
                            />
                            <MoneyInput
                                v-else-if="field.type === 'money'"
                                disabled
                                class="mt-1"
                            />
                            <input
                                v-else
                                disabled
                                :type="
                                    field.type === 'checkbox'
                                        ? 'checkbox'
                                        : field.type
                                "
                                class="mt-1 rounded-lg border-slate-300"
                            />
                        </div>
                        <div v-else class="col-span-12 h-2" />
                    </template>
                </div>
            </aside>
        </form>
    </main>
</template>
