<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import CategorySelector from '@/components/CategorySelector.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type VerseData = {
    book: string;
    chapter: string | number;
    verse: string | number;
    content: string;
    version: string;
    has_record: boolean;
};

type LibraryItem = {
    id: string;
    title: string;
    description: string | null;
    type: string;
    file_path: string | null;
    preview_url: string | null;
    category_ids: string[];
};

type CategoryOption = {
    id: string;
    name: string;
    slug: string;
    type: string;
};

const props = defineProps<{
    verse: VerseData;
    libraries: LibraryItem[];
    categories: CategoryOption[];
}>();

const verseForm = reactive({
    book: props.verse.book,
    chapter: String(props.verse.chapter || ''),
    verse: String(props.verse.verse || ''),
    content: props.verse.content,
    version: props.verse.version,
});

const libraryForm = reactive({
    title: '',
    description: '',
    type: '',
    file_path: '',
    category_ids: [] as string[],
});

const libraryErrors = ref<Record<string, string>>({});
const verseErrors = ref<Record<string, string>>({});
const processingLibrary = ref(false);
const processingVerse = ref(false);
const editingLibraryId = ref<string | null>(null);
const libraryInputType = ref<'url' | 'file'>('url');
const selectedLibraryFile = ref<File | null>(null);
const selectedLibraryPreviewUrl = ref<string>('');

const currentEditingLibrary = computed(() => {
    if (!editingLibraryId.value) {
        return null;
    }

    return props.libraries.find((library) => library.id === editingLibraryId.value) || null;
});

const selectedLibraryLabel = computed(() => {
    if (!editingLibraryId.value) {
        return 'Nova obra';
    }

    return props.libraries.find((library) => library.id === editingLibraryId.value)?.title || 'Editar obra';
});

const libraryPreviewSource = computed(() => {
    if (libraryInputType.value === 'file') {
        return selectedLibraryPreviewUrl.value || currentEditingLibrary.value?.preview_url || '';
    }

    if (libraryForm.file_path.trim()) {
        return libraryForm.file_path.trim();
    }

    return currentEditingLibrary.value?.preview_url || '';
});

const revokeSelectedPreview = () => {
    if (selectedLibraryPreviewUrl.value) {
        URL.revokeObjectURL(selectedLibraryPreviewUrl.value);
        selectedLibraryPreviewUrl.value = '';
    }
};

const fillLibraryForm = (library: LibraryItem) => {
    editingLibraryId.value = library.id;
    libraryForm.title = library.title;
    libraryForm.description = library.description || '';
    libraryForm.type = library.type;
    libraryForm.file_path = library.file_path || '';
    libraryForm.category_ids = [...library.category_ids];
    libraryInputType.value = library.file_path && library.file_path.startsWith('http') ? 'url' : 'file';
    selectedLibraryFile.value = null;
    revokeSelectedPreview();
    libraryErrors.value = {};
};

const resetLibraryForm = () => {
    editingLibraryId.value = null;
    libraryForm.title = '';
    libraryForm.description = '';
    libraryForm.type = '';
    libraryForm.file_path = '';
    libraryForm.category_ids = [];
    libraryInputType.value = 'url';
    selectedLibraryFile.value = null;
    revokeSelectedPreview();
    libraryErrors.value = {};
};

const handleLibraryTypeChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    libraryInputType.value = target.value === 'file' ? 'file' : 'url';

    if (libraryInputType.value === 'url') {
        selectedLibraryFile.value = null;
        revokeSelectedPreview();
    }
};

const handleLibraryFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    selectedLibraryFile.value = file;
    revokeSelectedPreview();

    if (file) {
        selectedLibraryPreviewUrl.value = URL.createObjectURL(file);
        libraryForm.file_path = file.name;
    }
};

