<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();

const page = usePage();
const logoUrl = computed(
    () =>
        (page.props.branding as { logo_url?: string } | undefined)?.logo_url ||
        '/branding/logo',
);
</script>

<template>
    <img :src="logoUrl" alt="" :class="className" v-bind="$attrs" />
</template>
