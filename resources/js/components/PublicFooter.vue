<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import PwaActions from '@/components/PwaActions.vue';
import { useI18n } from '@/lib/i18n';
import { privacy } from '@/routes/legal';

withDefaults(defineProps<{ showLocale?: boolean }>(), {
    showLocale: true,
});

const page = usePage();
const { t } = useI18n();

const portal = computed(
    () =>
        page.props.portal as
            | {
                  footer?: {
                      message?: string;
                      links?: Array<{ label: string; url: string }>;
                  };
              }
            | undefined,
);
const footerMessage = computed(() => portal.value?.footer?.message);
const footerLinks = computed(() => portal.value?.footer?.links ?? []);
</script>

<template>
    <footer
        class="mt-auto border-t border-slate-200 bg-white py-5 text-center font-mono text-[9px] font-semibold tracking-[0.14em] text-slate-400 uppercase sm:py-7"
    >
        <div
            class="mx-auto flex max-w-6xl flex-col items-center justify-center gap-3 px-4 sm:gap-4 sm:px-6"
        >
            <p
                v-if="footerMessage"
                class="max-w-2xl text-xs font-normal normal-case text-slate-600 dark:text-slate-400"
            >
                {{ footerMessage }}
            </p>
            <div
                v-if="footerLinks.length"
                class="flex flex-wrap items-center justify-center gap-4 text-xs font-semibold normal-case"
            >
                <a
                    v-for="link in footerLinks"
                    :key="link.url"
                    :href="link.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="transition hover:text-slate-800 dark:hover:text-slate-200"
                >
                    {{ link.label }}
                </a>
            </div>
            <LocaleSwitcher v-if="showLocale" />
            <PwaActions />
            <a
                :href="privacy().url"
                class="transition hover:text-slate-700"
            >
                {{ t('footer.privacy_terms') }}
            </a>
            <p>{{ t('footer.copyright') }}</p>
        </div>
    </footer>
</template>
