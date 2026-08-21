<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Moon, Palette, Sun, X } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from '@/lib/i18n';
import { index as libraryIndex } from '@/routes/library';

type ReaderTheme = 'light' | 'paper' | 'sepia' | 'dark';

const themeStorageKey = 'ncapp_reader_theme';
const themes: Array<{ value: ReaderTheme; color: string }> = [
    { value: 'light', color: '#ffffff' },
    { value: 'paper', color: '#f6f1e7' },
    { value: 'sepia', color: '#e9dcc3' },
    { value: 'dark', color: '#172033' },
];
const theme = ref<ReaderTheme>('paper');
const { t } = useI18n();

const readerStyle = computed<Record<string, string>>(() => {
    const palette: Record<ReaderTheme, Record<string, string>> = {
        light: {
            '--reader-bg': '#ffffff',
            '--reader-surface': '#f8fafc',
            '--reader-text': '#172033',
            '--reader-muted': '#64748b',
            '--reader-border': '#e2e8f0',
        },
        paper: {
            '--reader-bg': '#f6f1e7',
            '--reader-surface': '#fffdf8',
            '--reader-text': '#29241d',
            '--reader-muted': '#766b5b',
            '--reader-border': '#ded4c3',
        },
        sepia: {
            '--reader-bg': '#e9dcc3',
            '--reader-surface': '#f4ead7',
            '--reader-text': '#3e3022',
            '--reader-muted': '#7c6851',
            '--reader-border': '#cdbb9b',
        },
        dark: {
            '--reader-bg': '#172033',
            '--reader-surface': '#202c42',
            '--reader-text': '#edf2f7',
            '--reader-muted': '#a9b6c9',
            '--reader-border': '#35445d',
        },
    };

    return palette[theme.value];
});

const closeReader = (): void => {
    if (window.history.length > 1) {
        window.history.back();

        return;
    }

    router.visit(libraryIndex());
};

onMounted(() => {
    const savedTheme = window.localStorage.getItem(themeStorageKey);

    if (themes.some((item) => item.value === savedTheme)) {
        theme.value = savedTheme as ReaderTheme;
    }
});

watch(theme, (value) => window.localStorage.setItem(themeStorageKey, value));
</script>

<template>
    <div
        class="min-h-screen bg-[var(--reader-bg)] text-[var(--reader-text)] transition-colors duration-300"
        :style="readerStyle"
    >
        <header
            class="sticky top-0 z-40 border-b border-[var(--reader-border)] bg-[color:var(--reader-bg)]/95 backdrop-blur"
        >
            <div
                class="mx-auto flex h-14 w-full max-w-5xl items-center justify-between gap-3 px-4 sm:px-6"
            >
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <Palette class="size-4 text-[var(--reader-muted)]" />
                    <span class="hidden sm:inline">
                        {{ t('reader.appearance') }}
                    </span>
                    <div
                        class="flex items-center gap-1 rounded-full border border-[var(--reader-border)] bg-[var(--reader-surface)] p-1"
                    >
                        <button
                            v-for="item in themes"
                            :key="item.value"
                            type="button"
                            class="relative grid size-7 place-items-center rounded-full border transition hover:scale-105"
                            :class="
                                theme === item.value
                                    ? 'border-[var(--reader-text)]'
                                    : 'border-[var(--reader-border)]'
                            "
                            :style="{ backgroundColor: item.color }"
                            :aria-label="t(`reader.themes.${item.value}`)"
                            :title="t(`reader.themes.${item.value}`)"
                            @click="theme = item.value"
                        >
                            <Moon
                                v-if="item.value === 'dark'"
                                class="size-3.5 text-white"
                            />
                            <Sun
                                v-else-if="item.value === 'light'"
                                class="size-3.5 text-slate-700"
                            />
                            <Check
                                v-else-if="theme === item.value"
                                class="size-3.5 text-stone-800"
                            />
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-[var(--reader-border)] bg-[var(--reader-surface)] px-3 py-2 text-sm font-bold transition hover:brightness-95"
                    :aria-label="t('reader.close')"
                    :title="t('reader.close')"
                    @click="closeReader"
                >
                    <X class="size-4" />
                    <span class="hidden sm:inline">{{
                        t('reader.close')
                    }}</span>
                </button>
            </div>
        </header>

        <slot />
    </div>
</template>
