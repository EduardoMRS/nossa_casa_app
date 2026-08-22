<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from '@/lib/i18n';

type Currency = { code: string; locale: string; symbol: string };
const currencies: Currency[] = [
    { code: 'BRL', locale: 'pt-BR', symbol: 'R$' },
    { code: 'USD', locale: 'en-US', symbol: '$' },
    { code: 'EUR', locale: 'de-DE', symbol: '€' },
    { code: 'GBP', locale: 'en-GB', symbol: '£' },
    { code: 'ARS', locale: 'es-AR', symbol: '$' },
    { code: 'PYG', locale: 'es-PY', symbol: '₲' },
    { code: 'BOB', locale: 'es-BO', symbol: 'Bs' },
    { code: 'CLP', locale: 'es-CL', symbol: '$' },
    { code: 'COP', locale: 'es-CO', symbol: '$' },
    { code: 'MXN', locale: 'es-MX', symbol: '$' },
];
const props = withDefaults(
    defineProps<{
        modelValue?: string | number | null;
        currency?: string;
        name?: string;
        currencyName?: string;
        disabled?: boolean;
        lockCurrency?: boolean;
        required?: boolean;
    }>(),
    {
        modelValue: '',
        currency: 'BRL',
        name: undefined,
        currencyName: undefined,
        disabled: false,
        lockCurrency: false,
        required: false,
    },
);
const emit = defineEmits<{
    'update:modelValue': [value: string];
    'update:currency': [value: string];
}>();
const { t } = useI18n();
const selectedCode = ref(props.currency);
const rawValue = ref(String(props.modelValue ?? ''));
const selectedCurrency = computed(
    () =>
        currencies.find((item) => item.code === selectedCode.value) ??
        currencies[0],
);
const decimalValue = computed(() => {
    const parts = new Intl.NumberFormat(
        selectedCurrency.value.locale,
    ).formatToParts(1234.5);
    const group = parts.find((part) => part.type === 'group')?.value ?? ',';
    const decimal = parts.find((part) => part.type === 'decimal')?.value ?? '.';
    const cleaned = rawValue.value.replace(/[^\d,.-]/g, '');
    const normalized = cleaned.split(group).join('').replace(decimal, '.');
    const parsed = Number(normalized);

    return Number.isFinite(parsed) ? parsed : 0;
});
const format = (): void => {
    rawValue.value = new Intl.NumberFormat(selectedCurrency.value.locale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(decimalValue.value);
};
const update = (): void =>
    emit('update:modelValue', decimalValue.value.toFixed(2));
watch(
    () => props.modelValue,
    (value) => {
        rawValue.value = String(value ?? '');
    },
);
watch(
    () => props.currency,
    (value) => {
        selectedCode.value = value;
    },
);
watch(selectedCode, (value) => {
    emit('update:currency', value);
    format();
    update();
});
</script>

<template>
    <div
        class="flex w-full rounded-lg border border-input bg-background focus-within:ring-2 focus-within:ring-ring/40"
    >
        <select
            v-model="selectedCode"
            :name="currencyName"
            :disabled="disabled || lockCurrency"
            :aria-label="t('money.currency')"
            class="w-28 shrink-0 rounded-l-lg border-0 border-r border-input bg-muted px-2 py-2.5 text-sm focus:ring-0 disabled:opacity-70"
        >
            <option
                v-for="item in currencies"
                :key="item.code"
                :value="item.code"
            >
                {{ item.symbol }} {{ item.code }}
            </option>
        </select>
        <input
            v-model="rawValue"
            type="text"
            inputmode="decimal"
            :required="required"
            :disabled="disabled"
            :placeholder="t('money.placeholder')"
            class="min-w-0 flex-1 rounded-r-lg border-0 bg-transparent px-3 py-2.5 text-sm focus:ring-0"
            @input="update"
            @blur="format"
        />
        <input
            v-if="name"
            type="hidden"
            :name="name"
            :value="decimalValue.toFixed(2)"
        />
    </div>
</template>
