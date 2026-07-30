<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash, Eye, MessageCircle, Heart, Clock } from '@lucide/vue';

defineOptions({});

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface User {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
}

export interface PostMetrics {
    comments_count: number;
    reactions_count: number;
}

export interface Post {
    id: string;
    title: string;
    slug: string;
    content: string;
    published_at: string | null;
    expires_at: string | null;
    author: User;
    metrics: PostMetrics;
    translations: Record<string, string> | any[];
}

defineProps<{
    posts: {
        data: Post[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    }
}>();

const deletePost = (id: string) => {
    if (confirm('Tem certeza que deseja excluir esta postagem?')) {
        router.delete(`/post/${id}`);
    }
};

// Função para formatar a data ISO para o padrão brasileiro
const formatDate = (dateString: string | null) => {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).replace(' de ', '/').replace('. de ', '/');
};

// Helper para extrair o título (prioriza a tradução se existir, senão usa o original)
const getTitle = (post: Post) => {
    if (!Array.isArray(post.translations) && post.translations?.title) {
        return post.translations.title;
    }
    return post.title;
};
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-300 p-8">
        <Head title="Listagem de Postagens" />

        <div class="max-w-7xl mx-auto">
            <!-- Cabeçalho -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-zinc-100 tracking-wide">Postagens</h1>
                    <p class="text-sm text-zinc-500 mt-1">Gerencie os conteúdos, autores e publicações.</p>
                </div>
                <Link 
                    href="/posts/create" 
                    class="flex items-center gap-2 bg-green-500 hover:bg-green-400 text-zinc-950 px-4 py-2 rounded-sm font-semibold transition-colors shadow-[0_0_15px_rgba(34,197,94,0.3)]"
                >
                    <Plus class="w-4 h-4" />
                    Nova Postagem
                </Link>
            </div>

            <!-- Tabela -->
            <div class="bg-zinc-900 border border-zinc-800 rounded-sm overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-zinc-950 border-b border-zinc-800 text-green-400 uppercase tracking-wider text-xs">
                        <tr>
                            <th class="px-6 py-4 font-medium">Conteúdo</th>
                            <th class="px-6 py-4 font-medium">Autor</th>
                            <th class="px-6 py-4 font-medium">Engajamento</th>
                            <th class="px-6 py-4 font-medium">Status / Data</th>
                            <th class="px-6 py-4 font-medium text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        <tr v-for="post in posts.data" :key="post.id" class="hover:bg-zinc-800/40 transition-colors group">
                            <!-- Conteúdo (Título e Slug) -->
                            <td class="px-6 py-4">
                                <div class="flex flex-col max-w-md">
                                    <span class="text-zinc-100 font-medium truncate" :title="getTitle(post)">
                                        {{ getTitle(post) }}
                                    </span>
                                    <span class="text-xs font-mono text-zinc-500 truncate mt-0.5" :title="post.slug">
                                        /{{ post.slug }}
                                    </span>
                                </div>
                            </td>

                            <!-- Autor -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-zinc-800 flex items-center justify-center text-xs font-bold text-green-500 border border-zinc-700">
                                        {{ post.author.first_name.charAt(0) }}{{ post.author.last_name.charAt(0) }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-zinc-300 text-sm">{{ post.author.first_name }} {{ post.author.last_name }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Engajamento -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4 text-zinc-400">
                                    <div class="flex items-center gap-1.5" title="Reações">
                                        <Heart class="w-4 h-4 text-zinc-500 group-hover:text-red-400 transition-colors" />
                                        <span class="text-xs">{{ post.metrics.reactions_count }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5" title="Comentários">
                                        <MessageCircle class="w-4 h-4 text-zinc-500 group-hover:text-blue-400 transition-colors" />
                                        <span class="text-xs">{{ post.metrics.comments_count }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Status / Data -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Clock class="w-4 h-4" :class="post.published_at ? 'text-green-500' : 'text-zinc-600'" />
                                    <span :class="post.published_at ? 'text-zinc-300' : 'text-zinc-500 italic'">
                                        {{ formatDate(post.published_at) ?? 'Rascunho' }}
                                    </span>
                                </div>
                            </td>

                            <!-- Ações -->
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2 opacity-70 group-hover:opacity-100 transition-opacity">
                                    <Link :href="`/posts/${post.id}`" class="p-2 text-zinc-400 hover:text-green-400 hover:bg-zinc-800 rounded-sm transition-all" title="Visualizar">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                    <Link :href="`/posts/${post.id}/edit`" class="p-2 text-zinc-400 hover:text-yellow-400 hover:bg-zinc-800 rounded-sm transition-all" title="Editar">
                                        <Edit class="w-4 h-4" />
                                    </Link>
                                    <button @click="deletePost(post.id)" class="p-2 text-zinc-400 hover:text-red-500 hover:bg-zinc-800 rounded-sm transition-all" title="Excluir">
                                        <Trash class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="!posts.data.length">
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-zinc-500">
                                    <div class="w-12 h-12 rounded-full bg-zinc-800/50 flex items-center justify-center mb-3 border border-zinc-800">
                                        <Plus class="w-6 h-6 text-zinc-600" />
                                    </div>
                                    <p class="text-base font-medium text-zinc-400">Nenhuma postagem encontrada</p>
                                    <p class="text-sm mt-1">Crie sua primeira postagem para começar.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4" v-if="posts.links.length > 3">
                <span class="text-sm text-zinc-500">
                    Mostrando <strong class="text-zinc-300">{{ posts.from ?? 0 }}</strong> a <strong class="text-zinc-300">{{ posts.to ?? 0 }}</strong> de <strong class="text-zinc-300">{{ posts.total }}</strong> resultados
                </span>
                
                <div class="flex flex-wrap gap-1">
                    <template v-for="(link, key) in posts.links" :key="key">
                        <div 
                            v-if="link.url === null" 
                            class="px-3 py-1.5 text-sm border border-zinc-800 bg-zinc-950/50 text-zinc-600 rounded-sm cursor-not-allowed" 
                            v-html="link.label" 
                        />
                        <Link 
                            v-else 
                            :href="link.url" 
                            class="px-3 py-1.5 text-sm border rounded-sm transition-colors"
                            :class="link.active ? 'border-green-500 text-green-400 bg-green-500/10 font-medium' : 'border-zinc-800 bg-zinc-900 text-zinc-400 hover:border-green-500 hover:text-green-400'"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
