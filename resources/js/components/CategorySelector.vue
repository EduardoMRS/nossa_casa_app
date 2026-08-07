<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/lib/i18n';

type CategoryOption = {
    id: string;
    name: string;
    slug: string;
    type: string;
};

const props = defineProps<{
    modelValue: string[];
    categories: CategoryOption[];
    label: string;
    hint?: string;
}>();
const { t } = useI18n();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const selectedIds = computed(() => new Set(props.modelValue));

const toggleCategory = (categoryId: string) => {
    const nextValue = selectedIds.value.has(categoryId)
        ? props.modelValue.filter((id) => id !== categoryId)
        : [...props.modelValue, categoryId];

    emit('update:modelValue', nextValue);
};
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between gap-3">
            <label class="text-sm font-bold text-[#22374d]">{{ label }}</label>
            <span v-if="hint" class="text-xs text-[#678096]">{{ hint }}</span>
        </div>

        <div
            v-if="categories.length"
            class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
        >
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="flex items-center gap-2 rounded-2xl border px-3 py-3 text-left text-sm transition"
                :class="
                    selectedIds.has(category.id)
                        ? 'border-[#2b6e7d] bg-[#eaf6f8] text-[#173c47]'
                        : 'border-[#d4e0ea] bg-white text-[#334155]'
                "
                @click="toggleCategory(category.id)"
            >
                <span
                    class="inline-flex h-4 w-4 items-center justify-center rounded border"
                    :class="
                        selectedIds.has(category.id)
                            ? 'border-[#2b6e7d] bg-[#2b6e7d]'
                            : 'border-slate-300 bg-white'
                    "
                >
                    <span
                        v-if="selectedIds.has(category.id)"
                        class="h-1.5 w-1.5 rounded-full bg-white"
                    />
                </span>
                <span class="font-semibold">{{ category.name }}</span>
            </button>
        </div>

        <div
            v-else
            class="rounded-2xl border border-dashed border-[#d4e0ea] bg-[#f8fbfd] px-4 py-3 text-sm text-[#678096]"
        >
            {{ t('admin.categories.none_available') }}
        </div>
    </div>
</template>
