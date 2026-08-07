<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import { useI18n } from '@/lib/i18n';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
const { t } = useI18n();

const localized = (value: string): string =>
    value.startsWith('auth.') ? t(value) : value;
</script>

<template>
    <AuthLayout :title="localized(title)" :description="localized(description)">
        <slot />
    </AuthLayout>
</template>
