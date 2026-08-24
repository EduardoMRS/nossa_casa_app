<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, useSlots } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { modalSizeClass } from '@/lib/modal';
import type { ModalSize } from '@/lib/modal';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        size?: ModalSize;
        scrollable?: boolean;
        showCloseButton?: boolean;
        contentClass?: HTMLAttributes['class'];
        headerClass?: HTMLAttributes['class'];
    }>(),
    {
        description: undefined,
        size: 'md',
        scrollable: false,
        showCloseButton: true,
        contentClass: undefined,
        headerClass: undefined,
    },
);

const open = defineModel<boolean>('open', { required: true });
const slots = useSlots();
const contentClasses = computed(() =>
    cn(modalSizeClass(props.size), props.contentClass),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogScrollContent
            v-if="scrollable"
            :class="contentClasses"
            :show-close-button="showCloseButton"
        >
            <DialogHeader :class="headerClass">
                <DialogTitle
                    ><slot name="title">{{ title }}</slot></DialogTitle
                >
                <DialogDescription v-if="description || slots.description">
                    <slot name="description">{{ description }}</slot>
                </DialogDescription>
            </DialogHeader>

            <slot />

            <DialogFooter v-if="slots.footer">
                <slot name="footer" />
            </DialogFooter>
        </DialogScrollContent>

        <DialogContent
            v-else
            :class="contentClasses"
            :show-close-button="showCloseButton"
        >
            <DialogHeader :class="headerClass">
                <DialogTitle
                    ><slot name="title">{{ title }}</slot></DialogTitle
                >
                <DialogDescription v-if="description || slots.description">
                    <slot name="description">{{ description }}</slot>
                </DialogDescription>
            </DialogHeader>

            <slot />

            <DialogFooter v-if="slots.footer">
                <slot name="footer" />
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
