<script setup lang="ts">
import { computed } from 'vue';
import AppModal from '@/components/AppModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmationCanSubmit } from '@/lib/modal';

const props = withDefaults(
    defineProps<{
        title: string;
        message: string;
        confirmLabel: string;
        cancelLabel: string;
        intent?: 'default' | 'danger';
        processing?: boolean;
        inputLabel?: string;
        inputPlaceholder?: string;
        inputRequired?: boolean;
    }>(),
    {
        intent: 'default',
        processing: false,
        inputLabel: undefined,
        inputPlaceholder: undefined,
        inputRequired: false,
    },
);

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();
const open = defineModel<boolean>('open', { required: true });
const inputValue = defineModel<string>('inputValue', { default: '' });
const canSubmit = computed(() =>
    confirmationCanSubmit(
        props.processing,
        props.inputRequired,
        inputValue.value,
    ),
);
</script>

<template>
    <AppModal
        v-model:open="open"
        :title="title"
        :description="message"
        size="sm"
        @update:open="(value) => !value && emit('cancel')"
    >
        <div v-if="inputLabel" class="grid gap-2">
            <Label for="confirmation-input">{{ inputLabel }}</Label>
            <Input
                id="confirmation-input"
                v-model="inputValue"
                :placeholder="inputPlaceholder"
                :required="inputRequired"
                autocomplete="off"
                autofocus
                @keydown.enter.prevent="canSubmit && emit('confirm')"
            />
        </div>

        <template #footer>
            <Button
                type="button"
                variant="secondary"
                :disabled="processing"
                @click="emit('cancel')"
            >
                {{ cancelLabel }}
            </Button>
            <Button
                type="button"
                :variant="intent === 'danger' ? 'destructive' : 'default'"
                :disabled="!canSubmit"
                @click="emit('confirm')"
            >
                {{ confirmLabel }}
            </Button>
        </template>
    </AppModal>
</template>
