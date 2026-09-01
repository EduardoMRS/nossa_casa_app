<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { BookOpen, ExternalLink, FileText, MessageSquare, Plus, Settings, Trash2 } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<{
    classroom: any;
    forms: Array<{ id: string; title: string; description?: string }>;
    portalUrl: string;
}>();
const tab = ref('settings');
const base = `/dashboard/classrooms/${props.classroom.id}/content`;
const settings = useForm({
    name: props.classroom.name,
    description: props.classroom.description ?? '',
    accent_color: props.classroom.accent_color ?? '#4f46e5',
    portal_enabled: Boolean(props.classroom.portal_enabled),
    forum_enabled: props.classroom.portal_settings?.forum_enabled ?? true,
    cover: null as File | null,
});
const post = useForm({ title: '', content: '', published_at: '', comments_enabled: true, reactions_enabled: true });
const activity = useForm({ form_id: '', title: '', instructions: '', published_at: '', available_until: '', max_attempts: 1, is_published: true });
const material = useForm({ title: '', description: '', type: 'link', url: '', file: null as File | null });
const saveSettings = () => settings.transform((data) => ({ ...data, _method: 'put' })).post(`${base}/settings`, { forceFormData: true, preserveScroll: true });
const createPost = () => post.post(`${base}/posts`, { preserveScroll: true, onSuccess: () => post.reset() });
const createActivity = () => activity.post(`${base}/activities`, { preserveScroll: true, onSuccess: () => activity.reset() });
const createMaterial = () => material.post(`${base}/materials`, { forceFormData: true, preserveScroll: true, onSuccess: () => material.reset() });
const remove = (path: string) => { if (confirm('Excluir este item?')) router.delete(path, { preserveScroll: true }); };
const moderate = async (discussion: any, key: 'is_pinned' | 'is_locked') => {
    await axios.put(`${base}/discussions/${discussion.id}`, { is_pinned: key === 'is_pinned' ? !discussion.is_pinned : discussion.is_pinned, is_locked: key === 'is_locked' ? !discussion.is_locked : discussion.is_locked });
    router.reload({ only: ['classroom'] });
};
const tabs = [{ id: 'settings', label: 'Portal', icon: Settings }, { id: 'posts', label: 'Mural', icon: MessageSquare }, { id: 'activities', label: 'Atividades', icon: BookOpen }, { id: 'materials', label: 'Materiais', icon: FileText }, { id: 'forum', label: 'Fórum', icon: MessageSquare }];
</script>

