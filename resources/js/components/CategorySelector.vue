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
            <label class="text-xs font-bold text-foreground">{{ label }}</label>
            <span v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</span>
        </div>

        <div
            v-if="categories.length"
            class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
        >
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-sm transition"
                :class="
                    selectedIds.has(category.id)
                        ? 'border-primary bg-primary/10 text-primary font-semibold shadow-xs'
                        : 'border-input bg-card text-card-foreground hover:bg-muted/60'
                "
                @click="toggleCategory(category.id)"
            >
                <span
                    class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors"
                    :class="
                        selectedIds.has(category.id)
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-input bg-background'
                    "
                >
                    <span
                        v-if="selectedIds.has(category.id)"
                        class="h-1.5 w-1.5 rounded-full bg-primary-foreground"
                    />
                </span>
                <span class="truncate">{{ category.name }}</span>
            </button>
        </div>

        <div
            v-else
            class="rounded-xl border border-dashed border-input bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
        >
            {{ t('admin.categories.none_available') }}
        </div>
    </div>
</template>
