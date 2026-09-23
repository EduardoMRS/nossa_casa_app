<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NavUser from '@/components/NavUser.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-14 min-w-0 shrink-0 items-center gap-2 border-b border-indigo-950 bg-[#342f87] px-3 text-white shadow-sm transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:h-16 md:px-6"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
            <SidebarTrigger
                class="-ml-1 text-white hover:bg-indigo-800 hover:text-white"
            />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <div class="min-w-0 truncate">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </div>
            </template>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <NavUser />
        </div>
    </header>
</template>
