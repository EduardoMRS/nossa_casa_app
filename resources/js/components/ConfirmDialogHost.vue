<script setup lang="ts">
import ConfirmModal from '@/components/ConfirmModal.vue';
import {
    confirmationDialogState,
    resolveConfirmationDialog,
} from '@/composables/useConfirmDialog';
</script>

<template>
    <ConfirmModal
        v-model:open="confirmationDialogState.open"
        v-model:input-value="confirmationDialogState.inputValue"
        :title="confirmationDialogState.title"
        :message="confirmationDialogState.message"
        :confirm-label="confirmationDialogState.confirmLabel"
        :cancel-label="confirmationDialogState.cancelLabel"
        :intent="confirmationDialogState.intent"
        :input-label="confirmationDialogState.inputLabel || undefined"
        :input-placeholder="
            confirmationDialogState.inputPlaceholder || undefined
        "
        :input-required="confirmationDialogState.inputRequired"
        @confirm="resolveConfirmationDialog(true)"
        @cancel="resolveConfirmationDialog(false)"
    />
</template>
