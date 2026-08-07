<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Save, Trash2 } from '@lucide/vue';
import axios from 'axios';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from '@/lib/i18n';

type HighlightItem = {
    id: string;
    type: 'post' | 'event' | 'media';
    order: number;
    title: string;
};
type Candidate = { id: string; title?: string; file_path?: string };
const props = defineProps<{
    highlights: HighlightItem[];
    churchId: string;
    candidates: Record<'post' | 'event' | 'media', Candidate[]>;
}>();
const { t } = useI18n();
const items = ref(props.highlights.map((item) => ({ ...item })));
const selectedType = ref<'post' | 'event' | 'media'>('post');
const selectedId = ref('');
const saving = ref(false);
const available = computed(() =>
    props.candidates[selectedType.value].filter(
        (candidate) =>
            !items.value.some(
                (item) =>
                    item.type === selectedType.value &&
                    item.id === candidate.id,
            ),
    ),
);
const label = (candidate: Candidate): string =>
    candidate.title ?? candidate.file_path ?? candidate.id;
function add(): void {
    const candidate = props.candidates[selectedType.value].find(
        (item) => item.id === selectedId.value,
    );

    if (!candidate) {
        return;
    }

    items.value.push({
        id: candidate.id,
        type: selectedType.value,
        title: label(candidate),
        order: items.value.length,
    });
    selectedId.value = '';
}
function move(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= items.value.length) {
        return;
    }

    [items.value[index], items.value[target]] = [
        items.value[target],
        items.value[index],
    ];
}
async function save(): Promise<void> {
    saving.value = true;

    try {
        await axios.put(`/api/church/${props.churchId}/highlights`, {
            highlights: items.value.map((item, order) => ({
                id: item.id,
                type: item.type,
                order,
            })),
        });
        toast.success(t('admin.highlights.saved'));
    } catch {
        toast.error(t('admin.highlights.save_error'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head :title="t('admin.highlights.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white shadow-sm"
        >
            <p
                class="text-xs font-bold tracking-[0.2em] text-fuchsia-200 uppercase"
            >
                {{ t('admin.highlights.kicker') }}
            </p>
            <h1 class="mt-2 text-3xl font-black">
                {{ t('admin.highlights.title') }}
            </h1>
            <p class="mt-2 text-sm text-fuchsia-100">
                {{ t('admin.highlights.description') }}
            </p>
        </header>
        <section class="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
            <aside
                class="h-fit space-y-4 rounded-2xl border bg-white p-5 shadow-sm"
            >
                <h2 class="font-black">{{ t('admin.highlights.add') }}</h2>
                <select
                    v-model="selectedType"
                    class="w-full rounded-xl border-slate-300"
                >
                    <option value="post">{{ t('posts.index.title') }}</option>
                    <option value="event">{{ t('nav.events') }}</option>
                    <option value="media">{{ t('nav.gallery') }}</option>
                </select>
                <select
                    v-model="selectedId"
                    class="w-full rounded-xl border-slate-300"
                >
                    <option value="">{{ t('admin.highlights.choose') }}</option>
                    <option
                        v-for="candidate in available"
                        :key="candidate.id"
                        :value="candidate.id"
                    >
                        {{ label(candidate) }}
                    </option>
                </select>
                <button
                    :disabled="!selectedId"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-violet-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-40"
                    @click="add"
                >
                    <Plus class="size-4" />{{ t('actions.create') }}
                </button>
            </aside>
            <section
                class="overflow-hidden rounded-2xl border bg-white shadow-sm"
            >
                <header
                    class="flex items-center justify-between border-b px-5 py-4"
                >
                    <h2 class="font-black">
                        {{ t('admin.highlights.order') }}
                    </h2>
                    <button
                        :disabled="saving"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white"
                        @click="save"
                    >
                        <Save class="size-4" />{{ t('actions.save') }}
                    </button>
                </header>
                <div class="divide-y">
                    <article
                        v-for="(item, index) in items"
                        :key="`${item.type}-${item.id}`"
                        class="flex items-center gap-3 p-4"
                    >
                        <span
                            class="grid size-9 place-items-center rounded-lg bg-violet-50 font-black text-violet-700"
                            >{{ index + 1 }}</span
                        >
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold">{{ item.title }}</p>
                            <p
                                class="text-xs font-semibold text-slate-400 uppercase"
                            >
                                {{ item.type }}
                            </p>
                        </div>
                        <button
                            :disabled="index === 0"
                            class="p-2 disabled:opacity-30"
                            @click="move(index, -1)"
                        >
                            <ArrowUp class="size-4" /></button
                        ><button
                            :disabled="index === items.length - 1"
                            class="p-2 disabled:opacity-30"
                            @click="move(index, 1)"
                        >
                            <ArrowDown class="size-4" /></button
                        ><button
                            class="p-2 text-rose-600"
                            @click="items.splice(index, 1)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </article>
                    <p
                        v-if="!items.length"
                        class="p-12 text-center text-sm text-slate-500"
                    >
                        {{ t('admin.highlights.empty') }}
                    </p>
                </div>
            </section>
        </section>
    </main>
</template>