<template>
    <Head :title="`Classroom - ${classroom.name}`" />
    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8">
        <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-gradient-to-r from-indigo-950 to-indigo-700 p-6 text-white md:p-8">
            <div><p class="text-xs font-black tracking-[.2em] text-indigo-200 uppercase">Gestão do classroom</p><h1 class="mt-2 text-3xl font-black">{{ classroom.name }}</h1></div>
            <Link :href="portalUrl" class="inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-black text-indigo-950"><ExternalLink class="size-4" />Abrir portal</Link>
        </header>
        <nav class="flex gap-2 overflow-x-auto rounded-2xl border bg-white p-2">
            <button v-for="item in tabs" :key="item.id" class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold" :class="tab === item.id ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="tab = item.id"><component :is="item.icon" class="size-4" />{{ item.label }}</button>
        </nav>

        <section v-if="tab === 'settings'" class="rounded-3xl border bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Identidade e acesso</h2>
            <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="saveSettings">
                <label class="space-y-1 text-sm font-bold">Nome<input v-model="settings.name" required class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm font-bold">Cor de destaque<input v-model="settings.accent_color" type="color" class="h-11 w-full rounded-xl border-slate-300 p-1" /></label>
                <label class="space-y-1 text-sm font-bold md:col-span-2">Descrição<textarea v-model="settings.description" rows="4" class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm font-bold md:col-span-2">Capa<input type="file" accept="image/*" class="w-full rounded-xl border p-2" @change="settings.cover = ($event.target as HTMLInputElement).files?.[0] ?? null" /></label>
                <label class="flex items-center gap-3 rounded-xl border p-4 text-sm font-bold"><input v-model="settings.portal_enabled" type="checkbox" />Portal disponível</label>
                <label class="flex items-center gap-3 rounded-xl border p-4 text-sm font-bold"><input v-model="settings.forum_enabled" type="checkbox" />Fórum disponível</label>
                <div class="md:col-span-2 flex justify-end"><button :disabled="settings.processing" class="rounded-full bg-indigo-600 px-6 py-2.5 text-sm font-black text-white">Salvar portal</button></div>
            </form>
        </section>

        <section v-else-if="tab === 'posts'" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-3"><article v-for="item in classroom.posts" :key="item.id" class="rounded-2xl border bg-white p-5"><div class="flex justify-between gap-3"><div><h3 class="font-black">{{ item.title }}</h3><p class="mt-1 text-xs text-slate-500">{{ item.comments_enabled ? 'Comentários ativos' : 'Comentários desativados' }} · {{ item.reactions_enabled ? 'Reações ativas' : 'Reações desativadas' }}</p></div><button class="text-rose-600" @click="remove(`${base}/posts/${item.id}`)"><Trash2 class="size-4" /></button></div></article><p v-if="!classroom.posts?.length" class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-slate-500">Nenhuma publicação.</p></div>
            <form class="h-fit space-y-3 rounded-2xl border bg-white p-5" @submit.prevent="createPost"><h2 class="font-black">Nova publicação</h2><input v-model="post.title" required class="w-full rounded-xl border-slate-300" placeholder="Título" /><textarea v-model="post.content" required rows="7" class="w-full rounded-xl border-slate-300" placeholder="Conteúdo (Markdown)" /><label class="flex gap-2 text-sm"><input v-model="post.comments_enabled" type="checkbox" />Permitir comentários</label><label class="flex gap-2 text-sm"><input v-model="post.reactions_enabled" type="checkbox" />Permitir reações</label><button class="w-full rounded-xl bg-indigo-600 py-2.5 font-black text-white"><Plus class="mr-1 inline size-4" />Publicar</button></form>
        </section>

        <section v-else-if="tab === 'activities'" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-3"><article v-for="item in classroom.activities" :key="item.id" class="rounded-2xl border bg-white p-5"><div class="flex justify-between"><div><h3 class="font-black">{{ item.title }}</h3><p class="text-sm text-slate-500">{{ item.form?.title }} · {{ item.max_attempts }} tentativa(s)</p></div><button class="text-rose-600" @click="remove(`${base}/activities/${item.id}`)"><Trash2 class="size-4" /></button></div></article></div>
            <form class="h-fit space-y-3 rounded-2xl border bg-white p-5" @submit.prevent="createActivity"><h2 class="font-black">Nova atividade</h2><input v-model="activity.title" required class="w-full rounded-xl border-slate-300" placeholder="Título" /><select v-model="activity.form_id" required class="w-full rounded-xl border-slate-300"><option value="">Escolha o formulário</option><option v-for="form in forms" :key="form.id" :value="form.id">{{ form.title }}</option></select><textarea v-model="activity.instructions" rows="4" class="w-full rounded-xl border-slate-300" placeholder="Instruções" /><label class="space-y-1 text-xs font-bold">Disponível até<input v-model="activity.available_until" type="datetime-local" class="w-full rounded-xl border-slate-300" /></label><label class="space-y-1 text-xs font-bold">Tentativas<input v-model="activity.max_attempts" type="number" min="1" max="20" class="w-full rounded-xl border-slate-300" /></label><button class="w-full rounded-xl bg-indigo-600 py-2.5 font-black text-white">Criar atividade</button></form>
        </section>

        <section v-else-if="tab === 'materials'" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="grid gap-3 md:grid-cols-2"><article v-for="item in classroom.materials" :key="item.id" class="rounded-2xl border bg-white p-5"><div class="flex justify-between"><div><h3 class="font-black">{{ item.title }}</h3><p class="text-xs text-slate-500">{{ item.type }}</p></div><button class="text-rose-600" @click="remove(`${base}/materials/${item.id}`)"><Trash2 class="size-4" /></button></div></article></div>
            <form class="h-fit space-y-3 rounded-2xl border bg-white p-5" @submit.prevent="createMaterial"><h2 class="font-black">Adicionar material</h2><input v-model="material.title" required class="w-full rounded-xl border-slate-300" placeholder="Título" /><textarea v-model="material.description" rows="3" class="w-full rounded-xl border-slate-300" placeholder="Descrição" /><select v-model="material.type" class="w-full rounded-xl border-slate-300"><option value="link">Link</option><option value="file">Arquivo</option></select><input v-if="material.type === 'link'" v-model="material.url" required type="url" class="w-full rounded-xl border-slate-300" placeholder="https://" /><input v-else required type="file" class="w-full rounded-xl border p-2" @change="material.file = ($event.target as HTMLInputElement).files?.[0] ?? null" /><button class="w-full rounded-xl bg-indigo-600 py-2.5 font-black text-white">Adicionar</button></form>
        </section>

        <section v-else class="space-y-3"><article v-for="item in classroom.discussions" :key="item.id" class="rounded-2xl border bg-white p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-black">{{ item.title }}</h3><p class="text-sm text-slate-500">{{ item.author?.name || item.author?.first_name }}</p></div><div class="flex gap-2"><button class="rounded-lg border px-3 py-2 text-xs font-bold" @click="moderate(item, 'is_pinned')">{{ item.is_pinned ? 'Desafixar' : 'Fixar' }}</button><button class="rounded-lg border px-3 py-2 text-xs font-bold" @click="moderate(item, 'is_locked')">{{ item.is_locked ? 'Desbloquear' : 'Bloquear' }}</button><button class="rounded-lg px-3 py-2 text-xs font-bold text-rose-600" @click="remove(`${base}/discussions/${item.id}`)">Excluir</button></div></div></article></section>
    </main>
</template>
