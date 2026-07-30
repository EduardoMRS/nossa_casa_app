<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { Save, ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';

defineOptions({});

export interface Post {
    id?: string;
    title: string;
    slug: string;
    content: string;
    published_at?: string | null;
    expires_at?: string | null;
    author_id?: string | null;
    church_id?: string | null;
    category?: string | null;
}

const props = defineProps<{
    post?: Post;
    available_categories: { id: string; name: string }[];
}>();

const isEditing = computed(() => !!props.post?.id);

const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    categories: props.post?.category ? [props.post?.category] : [],
    content: props.post?.content ?? '',
    published_at: props.post?.published_at ?? '',
    expires_at: props.post?.expires_at ?? '',
    author_id: props.post?.author_id ?? '',
    church_id: props.post?.church_id ?? '',
});

const submit = () => {
    if (isEditing.value) {
        form.put(`/api/post/${props.post?.id}`);
    } else {
        form.post('/api/post');
    }
};
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-300 p-8">
        <Head :title="isEditing ? 'Editar Postagem' : 'Criar Postagem'" />

        <div class="max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-zinc-100 tracking-wide">
                    <span class="text-green-400">/</span> {{ isEditing ? 'Editar Postagem' : 'Nova Postagem' }}
                </h1>
                <Link href="/posts" class="flex items-center gap-2 text-zinc-400 hover:text-green-400 transition-colors">
                    <ArrowLeft class="w-4 h-4" />
                    Voltar
                </Link>
            </div>

            <div class="bg-zinc-900 border border-zinc-800 p-6 rounded-sm">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Título -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-zinc-400 uppercase tracking-wider">Título <span class="text-green-500">*</span></label>
                            <input 
                                v-model="form.title" 
                                type="text" 
                                maxlength="255"
                                required
                                class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 px-4 py-2 rounded-sm focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 transition-all"
                            />
                            <div v-if="form.errors.title" class="text-red-500 text-xs">{{ form.errors.title }}</div>
                        </div>

                        <!-- Slug -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-zinc-400 uppercase tracking-wider">Slug <span class="text-green-500">*</span></label>
                            <input 
                                v-model="form.slug" 
                                type="text" 
                                maxlength="255"
                                required
                                class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 px-4 py-2 rounded-sm font-mono focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 transition-all"
                            />
                            <div v-if="form.errors.slug" class="text-red-500 text-xs">{{ form.errors.slug }}</div>
                        </div>
                    </div>

                    <!-- Categoria -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-zinc-400 uppercase tracking-wider">Categoria</label>
                        <!-- change to multselect -->
                        <select 
                            v-model="form.categories" 
                            multiple
                            class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 px-4 py-2 rounded-sm focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 transition-all cursor-pointer"
                        >
                            <option value="" disabled>Selecione uma categoria...</option>
                            <option 
                                v-for="category in available_categories" 
                                :key="category.id" 
                                :value="category.name"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                        <div v-if="form.errors.categories" class="text-red-500 text-xs">{{ form.errors.categories }}</div>
                    </div>

                    <!-- Conteúdo -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-zinc-400 uppercase tracking-wider">Conteúdo <span class="text-green-500">*</span></label>
                        <textarea 
                            v-model="form.content" 
                            rows="8"
                            required
                            class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 px-4 py-2 rounded-sm focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 transition-all resize-y"
                        ></textarea>
                        <div v-if="form.errors.content" class="text-red-500 text-xs">{{ form.errors.content }}</div>
                    </div>

                    <!-- Datas -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-zinc-400 uppercase tracking-wider">Publicação (Opcional)</label>
                            <input 
                                v-model="form.published_at" 
                                type="datetime-local" 
                                class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 px-4 py-2 rounded-sm focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 transition-all [color-scheme:dark]"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-zinc-400 uppercase tracking-wider">Expiração (Opcional)</label>
                            <input 
                                v-model="form.expires_at" 
                                type="datetime-local" 
                                class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 px-4 py-2 rounded-sm focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 transition-all [color-scheme:dark]"
                            />
                        </div>
                    </div>

                    <!-- Botão de Ação -->
                    <div class="pt-4 flex justify-end">
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="flex items-center gap-2 bg-green-500 hover:bg-green-400 text-zinc-950 px-6 py-2 rounded-sm font-semibold transition-colors shadow-[0_0_10px_rgba(34,197,94,0.4)] disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <Save class="w-4 h-4" />
                            {{ isEditing ? 'Atualizar' : 'Salvar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
