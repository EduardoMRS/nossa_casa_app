<script setup lang="ts">
import Editor from '@toast-ui/editor';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import '@toast-ui/editor/dist/toastui-editor.css';

type Props = {
    modelValue: string;
    height?: string;
};

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    height: '360px',
});

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

const root = ref<HTMLElement | null>(null);
let editor: any = null;

const syncFromEditor = () => {
    if (!editor) {
        return;
    }

    emit('update:modelValue', editor.getMarkdown());
};

onMounted(() => {
    if (!root.value) {
        return;
    }

    editor = new Editor({
        el: root.value,
        initialValue: props.modelValue,
        initialEditType: 'wysiwyg',
        previewStyle: 'vertical',
        usageStatistics: false,
        height: props.height,
        events: {
            change: syncFromEditor,
        },
    });
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor) {
            return;
        }

        const markdown = editor.getMarkdown();

        if (value !== markdown) {
            editor.setMarkdown(value || '');
        }
    },
);

onBeforeUnmount(() => {
    editor?.destroy();
    editor = null;
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-[#dfe7ef] bg-white">
        <div ref="root" />
    </div>
</template>
