<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ChurchMembershipPrompt from '@/components/ChurchMembershipPrompt.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const themeStyle = computed(() => {
    const branding = (page.props.branding ?? {}) as Record<string, string>;
    const primary = branding.primary_color || '#342f87';

    return {
        '--primary': primary,
        '--primary-foreground': '#fafafa',
        '--ring': primary,
        '--sidebar-primary': primary,
        '--sidebar-primary-foreground': '#fafafa',
    };
});
</script>

<template>
    <AppShell variant="sidebar" :style="themeStyle">
        <AppSidebar :style="themeStyle" />
        <AppContent
            variant="sidebar"
            class="dashboard-theme overflow-x-hidden bg-background text-foreground"
            :style="themeStyle"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <ChurchMembershipPrompt />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
