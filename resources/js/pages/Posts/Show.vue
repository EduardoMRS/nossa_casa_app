<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { 
    ArrowLeft, Edit, Trash, Calendar, Clock, 
    MessageCircle, Heart, Reply, Share2, Image as ImageIcon 
} from '@lucide/vue';

defineOptions({});

// --- Interfaces Baseadas no Payload ---
export interface UserDetails {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
}

export interface Media {
    id: string;
    url: string;
    mimetype?: string;
}

export interface Reaction {
    id: string;
    user_id: string;
    content: string;
    type: string;
    user_details: UserDetails;
}

export interface Comment {
    id: string;
    user_id: string;
    content: string;
    created_at: string;
    user_details: UserDetails;
    replies?: Comment[];
    reactions?: Reaction[];
}

export interface Post {
    id: string;
    title: string;
    slug: string;
    content: string;
    published_at: string | null;
    expires_at: string | null;
    category?: string;
    author_details: UserDetails;
    metrics: {
        comments_count: number;
        reactions_count: number;
    };
    medias: Media[];
    translations?: { title?: string };
}

const props = defineProps<{
    post: Post;
    can: {
        edit: boolean;
        delete: boolean;
        comment: boolean;
        react: boolean;
    };
    comments: Comment[];
    reactions: Reaction[];
}>();

