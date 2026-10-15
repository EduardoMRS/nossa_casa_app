<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from '@/lib/i18n';

type Currency = { code: string; locale: string; symbol: string; flag: string };
const currencies: Currency[] = [
    { code: 'BRL', locale: 'pt-BR', symbol: 'R$', flag: '🇧🇷' },
    { code: 'USD', locale: 'en-US', symbol: '$', flag: '🇺🇸' },
    { code: 'EUR', locale: 'de-DE', symbol: '€', flag: '🇪🇺' },
    { code: 'GBP', locale: 'en-GB', symbol: '£', flag: '🇬🇧' },
    { code: 'ARS', locale: 'es-AR', symbol: '$', flag: '🇦🇷' },
    { code: 'PYG', locale: 'es-PY', symbol: '₲', flag: '🇵🇾' },
    { code: 'BOB', locale: 'es-BO', symbol: 'Bs', flag: '🇧🇴' },
    { code: 'CLP', locale: 'es-CL', symbol: '$', flag: '🇨🇱' },
    { code: 'COP', locale: 'es-CO', symbol: '$', flag: '🇨🇴' },
    { code: 'MXN', locale: 'es-MX', symbol: '$', flag: '🇲🇽' },
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
        class="money-input flex h-9 w-full min-w-0 max-w-full overflow-hidden rounded-md border border-input bg-background shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
    >
        <select
            v-model="selectedCode"
            :name="currencyName"
            :disabled="disabled || lockCurrency"
            :aria-label="t('money.currency')"
            class="money-input__currency h-full w-32 shrink-0 rounded-l-md border-0 bg-transparent px-2 py-1 text-sm text-foreground outline-none focus:ring-0 disabled:cursor-not-allowed disabled:opacity-50 sm:w-36"
        >
            <option
                v-for="item in currencies"
                :key="item.code"
                :value="item.code"
            >
                {{ item.flag }} {{ item.symbol }}
            </option>
        </select>
        <input
            v-model="rawValue"
            type="text"
            inputmode="decimal"
            :required="required"
            :disabled="disabled"
            :placeholder="t('money.placeholder')"
            class="money-input__value h-full w-0 min-w-0 flex-1 rounded-r-md border-0 bg-transparent px-3 py-1 text-sm text-slate-600 outline-none placeholder:text-muted-foreground focus:ring-0 disabled:cursor-not-allowed disabled:opacity-50"
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

<style scoped>
.money-input__currency option {
    background-color: var(--popover);
    color: var(--popover-foreground);
}

.money-input__value:-webkit-autofill,
.money-input__value:-webkit-autofill:hover,
.money-input__value:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--foreground);
    box-shadow: 0 0 0 1000px transparent inset;
    transition: background-color 9999s ease-out;
}
</style>
