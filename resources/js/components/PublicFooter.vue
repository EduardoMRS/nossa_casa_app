<script setup lang="ts">
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import PwaActions from '@/components/PwaActions.vue';
import { useI18n } from '@/lib/i18n';

withDefaults(defineProps<{ showLocale?: boolean }>(), {
    showLocale: true,
});

const { t } = useI18n();
</script>

<template>
    <footer
        class="mt-auto border-t border-slate-200 bg-white py-7 text-center font-mono text-[9px] font-semibold tracking-[0.14em] text-slate-400 uppercase"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col items-center justify-center gap-4 px-4 sm:px-6"
        >
            <LocaleSwitcher v-if="showLocale" />
            <PwaActions />
            <p>{{ t('footer.copyright') }}</p>
        </div>
    </footer>
</template>
