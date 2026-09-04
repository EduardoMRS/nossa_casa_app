<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MoneyInput from '@/components/MoneyInput.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { usePublicTemplate } from '@/composables/usePublicTemplate';
import { useI18n } from '@/lib/i18n';
import { useRepositories } from '@/lib/repositories';
import { login } from '@/routes';
import { show as showEvent } from '@/routes/events';
import { ApiError } from '@/shared/http/FetchHttpClient';

interface SchemaOption {
    label: string;
    value: string;
}

interface SchemaField {
    key: string;
    name: string;
    label: string;
    type: string;
    required: boolean;
    placeholder: string;
    helpText: string;
    options: SchemaOption[];
    width: number;
    mobileWidth: number;
    size: 'auto' | 'fixed';
    height: number;
    structural: boolean;
}

const props = defineProps<{
    event: {
        id: string;
        title: string;
        slug: string;
        description_html: string;
        start_time: string;
        end_time: string;
        church?: {
            id: string;
            name: string;
            slug: string;
        } | null;
        currency: string;
    };
    form: {
        id: string;
        title: string;
        description: string | null;
        schema: unknown;
    };
    existingAnswers: Record<string, unknown> | null;
    alreadyRegistered: boolean;
}>();
const { forms } = useRepositories();

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const { locale, t } = useI18n();
const publicTemplate = usePublicTemplate('form');
const processing = ref(false);
const flashMessage = ref('');
const errors = ref<Record<string, string>>({});

const normalizeOptions = (source: unknown): SchemaOption[] => {
    if (!Array.isArray(source)) {
        return [];
    }

    return source
        .map((item): SchemaOption | null => {
            if (typeof item === 'string') {
                return { label: item, value: item };
            }

            if (item && typeof item === 'object') {
                const record = item as Record<string, unknown>;
                const rawLabel = record.label ?? record.name ?? record.value;
                const rawValue = record.value ?? record.id ?? record.label;

                if (
                    typeof rawLabel === 'string' &&
                    typeof rawValue === 'string'
                ) {
                    return { label: rawLabel, value: rawValue };
                }
            }

            return null;
        })
        .filter((item): item is SchemaOption => item !== null);
};

const normalizeColumnSpan = (value: unknown): number => {
    if (typeof value === 'number' && value >= 1 && value <= 12) {
        return value;
    }

    return (
        ({ full: 12, half: 6, third: 4 } as Record<string, number>)[
            String(value)
        ] ?? 12
    );
};

