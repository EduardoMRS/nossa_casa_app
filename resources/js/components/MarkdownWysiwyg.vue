<script setup lang="ts">
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from '@/lib/i18n';
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
const { t } = useI18n();
const pickerOpen = ref(false);
const loadingEmbeds = ref(false);
const activeType = ref<'form' | 'media' | 'post' | 'event' | 'library'>(
    'media',
);
const search = ref('');
const category = ref('');
type EmbedItem = {
    id: string;
    title: string;
    description?: string | null;
    url?: string;
    mimetype?: string;
    categories?: Array<{ id: string; name: string }>;
};
type EditorInstance = {
    getMarkdown: () => string;
    setMarkdown: (value: string) => void;
    insertText: (value: string) => void;
    destroy: () => void;
};
const embeds = ref<Record<string, EmbedItem[]>>({
    form: [],
    media: [],
    post: [],
    event: [],
    library: [],
});
let editor: EditorInstance | null = null;

const categories = computed(() => {
    const names =
        embeds.value[activeType.value]
            ?.flatMap((item) => item.categories ?? [])
            .map((item) => item.name) ?? [];

    return [...new Set(names)].sort();
});
const filteredItems = computed(() =>
    (embeds.value[activeType.value] ?? []).filter((item) => {
        const matchesSearch = `${item.title} ${item.description ?? ''}`
            .toLocaleLowerCase()
            .includes(search.value.toLocaleLowerCase());
        const matchesCategory =
            !category.value ||
            item.categories?.some(
                (itemCategory) => itemCategory.name === category.value,
            );

        return matchesSearch && matchesCategory;
    }),
);

const syncFromEditor = () => {
    if (!editor) {
        return;
    }

    emit('update:modelValue', editor.getMarkdown());
};

const openPicker = async (): Promise<void> => {
    pickerOpen.value = true;

    if (Object.values(embeds.value).some((items) => items.length)) {
        return;
    }

    loadingEmbeds.value = true;

    try {
        const response = await axios.get('/api/content-embeds');
        embeds.value = response.data;
    } finally {
        loadingEmbeds.value = false;
    }
};

const insertEmbed = (item: EmbedItem): void => {
    editor?.insertText(`\n[[${activeType.value}:${item.id}]]\n`);
    pickerOpen.value = false;
};

onMounted(async () => {
    if (!root.value) {
        return;
    }

    const { default: Editor } = await import('@toast-ui/editor');
    const embedButton = document.createElement('button');
    embedButton.type = 'button';
    embedButton.className =
        'toastui-editor-toolbar-icons content-embed-toolbar';
    embedButton.textContent = '⊞';
    embedButton.setAttribute('aria-label', t('editor.embeds.open'));
    embedButton.addEventListener('click', openPicker);

    editor = new Editor({
        el: root.value,
        initialValue: props.modelValue,
        initialEditType: 'wysiwyg',
        previewStyle: 'vertical',
        usageStatistics: false,
        height: props.height,
        toolbarItems: [
            ['heading', 'bold', 'italic', 'strike'],
            ['hr', 'quote'],
            ['ul', 'ol', 'task', 'indent', 'outdent'],
            ['table', 'image', 'link'],
            ['code', 'codeblock'],
            [
                {
                    name: 'contentEmbed',
                    tooltip: t('editor.embeds.open'),
                    el: embedButton,
                },
            ],
        ],
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
        <Teleport to="body">
            <div
                v-if="pickerOpen"
                class="fixed inset-0 z-50 grid place-items-center bg-white p-4"
                @click.self="pickerOpen = false"
            >
                <section
                    class="max-h-[85vh] w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl"
                >
                    <header
                        class="flex items-center justify-between border-b p-5"
                    >
                        <div>
                            <h2 class="text-xl font-black">
                                {{ t('editor.embeds.title') }}
                            </h2>
                            <p class="text-sm text-slate-500">
                                {{ t('editor.embeds.description') }}
                            </p>
                        </div>
                                                
                        <button
                            class="rounded-lg p-2 text-slate-400 hover:bg-slate-100"
                            @click="pickerOpen = false"
                        >
                            <X class="size-5" />
                        </button>
                    </header>
                    <div class="border-b px-5 pt-4">
                        <nav class="flex gap-2 overflow-x-auto">
                            <button
                                v-for="type in [
                                    'form',
                                    'media',
                                    'post',
                                    'event',
                                    'library',
                                ] as const"
                                :key="type"
                                type="button"
                                class="rounded-t-lg px-4 py-2 text-sm font-bold"
                                :class="
                                    activeType === type
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-slate-100 text-slate-700'
                                "
                                @click="
                                    activeType = type;
                                    category = '';
                                "
                            >
                                {{ t(`editor.embeds.types.${type}`) }}
                            </button>
                        </nav>
                    </div>
                    <div class="grid gap-3 border-b p-5 sm:grid-cols-2">
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="t('editor.embeds.search')"
                            class="rounded-lg border-slate-300"
                        />
                        <select
                            v-if="categories.length"
                            v-model="category"
                            class="rounded-lg border-slate-300"
                        >
                            <option value="">
                                {{ t('editor.embeds.all_categories') }}
                            </option>
                            <option
                                v-for="name in categories"
                                :key="name"
                                :value="name"
                            >
                                {{ name }}
                            </option>
                        </select>
                    </div>
                    <div
                        class="grid max-h-[50vh] gap-3 overflow-y-auto p-5 sm:grid-cols-2"
                    >
                        <p
                            v-if="loadingEmbeds"
                            class="col-span-full text-sm text-slate-500"
                        >
                            {{ t('a11y.loading') }}
                        </p>
                        <button
                            v-for="item in filteredItems"
                            :key="item.id"
                            type="button"
                            class="flex gap-3 rounded-xl border p-3 text-left transition hover:border-teal-600 hover:bg-teal-50"
                            @click="insertEmbed(item)"
                        >
                            <img
                                v-if="
                                    item.url &&
                                    item.mimetype?.startsWith('image/')
                                "
                                :src="item.url"
                                :alt="item.title"
                                class="h-16 w-20 rounded object-cover"
                            />
                            <span
                                ><strong class="block text-sm">{{
                                    item.title
                                }}</strong
                                ><span
                                    class="mt-1 line-clamp-2 text-xs text-slate-500"
                                    >{{ item.description }}</span
                                ></span
                            >
                        </button>
                        <p
                            v-if="!loadingEmbeds && !filteredItems.length"
                            class="col-span-full rounded-xl bg-slate-50 p-5 text-sm text-slate-500"
                        >
                            {{ t('editor.embeds.empty') }}
                        </p>
                    </div>
                </section>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
:deep(.content-embed-toolbar) {
    background-image: none;
    font-size: 20px;
    font-weight: 800;
    line-height: 30px;
}
</style>