// --- Utilitários ---
const formatDate = (dateString: string | null) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('pt-BR', {
        day: '2-digit', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

const getInitials = (user: UserDetails) => {
    return `${user.first_name?.charAt(0) || ''}${user.last_name?.charAt(0) || ''}`;
};

const getTitle = () => props.post.translations?.title || props.post.title;

// --- Ações do Post ---
const deletePost = () => {
    if (confirm('Tem certeza que deseja excluir esta postagem?')) {
        router.delete(`/post/${props.post.id}`);
    }
};

// --- Sistema de Comentários e Reações ---
const replyingTo = ref<string | null>(null);

const commentForm = useForm({
    content: '',
    parent_id: null as string | null
});

const submitComment = (parentId: string | null = null) => {
    commentForm.parent_id = parentId;
    commentForm.post(`/api/post/${props.post.id}/comments`, {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            replyingTo.value = null;
        }
    });
};

const toggleReaction = (content: string, type: string = 'emoji', parentId: string | null = null) => {
    if (!props.can.react) return;
    
    router.post(`/api/post/${props.post.id}/reactions`, {
        content,
        type,
        parent_id: parentId
    }, { preserveScroll: true });
};
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-300 p-8">
        <Head :title="getTitle()" />

        <div class="max-w-4xl mx-auto">
            <!-- Header/Breadcrumb -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                <Link href="/posts" class="flex items-center gap-2 text-zinc-400 hover:text-green-400 transition-colors">
                    <ArrowLeft class="w-4 h-4" />
                    Voltar para lista
                </Link>
                
                <div class="flex gap-3" v-if="can.edit || can.delete">
                    <Link 
                        v-if="can.edit"
                        :href="`/posts/${post.id}/edit`" 
                        class="flex items-center gap-2 px-4 py-2 bg-zinc-900 border border-zinc-800 rounded-sm hover:border-green-500 hover:text-green-400 transition-colors"
                    >
                        <Edit class="w-4 h-4" /> Editar
                    </Link>
                    <button 
                        v-if="can.delete"
                        @click="deletePost" 
                        class="flex items-center gap-2 px-4 py-2 bg-zinc-900 border border-zinc-800 text-red-500 rounded-sm hover:border-red-500 hover:bg-red-500/10 transition-colors"
                    >
                        <Trash class="w-4 h-4" /> Excluir
                    </button>
                </div>
            </div>

            <!-- Corpo da Postagem -->
            <article class="bg-zinc-900 border border-zinc-800 rounded-sm overflow-hidden shadow-xl mb-8">
                <!-- Cover Image (se houver) -->
                <div v-if="post.medias && post.medias.length > 0" class="w-full h-64 md:h-96 bg-zinc-800 relative overflow-hidden border-b border-zinc-800">
                    <img :src="post.medias[0].url" class="w-full h-full object-cover opacity-80" alt="Capa da Postagem" />
                </div>

                <div class="p-8 md:p-12">
                    <header class="mb-8">
                        <div class="flex items-center gap-3 mb-6">
                            <span v-if="post.category" class="px-3 py-1 bg-green-500/10 text-green-400 text-xs font-bold uppercase tracking-wider rounded-sm border border-green-500/20">
                                {{ post.category }}
                            </span>
                            <div class="flex items-center gap-2 text-sm text-zinc-500">
                                <Calendar class="w-4 h-4" />
                                <span>{{ formatDate(post.published_at) }}</span>
                            </div>
                        </div>
                        
                        <h1 class="text-3xl md:text-5xl font-bold text-zinc-100 mb-6 leading-tight">{{ getTitle() }}</h1>
                        
                        <!-- Autor -->
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-zinc-800 flex items-center justify-center text-sm font-bold text-green-500 border border-zinc-700">
                                {{ getInitials(post.author_details) }}
                            </div>
                            <div class="flex flex-col">
                                <span class="text-zinc-200 font-medium">{{ post.author_details.first_name }} {{ post.author_details.last_name }}</span>
                                <span class="text-zinc-500 text-xs">{{ post.author_details.email }}</span>
                            </div>
                        </div>
                    </header>

                    <!-- Conteúdo Principal -->
                    <div class="prose prose-invert prose-zinc max-w-none text-zinc-300 mb-12 whitespace-pre-wrap text-lg leading-relaxed">
                        {{ post.content }}
                    </div>

                    <!-- Barra de Ações do Post -->
                    <footer class="flex flex-wrap items-center justify-between border-t border-zinc-800 pt-6 mt-8 gap-4">
                        <div class="flex gap-2">
                            <button 
                                @click="toggleReaction('👍')"
                                :disabled="!can.react"
                                class="flex items-center gap-2 px-4 py-2 bg-zinc-950 border border-zinc-800 rounded-sm hover:border-green-500 hover:text-green-400 transition-colors disabled:opacity-50"
                            >
                                <Heart class="w-4 h-4 text-zinc-400" />
                                <span>{{ post.metrics.reactions_count }} Curtidas</span>
                            </button>
                            <a 
                                href="#comments"
                                class="flex items-center gap-2 px-4 py-2 bg-zinc-950 border border-zinc-800 rounded-sm hover:border-blue-500 hover:text-blue-400 transition-colors"
                            >
                                <MessageCircle class="w-4 h-4 text-zinc-400" />
                                <span>{{ post.metrics.comments_count }} Comentários</span>
                            </a>
                        </div>
                        
                        <div v-if="post.expires_at" class="flex items-center gap-2 text-sm text-zinc-500">
                            <Clock class="w-4 h-4 text-yellow-500" />
                            <span>Expira: {{ formatDate(post.expires_at) }}</span>
                        </div>
                    </footer>
                </div>
            </article>

            <!-- Seção de Comentários -->
            <section id="comments" class="bg-zinc-900 border border-zinc-800 rounded-sm p-8">
                <h3 class="text-xl font-bold text-zinc-100 mb-6 flex items-center gap-2">
                    <MessageCircle class="w-5 h-5 text-green-500" />
                    Comentários ({{ post.metrics.comments_count }})
                </h3>

                <!-- Formulário de Novo Comentário Principal -->
                <div class="mb-10" v-if="can.comment">
                    <form @submit.prevent="submitComment(null)" class="flex flex-col gap-3">
                        <textarea 
                            v-model="commentForm.content"
                            placeholder="Deixe seu comentário..."
                            rows="3"
                            class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 px-4 py-3 rounded-sm focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 transition-all resize-y"
                            required
                        ></textarea>
                        <div class="flex justify-end">
                            <button 
                                type="submit" 
                                :disabled="commentForm.processing || !commentForm.content"
                                class="bg-green-500 hover:bg-green-400 text-zinc-950 px-6 py-2 rounded-sm font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Comentar
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Lista de Comentários -->
                <div class="space-y-8">
                    <div v-for="comment in comments" :key="comment.id" class="flex gap-4">
                        <!-- Avatar Comentário -->
                        <div class="w-10 h-10 shrink-0 rounded-full bg-zinc-950 flex items-center justify-center text-sm font-bold text-green-500 border border-zinc-800">
                            {{ getInitials(comment.user_details) }}
                        </div>
                        
                        <div class="flex-1 space-y-3">
                            <div class="bg-zinc-950 border border-zinc-800 p-4 rounded-sm">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-medium text-zinc-200">{{ comment.user_details.first_name }} {{ comment.user_details.last_name }}</span>
                                    <span class="text-xs text-zinc-500">{{ formatDate(comment.created_at) }}</span>
                                </div>
                                <p class="text-zinc-300 text-sm whitespace-pre-wrap">{{ comment.content }}</p>
                            </div>

                            <!-- Ações do Comentário -->
                            <div class="flex gap-4 text-xs font-medium pl-2">
                                <button @click="toggleReaction('👍', 'emoji', comment.id)" class="text-zinc-500 hover:text-green-400 transition-colors flex items-center gap-1">
                                    <Heart class="w-3 h-3" /> Reagir
                                </button>
                                <button @click="replyingTo = replyingTo === comment.id ? null : comment.id" class="text-zinc-500 hover:text-green-400 transition-colors flex items-center gap-1">
                                    <Reply class="w-3 h-3" /> Responder
                                </button>
                            </div>

                            <!-- Formulário de Resposta (Inline) -->
                            <div v-if="replyingTo === comment.id" class="mt-3 pl-4 border-l border-zinc-800">
                                <form @submit.prevent="submitComment(comment.id)" class="flex flex-col gap-2">
                                    <textarea 
                                        v-model="commentForm.content"
                                        placeholder="Escreva sua resposta..."
                                        rows="2"
                                        class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 px-3 py-2 text-sm rounded-sm focus:outline-none focus:ring-1 focus:ring-green-500 transition-all"
                                        required
                                    ></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="replyingTo = null" class="px-3 py-1.5 text-xs text-zinc-400 hover:text-zinc-200">Cancelar</button>
                                        <button type="submit" :disabled="commentForm.processing" class="bg-green-500 text-zinc-950 px-4 py-1.5 text-xs rounded-sm font-semibold hover:bg-green-400 transition-colors">Enviar Resposta</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Respostas Aninhadas (Level 2) -->
                            <div v-if="comment.replies && comment.replies.length > 0" class="space-y-4 mt-4 pl-4 border-l border-zinc-800">
                                <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-3">
                                    <div class="w-8 h-8 shrink-0 rounded-full bg-zinc-950 flex items-center justify-center text-xs font-bold text-green-500 border border-zinc-800">
                                        {{ getInitials(reply.user_details) }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="bg-zinc-950 border border-zinc-800 p-3 rounded-sm">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="font-medium text-zinc-200 text-sm">{{ reply.user_details.first_name }} {{ reply.user_details.last_name }}</span>
                                                <span class="text-[10px] text-zinc-500">{{ formatDate(reply.created_at) }}</span>
                                            </div>
                                            <p class="text-zinc-400 text-sm">{{ reply.content }}</p>
                                        </div>
                                        <div class="flex gap-4 text-xs font-medium pl-2 mt-2">
                                            <button @click="toggleReaction('👍', 'emoji', reply.id)" class="text-zinc-500 hover:text-green-400 transition-colors flex items-center gap-1">
                                                <Heart class="w-3 h-3" /> Reagir
                                            </button>
                                            <!-- Para níveis infinitos, você extrairia isso para um componente <CommentItem /> recursivo -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
