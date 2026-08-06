<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

type Linkable = { id: string; title: string };
type Category = { id: string; name: string };
type FormElement = {
    id: string;
    type: string;
    name?: string;
    label?: string;
    required?: boolean;
    options?: string[];
    width?: 'full' | 'half' | 'third';
    size?: 'auto' | 'fixed';
    height?: number;
    placeholder?: string;
    help_text?: string;
};
type ManagedForm = {
    id: string;
    title: string;
    description: string | null;
    schema: { fields: FormElement[] };
    events: Linkable[];
    posts: Linkable[];
    categories?: Category[];
    responses_count: number;
};

const props = defineProps<{
    forms: ManagedForm[];
    events: Linkable[];
    posts: Linkable[];
    categories: Category[];
}>();
const selected = ref<ManagedForm | null>(null);
const editorOpen = ref(false);
const saving = ref(false);
const error = ref('');
const fields = ref<FormElement[]>([]);
const form = ref({
    title: '',
    description: '',
    category_ids: [] as string[],
    event_ids: [] as string[],
    post_ids: [] as string[],
});
const fieldTypes = [
    ['text', 'Texto curto'],
    ['email', 'E-mail'],
    ['number', 'Número'],
    ['date', 'Data'],
    ['textarea', 'Texto longo'],
    ['select', 'Lista'],
    ['radio', 'Escolha única'],
    ['checkbox', 'Caixa de seleção'],
    ['heading', 'Título'],
    ['divider', 'Separador'],
    ['line_break', 'Quebra de linha'],
] as const;
const submitLabel = computed(() =>
    selected.value ? 'Salvar alterações' : 'Criar formulário',
);
const inputTypes = new Set([
    'text',
    'email',
    'number',
    'date',
    'textarea',
    'select',
    'radio',
    'checkbox',
]);

function emptyElement(type = 'text'): FormElement {
    return {
        id: crypto.randomUUID(),
        type,
        name:
            type === 'heading' || type === 'divider' || type === 'line_break'
                ? undefined
                : `campo_${fields.value.length + 1}`,
        label:
            type === 'heading'
                ? 'Novo título'
                : type === 'divider' || type === 'line_break'
                  ? undefined
                  : 'Novo campo',
        required: false,
        options: ['select', 'radio'].includes(type) ? ['Opção 1'] : [],
        width: 'full',
        size: 'auto',
        height: 4,
        placeholder: '',
        help_text: '',
    };
}

function startNew(): void {
    editorOpen.value = true;
    selected.value = null;
    error.value = '';
    fields.value = [emptyElement()];
    form.value = {
        title: '',
        description: '',
        category_ids: [],
        event_ids: [],
        post_ids: [],
    };
}

function edit(item: ManagedForm): void {
    editorOpen.value = true;
    selected.value = item;
    error.value = '';
    fields.value =
        item.schema?.fields?.map((field) => ({
            ...emptyElement(field.type),
            ...field,
            id: field.id || crypto.randomUUID(),
        })) ?? [];
    form.value = {
        title: item.title,
        description: item.description ?? '',
        category_ids: item.categories?.map((category) => category.id) ?? [],
        event_ids: item.events.map((event) => event.id),
        post_ids: item.posts.map((post) => post.id),
    };
}

function addField(type: string): void {
    fields.value.push(emptyElement(type));
}
function removeField(index: number): void {
    fields.value.splice(index, 1);
}
function moveField(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target >= 0 && target < fields.value.length) {
        [fields.value[index], fields.value[target]] = [
            fields.value[target],
            fields.value[index],
        ];
    }
}
function needsOptions(field: FormElement): boolean {
    return ['select', 'radio'].includes(field.type);
}

async function save(): Promise<void> {
    try {
        saving.value = true;
        error.value = '';
        const payload = { ...form.value, schema: { fields: fields.value } };

        if (selected.value) {
            await axios.put(`/api/forms/${selected.value.id}`, payload);
        } else {
            await axios.post('/api/forms', payload);
        }

        window.location.reload();
    } catch (caught) {
        error.value = axios.isAxiosError(caught)
            ? Object.values(caught.response?.data?.errors ?? {})
                  .flat()
                  .join(' ') || 'Não foi possível salvar o formulário.'
            : 'Não foi possível salvar o formulário.';
    } finally {
        saving.value = false;
    }
}

async function remove(item: ManagedForm): Promise<void> {
    if (!window.confirm(`Excluir o formulário “${item.title}”?`)) {
        return;
    }

    await axios.delete(`/api/forms/${item.id}`);
    window.location.reload();
}
function closeEditor(): void {
    editorOpen.value = false;
    error.value = '';
}
</script>

