<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { useI18n } from '@/lib/i18n';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'settings.appearance.breadcrumb',
                href: edit(),
            },
        ],
    },
});
const { t } = useI18n();
</script>

<template>
    <Head :title="t('settings.appearance.meta_title')" />

    <h1 class="sr-only">{{ t('settings.appearance.meta_title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('settings.appearance.title')"
            :description="t('settings.appearance.description')"
        />
        <AppearanceTabs />
    </div>
</template>
