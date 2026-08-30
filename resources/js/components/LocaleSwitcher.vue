<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from '@/lib/i18n';
import type { SupportedLocale } from '@/lib/i18n';

const { locale, setLocale, t } = useI18n();

const selectedLocale = computed({
    get: () => locale.value,
    set: (value: SupportedLocale) => {
        setLocale(value);
        router.reload();
    },
});
</script>

<template>
    <label
        class="inline-flex items-center gap-1 rounded-lg border border-[#c5d3df] bg-white px-2 py-1 text-xs font-semibold text-[#21364b] sm:gap-2 sm:rounded-full sm:px-3 sm:py-1.5"
    >
        <span class="sr-only sm:not-sr-only">{{ t('locale.switcher') }}</span>
        <select
            v-model="selectedLocale"
            class="min-w-0 rounded bg-transparent py-0 pr-4 pl-0 text-base font-semibold text-[#21364b] outline-none sm:text-xs"
        >
            <option value="pt">{{ t('locale.pt_short') }}</option>
            <option value="en">{{ t('locale.en_short') }}</option>
        </select>
    </label>
</template>