const submitVerse = async () => {
    processingVerse.value = true;
    verseErrors.value = {};

    try {
        await router.put('/admin/biblioteca-versiculo/verse', {
            book: verseForm.book,
            chapter: Number(verseForm.chapter),
            verse: Number(verseForm.verse),
            content: verseForm.content,
            version: verseForm.version,
        }, {
            preserveScroll: true,
            onError: (errors) => {
                verseErrors.value = errors as Record<string, string>;
            },
        });
    } finally {
        processingVerse.value = false;
    }
};

const submitLibrary = async () => {
    processingLibrary.value = true;
    libraryErrors.value = {};

    const payload = {
        title: libraryForm.title,
        description: libraryForm.description,
        type: libraryForm.type,
        file_path: libraryInputType.value === 'file'
            ? selectedLibraryFile.value ?? libraryForm.file_path
            : libraryForm.file_path,
            category_ids: libraryForm.category_ids,
    };

    try {
        if (editingLibraryId.value) {
            await router.put(`/admin/biblioteca-versiculo/library/${editingLibraryId.value}`, payload, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: resetLibraryForm,
                onError: (errors) => {
                    libraryErrors.value = errors as Record<string, string>;
                },
            });
        } else {
            await router.post('/admin/biblioteca-versiculo/library', payload, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: resetLibraryForm,
                onError: (errors) => {
                    libraryErrors.value = errors as Record<string, string>;
                },
            });
        }
    } finally {
        processingLibrary.value = false;
    }
};

