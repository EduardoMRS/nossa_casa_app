<script lang="ts" setup>
import { MoreHorizontal } from "@lucide/vue"
import type { HTMLAttributes } from "vue"
import { useI18n } from "@/lib/i18n"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { t } = useI18n()
</script>

<template>
  <span
    data-slot="breadcrumb-ellipsis"
    role="presentation"
    aria-hidden="true"
    :class="cn('flex size-9 items-center justify-center', props.class)"
  >
    <slot>
      <MoreHorizontal class="size-4" />
    </slot>
    <span class="sr-only">{{ t('a11y.more') }}</span>
  </span>
</template>