<template>
    <Head title="Formulários" />
    <main
        class="grid gap-6 p-4 xl:grid-cols-[20rem_minmax(0,1fr)_19rem] xl:p-6"
    >
        <section class="space-y-4">
            <header class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-black">Formulários</h1>
                    <p class="text-sm text-slate-500">
                        Construtor visual e vínculos.
                    </p>
                </div>
                <button
                    class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white"
                    @click="startNew"
                >
                    Novo
                </button>
            </header>
            <article
                v-for="item in props.forms"
                :key="item.id"
                class="rounded-xl border bg-white p-4"
            >
                <button class="w-full text-left" @click="edit(item)">
                    <strong>{{ item.title }}</strong
                    ><span class="mt-1 block text-xs text-slate-500"
                        >{{ item.responses_count }} respostas ·
                        {{ item.events.length + item.posts.length }}
                        vínculo(s)</span
                    ></button
                ><button
                    class="mt-2 text-xs font-bold text-red-600"
                    @click="remove(item)"
                >
                    Excluir
                </button>
            </article>
        </section>
        <div
            v-if="editorOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/45 p-4 backdrop-blur-sm lg:p-8"
        >
            <div
                class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[minmax(0,1fr)_19rem]"
            >
                <form
                    class="space-y-5 rounded-2xl border bg-white p-5 shadow-sm"
                    @submit.prevent="save"
                >
                    <header>
                        <h2 class="text-xl font-black">
                            {{
                                selected
                                    ? 'Editar formulário'
                                    : 'Novo formulário'
                            }}
                        </h2>
                        <p class="text-sm text-slate-500">
                            Adicione campos e elementos de conteúdo sem editar
                            código.
                        </p>
                    </header>
                    <p
                        v-if="error"
                        class="rounded-lg bg-red-50 p-3 text-sm text-red-700"
                    >
                        {{ error }}
                    </p>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="text-sm font-semibold"
                            >Título<input
                                v-model="form.title"
                                required
                                class="mt-1 w-full rounded-lg border-slate-300" /></label
                        ><label class="text-sm font-semibold"
                            >Descrição<input
                                v-model="form.description"
                                class="mt-1 w-full rounded-lg border-slate-300"
                        /></label>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-sm font-bold">Adicionar elemento</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="[value, label] in fieldTypes"
                                :key="value"
                                type="button"
                                class="rounded-full border bg-white px-3 py-1 text-xs font-semibold"
                                @click="addField(value)"
                            >
                                {{ label }}
                            </button>
                        </div>
                    </div>
                    <section class="space-y-3">
                        <article
                            v-for="(field, index) in fields"
                            :key="field.id"
                            class="rounded-xl border p-4"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <span
                                    class="text-xs font-bold text-slate-500 uppercase"
                                    >{{ field.type }}</span
                                >
                                <div class="flex gap-2 text-xs">
                                    <button
                                        type="button"
                                        @click="moveField(index, -1)"
                                    >
                                        ↑</button
                                    ><button
                                        type="button"
                                        @click="moveField(index, 1)"
                                    >
                                        ↓</button
                                    ><button
                                        type="button"
                                        class="text-red-600"
                                        @click="removeField(index)"
                                    >
                                        Remover
                                    </button>
                                </div>
                            </div>
                            <template v-if="inputTypes.has(field.type)"
                                ><div class="grid gap-3 md:grid-cols-2">
                                    <label class="text-sm"
                                        >Rótulo<input
                                            v-model="field.label"
                                            class="mt-1 w-full rounded-lg border-slate-300" /></label
                                    ><label class="text-sm"
                                        >Identificador<input
                                            v-model="field.name"
                                            class="mt-1 w-full rounded-lg border-slate-300" /></label
                                    ><label class="text-sm"
                                        >Largura<select
                                            v-model="field.width"
                                            class="mt-1 w-full rounded-lg border-slate-300"
                                        >
                                            <option value="full">
                                                Linha inteira
                                            </option>
                                            <option value="half">
                                                Metade da linha
                                            </option>
                                            <option value="third">
                                                Um terço
                                            </option>
                                        </select></label
                                    ><label class="text-sm"
                                        >Tamanho<select
                                            v-model="field.size"
                                            class="mt-1 w-full rounded-lg border-slate-300"
                                        >
                                            <option value="auto">
                                                Dinâmico
                                            </option>
                                            <option value="fixed">Fixo</option>
                                        </select></label
                                    >
                                </div>
                                <label class="mt-3 flex gap-2 text-sm"
                                    ><input
                                        v-model="field.required"
                                        type="checkbox"
                                    />Obrigatório</label
                                ><label
                                    v-if="
                                        field.type === 'textarea' &&
                                        field.size === 'fixed'
                                    "
                                    class="mt-3 block text-sm"
                                    >Altura (linhas)<input
                                        v-model.number="field.height"
                                        type="number"
                                        min="1"
                                        max="20"
                                        class="mt-1 w-32 rounded-lg border-slate-300" /></label
                                ><label
                                    v-if="needsOptions(field)"
                                    class="mt-3 block text-sm"
                                    >Opções (uma por linha)<textarea
                                        :value="field.options?.join('\n')"
                                        rows="3"
                                        class="mt-1 w-full rounded-lg border-slate-300"
                                        @input="
                                            field.options = (
                                                $event.target as HTMLTextAreaElement
                                            ).value
                                                .split('\n')
                                                .filter(Boolean)
                                        "
                                    /></label></template
                            ><template v-else-if="field.type === 'heading'"
                                ><label class="block text-sm"
                                    >Título<input
                                        v-model="field.label"
                                        class="mt-1 w-full rounded-lg border-slate-300" /></label
                            ></template>
                            <p v-else class="text-sm text-slate-500">
                                Elemento visual sem resposta do participante.
                            </p>
                        </article>
                    </section>
                    <fieldset class="grid gap-3 md:grid-cols-3">
                        <label class="text-sm font-semibold"
                            >Eventos<select
                                v-model="form.event_ids"
                                multiple
                                class="mt-1 h-28 w-full rounded-lg border-slate-300"
                            >
                                <option
                                    v-for="event in props.events"
                                    :key="event.id"
                                    :value="event.id"
                                >
                                    {{ event.title }}
                                </option>
                            </select></label
                        ><label class="text-sm font-semibold"
                            >Postagens<select
                                v-model="form.post_ids"
                                multiple
                                class="mt-1 h-28 w-full rounded-lg border-slate-300"
                            >
                                <option
                                    v-for="post in props.posts"
                                    :key="post.id"
                                    :value="post.id"
                                >
                                    {{ post.title }}
                                </option>
                            </select></label
                        ><label class="text-sm font-semibold"
                            >Categorias<select
                                v-model="form.category_ids"
                                multiple
                                class="mt-1 h-28 w-full rounded-lg border-slate-300"
                            >
                                <option
                                    v-for="category in props.categories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select></label
                        >
                    </fieldset>
                    <button
                        :disabled="saving"
                        class="w-full rounded-lg bg-slate-900 py-2 text-sm font-bold text-white disabled:opacity-50"
                    >
                        {{ saving ? 'Salvando…' : submitLabel }}
                    </button>
                </form>
                <aside class="rounded-2xl border bg-white p-5">
                    <h2 class="font-black">Prévia</h2>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <template
                            v-for="field in fields"
                            :key="`preview-${field.id}`"
                            ><h3
                                v-if="field.type === 'heading'"
                                class="col-span-3 text-lg font-black"
                            >
                                {{ field.label }}
                            </h3>
                            <hr
                                v-else-if="field.type === 'divider'"
                                class="col-span-3 border-slate-200" />
                            <div
                                v-else-if="field.type !== 'line_break'"
                                :class="
                                    field.width === 'half'
                                        ? 'col-span-3 md:col-span-1'
                                        : field.width === 'third'
                                          ? 'col-span-1'
                                          : 'col-span-3'
                                "
                            >
                                <label class="text-sm font-semibold"
                                    >{{ field.label
                                    }}<span
                                        v-if="field.required"
                                        class="text-red-600"
                                    >
                                        *</span
                                    ></label
                                ><textarea
                                    v-if="field.type === 'textarea'"
                                    :rows="
                                        field.size === 'fixed'
                                            ? field.height
                                            : 3
                                    "
                                    disabled
                                    class="mt-1 w-full rounded-lg border-slate-300"
                                /><select
                                    v-else-if="field.type === 'select'"
                                    disabled
                                    class="mt-1 w-full rounded-lg border-slate-300"
                                >
                                    <option>Selecione</option></select
                                ><input
                                    v-else
                                    :type="
                                        field.type === 'checkbox'
                                            ? 'checkbox'
                                            : field.type
                                    "
                                    disabled
                                    class="mt-1 w-full rounded-lg border-slate-300"
                                />
                            </div>
                            <div v-else class="col-span-3 h-2"
                        /></template>
                    </div>
                </aside>
            </div>
            <button
                type="button"
                class="mx-auto mt-4 block rounded-full bg-white px-5 py-2 text-sm font-bold text-slate-700 shadow"
                @click="closeEditor"
            >
                Fechar editor
            </button>
        </div>
    </main>
</template>
