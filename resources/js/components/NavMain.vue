<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ label ?? 'Platform' }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem
                v-for="item in items"
                :key="item.title"
                class="group/menu-item"
            >
                <Collapsible
                    v-if="item.children?.length"
                    :default-open="isCurrentUrl(item.href)"
                    class="group/collapsible"
                >
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                        class="pr-8 data-[active=true]:bg-indigo-600 data-[active=true]:font-bold data-[active=true]:text-white"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuAction
                            :aria-label="item.title"
                            class="data-[state=open]:rotate-90"
                        >
                            <ChevronRight />
                        </SidebarMenuAction>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem
                                v-for="child in item.children"
                                :key="child.title"
                            >
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isCurrentUrl(child.href)"
                                >
                                    <Link :href="child.href">
                                        <span>{{ child.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </Collapsible>

                <SidebarMenuButton
                    v-else
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="data-[active=true]:bg-indigo-600 data-[active=true]:font-bold data-[active=true]:text-white"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
