<script setup lang="ts">
import { PanelLeftClose, PanelLeftOpen } from "@lucide/vue"
import type { HTMLAttributes } from "vue"
import { Button } from '@/components/ui/button'
import { useI18n } from '@/lib/i18n'
import { cn } from "@/lib/utils"
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, state, toggleSidebar } = useSidebar()
const { t } = useI18n()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('h-7 w-7', props.class)"
    @click="toggleSidebar"
  >
    <PanelLeftOpen v-if="isMobile || state === 'collapsed'" />
    <PanelLeftClose v-else />
    <span class="sr-only">{{ t('a11y.toggle_sidebar') }}</span>
  </Button>
</template>
