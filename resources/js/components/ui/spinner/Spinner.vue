<script setup lang="ts">
import { Loader2Icon } from "@lucide/vue"
import type { HTMLAttributes } from "vue"
import { useI18n } from "@/lib/i18n"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { t } = useI18n()
</script>

<template>
  <Loader2Icon
    role="status"
    :aria-label="t('a11y.loading')"
    :class="cn('size-4 animate-spin', props.class)"
  />
</template>
