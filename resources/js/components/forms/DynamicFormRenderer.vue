<script setup lang="ts">
import { computed } from 'vue';
import MoneyInput from '@/components/MoneyInput.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { useI18n } from '@/lib/i18n';

type Field = {
    key: string;
    name: string;
    label: string;
    type: string;
    required: boolean;
    placeholder: string;
    helpText: string;
    options: Array<{ label: string; value: string }>;
    width: number;
    mobileWidth: number;
    height: number;
};

const { t } = useI18n();

const props = withDefaults(defineProps<{
    schema: unknown;
    modelValue: Record<string, unknown>;
    currency?: string;
    disabled?: boolean;
}>(), { currency: 'BRL', disabled: false });
const emit = defineEmits<{ 'update:modelValue': [value: Record<string, unknown>] }>();

const span = (value: unknown): number => typeof value === 'number' && value >= 1 && value <= 12
    ? value
    : ({ full: 12, half: 6, third: 4 } as Record<string, number>)[String(value)] ?? 12;
const mobileClasses = ['','col-span-1','col-span-2','col-span-3','col-span-4','col-span-5','col-span-6','col-span-7','col-span-8','col-span-9','col-span-10','col-span-11','col-span-12'];
const desktopClasses = ['','md:col-span-1','md:col-span-2','md:col-span-3','md:col-span-4','md:col-span-5','md:col-span-6','md:col-span-7','md:col-span-8','md:col-span-9','md:col-span-10','md:col-span-11','md:col-span-12'];

const fields = computed<Field[]>(() => {
    const record = props.schema as { fields?: unknown[] } | null;
    const raw = Array.isArray(props.schema)
        ? props.schema
        : Array.isArray(record?.fields)
          ? record.fields
          : [];
    return raw.filter((item): item is Record<string, unknown> => Boolean(item && typeof item === 'object')).map((item, index) => {
        const name = String(item.name ?? item.key ?? item.id ?? '');
        const options = Array.isArray(item.options) ? item.options.map((option) => typeof option === 'string'
            ? { label: option, value: option }
            : { label: String(option.label ?? option.name ?? option.value ?? ''), value: String(option.value ?? option.id ?? option.label ?? '') }) : [];
        return {
            key: name || `element-${index}`, name, label: String(item.label ?? name),
            type: String(item.type ?? 'text').toLowerCase(), required: Boolean(item.required),
            placeholder: String(item.placeholder ?? ''), helpText: String(item.helpText ?? item.help_text ?? ''),
            options, width: span(item.width), mobileWidth: span(item.mobile_width), height: Math.max(2, Number(item.height ?? 4)),
        };
    });
});
const value = (field: Field): unknown => props.modelValue[field.key] ?? (field.type === 'checkbox' ? false : '');
const update = (field: Field, next: unknown): void => emit('update:modelValue', { ...props.modelValue, [field.key]: next });
</script>

<template>
    <div class="grid grid-cols-12 gap-4">
        <template v-for="field in fields" :key="field.key">
            <h3 v-if="field.type === 'heading'" class="col-span-full text-lg font-black text-slate-900">{{ field.label }}</h3>
            <hr v-else-if="field.type === 'divider'" class="col-span-full border-slate-200" />
            <div v-else-if="field.type === 'line_break'" class="col-span-full h-1" />
            <div v-else :class="['space-y-1.5', mobileClasses[field.mobileWidth], desktopClasses[field.width]]">
                <label class="text-sm font-bold text-slate-700">{{ field.label }}<span v-if="field.required" class="text-rose-600"> *</span></label>
                <textarea v-if="field.type === 'textarea'" :value="String(value(field))" :rows="field.height" :disabled="disabled" class="w-full rounded-xl border-slate-300 text-sm" :placeholder="field.placeholder" @input="update(field, ($event.target as HTMLTextAreaElement).value)" />
                <select v-else-if="field.type === 'select'" :value="String(value(field))" :disabled="disabled" class="w-full rounded-xl border-slate-300 text-sm" @change="update(field, ($event.target as HTMLSelectElement).value)">
                    <option value="">{{ t('classrooms.form.select') }}</option>
                    <option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <div v-else-if="field.type === 'radio'" class="space-y-2">
                    <label v-for="option in field.options" :key="option.value" class="flex items-center gap-2 text-sm"><input type="radio" :name="field.name" :checked="value(field) === option.value" :disabled="disabled" @change="update(field, option.value)" />{{ option.label }}</label>
                </div>
                <label v-else-if="field.type === 'checkbox'" class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm"><input type="checkbox" :checked="Boolean(value(field))" :disabled="disabled" @change="update(field, ($event.target as HTMLInputElement).checked)" />{{ field.placeholder || field.label }}</label>
                <PhoneInput v-else-if="field.type === 'phone'" :model-value="String(value(field))" :name="field.name" :required="field.required" :disabled="disabled" @update:model-value="update(field, $event)" />
                <MoneyInput v-else-if="field.type === 'money'" :model-value="String(value(field))" :currency="currency" lock-currency :name="field.name" :required="field.required" :disabled="disabled" @update:model-value="update(field, $event)" />
                <input v-else :value="String(value(field))" :type="field.type" :disabled="disabled" class="w-full rounded-xl border-slate-300 text-sm" :placeholder="field.placeholder" @input="update(field, ($event.target as HTMLInputElement).value)" />
                <p v-if="field.helpText" class="text-xs text-slate-500">{{ field.helpText }}</p>
            </div>
        </template>
    </div>
</template>
