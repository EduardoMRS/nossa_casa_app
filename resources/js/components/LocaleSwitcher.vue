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
        class="inline-flex items-center gap-2 rounded-full border border-[#c5d3df] bg-white px-3 py-1.5 text-xs font-semibold text-[#21364b]"
    >
        <span>{{ t('locale.switcher') }}</span>
        <select
            v-model="selectedLocale"
            class="rounded bg-transparent pr-4 text-xs font-semibold text-[#21364b] outline-none"
        >
            <option value="pt">{{ t('locale.pt_short') }}</option>
            <option value="en">{{ t('locale.en_short') }}</option>
        </select>
    </label>
</template>
