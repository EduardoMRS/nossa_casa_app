<script setup lang="ts">
import { vMaskInput } from 'use-mask-input/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from '@/lib/i18n';

type DocumentType = 'cpf' | 'ssn' | 'nif' | 'nino' | 'cuit' | 'dni' | 'passport';
type DocumentValue = { type: DocumentType; number: string };
type DocumentOption = {
    type: DocumentType;
    flag: string;
    mask: string;
};

const documentOptions: DocumentOption[] = [
    { type: 'cpf', flag: '🇧🇷', mask: '999.999.999-99' },
    { type: 'ssn', flag: '🇺🇸', mask: '999-99-9999' },
    { type: 'nif', flag: '🇵🇹', mask: '999999999' },
    { type: 'nino', flag: '🇬🇧', mask: 'aa 99 99 99 a' },
    { type: 'cuit', flag: '🇦🇷', mask: '99-99999999-9' },
    { type: 'dni', flag: '🇪🇸', mask: '99.999.999-a' },
    { type: 'passport', flag: '🌐', mask: '********************' },
];

const props = withDefaults(
    defineProps<{
        modelValue?: unknown;
        required?: boolean;
        disabled?: boolean;
        placeholder?: string;
    }>(),
    {
        modelValue: undefined,
        required: false,
        disabled: false,
        placeholder: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: DocumentValue];
}>();
const { t } = useI18n();

const normalizeValue = (value: unknown): DocumentValue => {
    if (value && typeof value === 'object') {
        const record = value as Record<string, unknown>;
        const type = documentOptions.find(
            (option) => option.type === record.type,
        )?.type;

        if (type) {
            return { type, number: String(record.number ?? '') };
        }
    }

    return { type: 'cpf', number: typeof value === 'string' ? value : '' };
};

const initialValue = normalizeValue(props.modelValue);
const selectedType = ref<DocumentType>(initialValue.type);
const number = ref(initialValue.number);
const selectedDocument = computed(
    () =>
        documentOptions.find(
            (option) => option.type === selectedType.value,
        ) ?? documentOptions[0],
);

watch(
    () => props.modelValue,
    (value) => {
        const normalized = normalizeValue(value);
        selectedType.value = normalized.type;
        number.value = normalized.number;
    },
);

watch(number, (value) => {
    if (['nino', 'dni', 'passport'].includes(selectedType.value)) {
        number.value = value.toUpperCase();
    }
});

watch([selectedType, number], () => {
    emit('update:modelValue', {
        type: selectedType.value,
        number: number.value,
    });
});

const changeType = (): void => {
    number.value = '';
};
</script>

<template>
    <div
        class="document-input flex h-10 w-full min-w-0 max-w-full overflow-hidden rounded-md border border-input bg-background shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
    >
        <select
            v-model="selectedType"
            :disabled="disabled"
            :aria-label="t('document_input.type')"
            class="document-input__type h-full w-32 shrink-0 rounded-l-md border-0 bg-transparent px-2 py-1 text-sm text-foreground outline-none focus:ring-0 disabled:cursor-not-allowed disabled:opacity-50 sm:w-36"
            @change="changeType"
        >
            <option
                v-for="option in documentOptions"
                :key="option.type"
                :value="option.type"
            >
                {{ option.flag }} {{ t(`document_input.types.${option.type}`) }}
            </option>
        </select>
        <input
            v-model="number"
            v-mask-input="selectedDocument.mask"
            type="text"
            autocomplete="off"
            :required="required"
            :disabled="disabled"
            :placeholder="placeholder ?? t('document_input.placeholder')"
            class="document-input__number h-full w-0 min-w-0 flex-1 rounded-r-md border-0 bg-transparent px-3 py-1 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:ring-0 disabled:cursor-not-allowed disabled:opacity-50"
        />
    </div>
</template>

<style scoped>
.document-input__type option {
    background-color: var(--popover);
    color: var(--popover-foreground);
}

.document-input__number:-webkit-autofill,
.document-input__number:-webkit-autofill:hover,
.document-input__number:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--foreground);
    box-shadow: 0 0 0 1000px transparent inset;
    transition: background-color 9999s ease-out;
}
</style>