const columnClasses: Record<number, string> = {
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
const desktopColumnClasses: Record<number, string> = {
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

const schemaFields = computed<SchemaField[]>(() => {
    const source = props.form.schema as unknown;
    const rawFields = Array.isArray(source)
        ? source
        : source &&
            typeof source === 'object' &&
            Array.isArray((source as { fields?: unknown[] }).fields)
          ? ((source as { fields: unknown[] }).fields ?? [])
          : [];

    return rawFields
        .map((item, index): SchemaField | null => {
            if (!item || typeof item !== 'object') {
                return null;
            }

            const record = item as Record<string, unknown>;
            const type = String(record.type ?? 'text').toLowerCase();
            const structural = ['heading', 'divider', 'line_break'].includes(
                type,
            );
            const name = String(
                record.name ?? record.key ?? record.id ?? '',
            ).trim();

            if (!name && !structural) {
                return null;
            }

            return {
                key: name || `element-${index}`,
                name,
                label: String(record.label ?? name),
                type,
                required: Boolean(record.required ?? false),
                placeholder: String(record.placeholder ?? ''),
                helpText: String(record.helpText ?? record.help_text ?? ''),
                options: normalizeOptions(record.options),
                width: normalizeColumnSpan(record.width),
                mobileWidth: normalizeColumnSpan(record.mobile_width),
                size: record.size === 'fixed' ? 'fixed' : 'auto',
                height: Math.max(1, Number(record.height ?? 4)),
                structural,
            };
        })
        .filter((field): field is SchemaField => field !== null);
});

const answers = ref<Record<string, unknown>>({
    ...(props.existingAnswers ?? {}),
});

const getFieldValue = (field: SchemaField): unknown => {
    const value = answers.value[field.key];

    if (value !== undefined) {
        return value;
    }

    if (field.type === 'checkbox') {
        return false;
    }

    if (field.type === 'select-multiple') {
        return [];
    }

    return '';
};

const updateFieldValue = (field: SchemaField, value: unknown): void => {
    answers.value[field.key] = value;
};

const fieldWidthClass = (field: SchemaField): string => {
    return `${columnClasses[field.mobileWidth]} ${desktopColumnClasses[field.width]}`;
};

const submit = async () => {
    if (!isAuthenticated.value) {
        return;
    }

    processing.value = true;
    errors.value = {};
    flashMessage.value = '';

    try {
        await forms.submit(props.form.id, answers.value);

        flashMessage.value = t('events.register.success');
    } catch (error: unknown) {
        if (error instanceof ApiError && error.status === 422) {
            const payload = error.details as Record<string, string[]> | undefined;
            const mappedErrors: Record<string, string> = {};

            Object.entries(payload ?? {}).forEach(([key, value]) => {
                mappedErrors[key] =
                    value[0] ?? t('events.register.invalid_field');
            });

            errors.value = mappedErrors;
        } else {
            flashMessage.value = t('events.register.error');
        }
    } finally {
        processing.value = false;
    }
};

const formatDate = (value: string): string => {
    return new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};
</script>

<template>
    <Head :title="`${t('events.register.title')} - ${event.title}`" />

    <div
        class="public-template-page flex min-h-screen flex-col bg-[#f8fafc] text-slate-950"
        :data-public-template="publicTemplate"
    >
        <PublicHeader active="events" />

        <main
            class="mx-auto w-full max-w-5xl px-3 py-5 sm:px-5 sm:py-8 md:px-8 md:py-10"
        >
            <section
                class="mb-5 rounded-2xl bg-gradient-to-r from-[#0b3d44] via-[#125a63] to-[#0f7a69] p-5 text-white sm:mb-6 sm:rounded-3xl sm:p-6 md:p-8"
            >
                <p
                    class="mb-2 text-xs tracking-[0.2em] text-[#a9f4e3] uppercase"
                >
                    {{ t('events.register.title') }}
                </p>
                <h1
                    class="[font-family:Manrope,ui-sans-serif] text-2xl font-black md:text-4xl"
                >
                    {{ event.title }}
                </h1>
                <p class="mt-2 text-sm text-[#d8f6ef]">
                    {{ formatDate(event.start_time) }}
                    {{ t('events.register.until') }}
                    {{ formatDate(event.end_time) }}
                </p>
                <div class="mt-4">
                    <Link
                        :href="showEvent({ event: event.slug })"
                        class="rounded-full border border-white/35 px-4 py-2 text-xs font-semibold text-white"
                    >
                        {{ t('events.register.back_to_event') }}
                    </Link>
                </div>
            </section>

            <section
                class="rounded-2xl border border-[#d8e2ec] bg-white p-4 shadow-sm sm:rounded-3xl sm:p-6 md:p-8"
            >
                <div class="mb-6">
                    <h2
                        class="[font-family:Manrope,ui-sans-serif] text-xl font-black"
                    >
                        {{ form.title }}
                    </h2>
                    <p
                        v-if="form.description"
                        class="mt-1 text-sm text-[#4f6479]"
                    >
                        {{ form.description }}
                    </p>
                </div>

                <div
                    v-if="flashMessage"
                    class="mb-4 rounded-xl border border-[#cde6d5] bg-[#ecf9f0] px-4 py-3 text-sm text-[#1c5f35]"
                >
                    {{ flashMessage }}
                </div>

                <div
                    v-if="alreadyRegistered"
                    class="mb-4 rounded-xl border border-[#d3e2ef] bg-[#f1f7fc] px-4 py-3 text-sm text-[#2f4f69]"
                >
                    {{ t('events.register.already_registered') }}
                </div>

                <div
                    v-if="!isAuthenticated"
                    class="rounded-xl border border-[#e4d5c0] bg-[#fff6ea] px-4 py-3 text-sm text-[#6e4a24]"
                >
                    {{ t('events.register.login_required') }}
                    <Link
                        :href="login()"
                        class="ml-2 font-bold text-[#15486b]"
                        >{{ t('nav.login') }}</Link
                    >
                </div>

                <form v-else class="space-y-5" @submit.prevent="submit">
                    <div
                        v-if="schemaFields.length === 0"
                        class="rounded-xl border border-[#e4d5c0] bg-[#fff6ea] px-4 py-3 text-sm text-[#6e4a24]"
                    >
                        {{ t('events.register.schema_empty') }}
                    </div>

                    <div class="grid grid-cols-12 gap-5">
                        <template
                            v-for="field in schemaFields"
                            :key="field.key"
                        >
                            <h3
                                v-if="field.type === 'heading'"
                                class="col-span-full text-xl font-black text-[#20374f]"
                            >
                                {{ field.label }}
                            </h3>
                            <hr
                                v-else-if="field.type === 'divider'"
                                class="col-span-full border-[#d4e0ea]"
                            />
                            <div
                                v-else-if="field.type === 'line_break'"
                                class="col-span-full h-2"
                            />
                            <div
                                v-else
                                :class="['space-y-2', fieldWidthClass(field)]"
                            >
                                <label class="text-sm font-bold text-[#20374f]">
                                    {{ field.label }}
                                    <span
                                        v-if="field.required"
                                        class="text-[#b04222]"
                                        >*</span
                                    >
                                </label>

                                <textarea
                                    v-if="field.type === 'textarea'"
                                    :value="String(getFieldValue(field))"
                                    class="w-full rounded-xl border border-[#d4e0ea] px-3 py-2.5 text-sm"
                                    :placeholder="field.placeholder"
                                    :rows="
                                        field.size === 'fixed'
                                            ? field.height
                                            : 4
                                    "
                                    @input="
                                        updateFieldValue(
                                            field,
                                            (
                                                $event.target as HTMLTextAreaElement
                                            ).value,
                                        )
                                    "
                                />

                                <select
                                    v-else-if="field.type === 'select'"
                                    :value="String(getFieldValue(field))"
                                    class="w-full rounded-xl border border-[#d4e0ea] px-3 py-2.5 text-sm"
                                    @change="
                                        updateFieldValue(
                                            field,
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <option value="">
                                        {{ t('events.register.select') }}
                                    </option>
                                    <option
                                        v-for="option in field.options"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>

                                <div
                                    v-else-if="field.type === 'radio'"
                                    class="space-y-2"
                                >
                                    <label
                                        v-for="option in field.options"
                                        :key="option.value"
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            :checked="
                                                getFieldValue(field) ===
                                                option.value
                                            "
                                            type="radio"
                                            :name="field.name"
                                            :value="option.value"
                                            @change="
                                                updateFieldValue(
                                                    field,
                                                    option.value,
                                                )
                                            "
                                        />
                                        {{ option.label }}
                                    </label>
                                </div>

                                <label
                                    v-else-if="field.type === 'checkbox'"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="Boolean(getFieldValue(field))"
                                        @change="
                                            updateFieldValue(
                                                field,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                    {{
                                        field.placeholder ||
                                        t('events.register.mark_option')
                                    }}
                                </label>

                                <PhoneInput
                                    v-else-if="field.type === 'phone'"
                                    :model-value="String(getFieldValue(field))"
                                    :name="field.name"
                                    :required="field.required"
                                    :placeholder="field.placeholder"
                                    @update:model-value="
                                        updateFieldValue(field, $event)
                                    "
                                />

                                <MoneyInput
                                    v-else-if="field.type === 'money'"
                                    :model-value="String(getFieldValue(field))"
                                    :currency="event.currency"
                                    lock-currency
                                    :name="field.name"
                                    :required="field.required"
                                    @update:model-value="
                                        updateFieldValue(field, $event)
                                    "
                                />

                                <input
                                    v-else
                                    :value="String(getFieldValue(field))"
                                    :type="field.type"
                                    class="w-full rounded-xl border border-[#d4e0ea] px-3 py-2.5 text-sm"
                                    :placeholder="field.placeholder"
                                    @input="
                                        updateFieldValue(
                                            field,
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />

                                <p
                                    v-if="field.helpText"
                                    class="text-xs text-[#617a91]"
                                >
                                    {{ field.helpText }}
                                </p>
                                <p
                                    v-if="errors[`answers.${field.key}`]"
                                    class="text-xs text-red-600"
                                >
                                    {{ errors[`answers.${field.key}`] }}
                                </p>
                            </div>
                        </template>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="processing || schemaFields.length === 0"
                            class="rounded-full bg-[#0f5564] px-6 py-2.5 text-sm font-bold text-white transition hover:bg-[#0c4652] disabled:opacity-60"
                        >
                            {{
                                processing
                                    ? t('events.register.submitting')
                                    : t('events.register.submit')
                            }}
                        </button>
                    </div>
                </form>
            </section>
        </main>
        <PublicFooter show-locale />
    </div>
</template>
