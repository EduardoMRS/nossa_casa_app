<script setup lang="ts">
import { vMaskInput } from 'use-mask-input/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from '@/lib/i18n';

type CountryPhone = {
    code: string;
    dial: string;
    flag: string;
    mask: string | string[];
};

const countries: CountryPhone[] = [
    {
        code: 'BR',
        dial: '+55',
        flag: '🇧🇷',
        mask: ['(99) 9999-9999', '(99) 99999-9999'],
    },
    { code: 'US', dial: '+1', flag: '🇺🇸', mask: '(999) 999-9999' },
    { code: 'PT', dial: '+351', flag: '🇵🇹', mask: '999 999 999' },
    {
        code: 'AR',
        dial: '+54',
        flag: '🇦🇷',
        mask: ['99 9999-9999', '999 999-9999'],
    },
    { code: 'PY', dial: '+595', flag: '🇵🇾', mask: '999 999 999' },
    { code: 'BO', dial: '+591', flag: '🇧🇴', mask: '99999999' },
    { code: 'CL', dial: '+56', flag: '🇨🇱', mask: '9 9999 9999' },
    { code: 'CO', dial: '+57', flag: '🇨🇴', mask: '999 999 9999' },
    { code: 'MX', dial: '+52', flag: '🇲🇽', mask: '999 999 9999' },
    { code: 'GB', dial: '+44', flag: '🇬🇧', mask: '9999 999999' },
    { code: 'ES', dial: '+34', flag: '🇪🇸', mask: '999 999 999' },
    { code: 'FR', dial: '+33', flag: '🇫🇷', mask: '9 99 99 99 99' },
    {
        code: 'DE',
        dial: '+49',
        flag: '🇩🇪',
        mask: ['999 99999999', '9999 9999999'],
    },
    { code: 'IT', dial: '+39', flag: '🇮🇹', mask: '999 999 9999' },
];

const props = withDefaults(
    defineProps<{
        modelValue?: string | null;
        name?: string;
        id?: string;
        required?: boolean;
        disabled?: boolean;
        defaultCountry?: string;
        placeholder?: string;
    }>(),
    {
        modelValue: '',
        name: undefined,
        id: undefined,
        required: false,
        disabled: false,
        defaultCountry: 'BR',
        placeholder: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
const { t } = useI18n();

const initialCountry = (): CountryPhone => {
    const value = String(props.modelValue ?? '').trim();

    return (
        countries
            .filter((country) => value.startsWith(country.dial))
            .sort(
                (first, second) => second.dial.length - first.dial.length,
            )[0] ??
        countries.find((country) => country.code === props.defaultCountry) ??
        countries[0]
    );
};

const selectedCode = ref(initialCountry().code);
const localValue = ref(
    String(props.modelValue ?? '')
        .replace(initialCountry().dial, '')
        .trim(),
);
const selectedCountry = computed(
    () =>
        countries.find((country) => country.code === selectedCode.value) ??
        countries[0],
);
const completeValue = computed(() => {
    const nationalNumber = localValue.value.trim();

    return nationalNumber
        ? `${selectedCountry.value.dial} ${nationalNumber}`
        : '';
});

watch(completeValue, (value) => emit('update:modelValue', value));

watch(
    () => props.modelValue,
    (value) => {
        if (String(value ?? '') === completeValue.value) {
            return;
        }

        const country = initialCountry();
        selectedCode.value = country.code;
        localValue.value = String(value ?? '')
            .replace(country.dial, '')
            .trim();
    },
);

const changeCountry = (): void => {
    localValue.value = '';
};
</script>

<template>
    <div class="phone-input flex h-9 w-full min-w-0 max-w-full overflow-hidden rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30">
        <select
            v-model="selectedCode"
            :disabled="disabled"
            :aria-label="t('phone.country')"
            class="phone-input__country h-full w-24 shrink-0 appearance-none rounded-l-md border-0 border-r border-input bg-transparent px-2 text-sm text-foreground outline-none focus:ring-0 disabled:opacity-50 sm:w-28"
            @change="changeCountry"
        >
            <option
                v-for="country in countries"
                :key="country.code"
                :value="country.code"
            >
                {{ country.flag }} {{ country.dial }}
            </option>
        </select>
        <input
            :id="id"
            v-model="localValue"
            v-mask-input="selectedCountry.mask"
            type="text"
            inputmode="numeric"
            autocomplete="tel-national"
            :required="required"
            :disabled="disabled"
            :placeholder="placeholder ?? t('phone.placeholder')"
            class="phone-input__number h-full w-0 min-w-0 flex-1 rounded-r-md border-0 bg-transparent px-3 py-1 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:ring-0 disabled:opacity-50"
        />
        <input v-if="name" type="hidden" :name="name" :value="completeValue" />
    </div>
</template>

<style scoped>
.phone-input__country option {
    background-color: var(--popover);
    color: var(--popover-foreground);
}

.phone-input__number:-webkit-autofill,
.phone-input__number:-webkit-autofill:hover,
.phone-input__number:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--foreground);
    box-shadow: 0 0 0 1000px transparent inset;
    transition: background-color 9999s ease-out;
}
</style>
