<script setup lang="ts">
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';

withDefaults(defineProps<{ showLocale?: boolean }>(), {
    showLocale: false,
});
</script>

<template>
    <footer
        class="mt-auto border-t border-slate-200 bg-white py-7 text-center font-mono text-[9px] font-semibold tracking-[0.14em] text-slate-400 uppercase"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col items-center justify-center gap-4 px-4 sm:px-6"
        >
            <LocaleSwitcher v-if="showLocale" />
            <p>© 2026 Projeto Nossa Casa · Open Source (MIT)</p>
        </div>
    </footer>
</template>