const deleteLibrary = (library: LibraryItem) => {
    if (!confirm(`Excluir "${library.title}"?`)) {
        return;
    }

    router.delete(`/admin/biblioteca-versiculo/library/${library.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingLibraryId.value === library.id) {
                resetLibraryForm();
            }
        },
    });
};

onBeforeUnmount(() => {
    revokeSelectedPreview();
});
</script>

<template>
    <Head title="Biblioteca e Versiculo" />

    <div class="space-y-6 p-4 md:p-6">
        <Heading
            variant="small"
            title="Gestao da Biblioteca Digital & Devocional"
            description="Gerencie a leitura do dia e os materiais disponiveis para download e estudo."
        />

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-600">Versiculo do dia</p>
                        <h2 class="text-lg font-black text-slate-900">Home devocional</h2>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700">
                        {{ props.verse.has_record ? 'Ativo' : 'Sem registro' }}
                    </span>
                </div>

                <div class="mt-4 grid gap-4">
                    <div class="grid gap-2">
                        <Label for="verse_content">Texto do versiculo</Label>
                        <textarea
                            id="verse_content"
                            v-model="verseForm.content"
                            rows="5"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        />
                        <InputError :message="verseErrors.content" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="verse_book">Livro</Label>
                            <Input id="verse_book" v-model="verseForm.book" />
                            <InputError :message="verseErrors.book" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="verse_chapter">Capitulo</Label>
                            <Input id="verse_chapter" v-model="verseForm.chapter" type="number" min="1" />
                            <InputError :message="verseErrors.chapter" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="verse_verse">Versiculo</Label>
                            <Input id="verse_verse" v-model="verseForm.verse" type="number" min="1" />
                            <InputError :message="verseErrors.verse" />
                        </div>
                    </div>

                    <div class="grid gap-2 md:max-w-xs">
                        <Label for="verse_version">Versao / traducao</Label>
                        <Input id="verse_version" v-model="verseForm.version" />
                        <InputError :message="verseErrors.version" />
                    </div>

                    <div>

                    <CategorySelector
                        v-model="libraryForm.category_ids"
                        :categories="categories"
                        label="Categorias da biblioteca"
                        hint="Use apenas categorias da igreja atual"
                    />
                        <Button :disabled="processingVerse" @click="submitVerse">
                            {{ processingVerse ? 'Salvando...' : 'Atualizar versiculo da home' }}
                        </Button>
                    </div>
                </div>
            </article>

            <article class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-600">Acervo</p>
                        <h2 class="text-lg font-black text-slate-900">Categorias cadastradas</h2>
                    </div>
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-[11px] font-bold text-indigo-700">{{ props.libraries.length }} obras</span>
                </div>

                <form class="mt-4 grid gap-3" @submit.prevent="submitLibrary">
                    <div class="grid gap-2">
                        <Label for="library_source_type">Tipo de arquivo</Label>
                        <select
                            id="library_source_type"
                            :value="libraryInputType"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            @change="handleLibraryTypeChange"
                        >
                            <option value="url">URL</option>
                            <option value="file">Arquivo</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="library_title">Titulo</Label>
                        <Input id="library_title" v-model="libraryForm.title" />
                        <InputError :message="libraryErrors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="library_type">Categoria / tipo</Label>
                        <Input id="library_type" v-model="libraryForm.type" placeholder="Devocional, estudo, noticia..." />
                        <InputError :message="libraryErrors.type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="library_description">Descricao</Label>
                        <textarea
                            id="library_description"
                            v-model="libraryForm.description"
                            rows="4"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        />
                        <InputError :message="libraryErrors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="library_file">{{ libraryInputType === 'file' ? 'Selecionar arquivo' : 'URL / caminho do arquivo' }}</Label>
                        <Input
                            v-if="libraryInputType === 'url'"
                            id="library_file"
                            v-model="libraryForm.file_path"
                            placeholder="https://... ou caminho do arquivo"
                        />
                        <input
                            v-else
                            id="library_file"
                            type="file"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            @change="handleLibraryFileChange"
                        />
                        <InputError :message="libraryErrors.file_path" />
                    </div>

                    <div v-if="libraryPreviewSource" class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                        <div class="border-b border-slate-200 px-4 py-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                            Preview antes de enviar
                        </div>
                        <div class="p-4">
                            <img
                                v-if="libraryPreviewSource.match(/\.(png|jpe?g|webp|gif|svg)(\?.*)?$/i)"
                                :src="libraryPreviewSource"
                                alt="Preview do arquivo"
                                class="h-48 w-full rounded-xl object-cover"
                            />
                            <div v-else class="flex items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-white p-4">
                                <div class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600">Arquivo</div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">
                                        {{ libraryInputType === 'file' ? (selectedLibraryFile?.name || 'Arquivo selecionado') : libraryForm.file_path || 'Caminho/URL informado' }}
                                    </p>
                                    <p class="text-xs text-slate-500">{{ libraryPreviewSource }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button :disabled="processingLibrary">
                            {{ processingLibrary ? 'Salvando...' : selectedLibraryLabel }}
                        </Button>
                        <Button v-if="editingLibraryId" type="button" variant="outline" @click="resetLibraryForm">
                            Cancelar edicao
                        </Button>
                    </div>
                </form>
            </article>
        </section>

        <section class="rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-700">Acervo de livros & roteiros cadastrados</h2>
                <span class="text-xs text-slate-400">{{ props.libraries.length }} registros</span>
            </div>

            <div v-if="props.libraries.length > 0" class="divide-y divide-slate-100">
                <div
                    v-for="library in props.libraries"
                    :key="library.id"
                    class="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-slate-900">{{ library.title }}</p>
                        <p class="text-xs text-slate-500">{{ library.description || 'Sem descricao' }}</p>
                        <div class="flex flex-wrap gap-2 pt-1 text-[11px] font-semibold text-slate-500">
                            <span class="rounded-full bg-slate-100 px-2 py-1">{{ library.type }}</span>
                            <span v-if="library.file_path" class="rounded-full bg-slate-100 px-2 py-1">PDF / Link</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" @click="fillLibraryForm(library)">
                            Editar
                        </Button>
                        <Button type="button" variant="destructive" @click="deleteLibrary(library)">
                            Excluir
                        </Button>
                    </div>
                </div>
            </div>

            <div v-else class="px-5 py-8 text-center text-sm text-slate-500">
                Nenhuma obra cadastrada ainda.
            </div>
        </section>
    </div>
</template>
