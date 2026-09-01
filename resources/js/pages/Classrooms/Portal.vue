<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, ClipboardCheck, Download, FileText, Lock, MessageSquare, Pin, Send, Settings, Users } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import DynamicFormRenderer from '@/components/forms/DynamicFormRenderer.vue';
import PostArticle from '@/components/PostArticle.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';

type Activity = { id: string; title: string; instructions?: string; available_until?: string; max_attempts: number; attempts: number; can_submit: boolean; form: { title: string; description?: string; schema: unknown } };
type Discussion = { id: string; title: string; content: string; is_pinned: boolean; is_locked: boolean; replies_count: number; author: { first_name: string; last_name: string }; replies: Array<{ id: string; content: string; author: { first_name: string; last_name: string } }> };
const props = defineProps<{
    classroom: { id: string; name: string; slug: string; description?: string; cover_path?: string; accent_color?: string; portal_settings?: Record<string, unknown>; church?: { name: string }; teacher?: { first_name: string; last_name: string } };
    wallPosts: Array<{ post: any; comments: any[]; reactions: any[]; canComment: boolean; canReact: boolean }>;
    activities: Activity[];
    materials: Array<{ id: string; title: string; description?: string; type: string; mimetype?: string; size?: number; download_url: string }>;
    discussions: Discussion[];
    canManage: boolean;
    canUseForum: boolean;
}>();

const tabs = [
    { id: 'wall', label: 'Mural', icon: MessageSquare },
    { id: 'activities', label: 'Atividades', icon: ClipboardCheck },
    { id: 'materials', label: 'Materiais', icon: FileText },
    { id: 'forum', label: 'Fórum', icon: Users },
];
const active = ref('wall');
const answers = reactive<Record<string, Record<string, unknown>>>({});
const submitting = ref('');
const topic = reactive({ title: '', content: '' });
const replies = reactive<Record<string, string>>({});
const accent = computed(() => props.classroom.accent_color || '#4f46e5');

const submitActivity = (activity: Activity): void => {
    submitting.value = activity.id;
    router.post(`/classrooms/${props.classroom.slug}/activities/${activity.id}/submissions`, { answers: answers[activity.id] ?? {} }, {
        preserveScroll: true,
        onFinish: () => { submitting.value = ''; },
    });
};
const createTopic = (): void => {
    router.post(`/classrooms/${props.classroom.slug}/discussions`, topic, {
        preserveScroll: true,
        onSuccess: () => { topic.title = ''; topic.content = ''; },
    });
};
const reply = (discussion: Discussion): void => {
    if (!replies[discussion.id]?.trim()) return;
    router.post(`/classrooms/${props.classroom.slug}/discussions/${discussion.id}/replies`, { content: replies[discussion.id] }, {
        preserveScroll: true,
        onSuccess: () => { replies[discussion.id] = ''; },
    });
};
const size = (bytes?: number): string => !bytes ? '' : bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.ceil(bytes / 1024)} KB`;
</script>

<template>
    <Head :title="classroom.name" />
    <div class="flex min-h-screen flex-col bg-slate-50 text-slate-950" :style="{ '--classroom-accent': accent }">
        <PublicHeader />
        <main class="mx-auto w-full max-w-6xl flex-1 px-3 py-5 sm:px-5 md:py-9">
            <header class="relative overflow-hidden rounded-3xl bg-slate-950 p-6 text-white shadow-xl md:p-10">
                <img v-if="classroom.cover_path" :src="classroom.cover_path" alt="" class="absolute inset-0 size-full object-cover opacity-30" />
                <div class="relative max-w-3xl">
                    <p class="text-xs font-black tracking-[.22em] uppercase" :style="{ color: accent }">{{ classroom.church?.name }} · Classroom</p>
                    <h1 class="mt-3 text-3xl font-black md:text-5xl">{{ classroom.name }}</h1>
                    <p v-if="classroom.description" class="mt-4 max-w-2xl leading-7 text-slate-300">{{ classroom.description }}</p>
                    <p v-if="classroom.teacher" class="mt-5 inline-flex items-center gap-2 text-sm font-bold"><Users class="size-4" />{{ classroom.teacher.first_name }} {{ classroom.teacher.last_name }}</p>
                    <Link v-if="canManage" :href="`/dashboard/classrooms/${classroom.id}/content`" class="mt-5 ml-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-bold backdrop-blur hover:bg-white/20"><Settings class="size-4" />Gerenciar</Link>
                </div>
            </header>

            <nav class="sticky top-[var(--public-header-height,0px)] z-20 mt-5 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                <button v-for="tab in tabs" :key="tab.id" type="button" class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-3 text-sm font-bold transition" :class="active === tab.id ? 'text-white shadow' : 'text-slate-600 hover:bg-slate-100'" :style="active === tab.id ? { backgroundColor: accent } : {}" @click="active = tab.id"><component :is="tab.icon" class="size-4" />{{ tab.label }}</button>
            </nav>

            <section v-if="active === 'wall'" class="mt-6 space-y-6">
                <PostArticle v-for="item in wallPosts" :key="item.post.id" :post="item.post" :comments="item.comments" :reactions="item.reactions" :can-comment="item.canComment" :can-react="item.canReact" />
                <div v-if="!wallPosts.length" class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center"><MessageSquare class="mx-auto size-9 text-slate-300" /><p class="mt-3 font-bold text-slate-600">O mural ainda não tem publicações.</p></div>
            </section>

            <section v-else-if="active === 'activities'" class="mt-6 space-y-5">
                <article v-for="activity in activities" :key="activity.id" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="text-xs font-black tracking-wider uppercase" :style="{ color: accent }">Atividade · {{ activity.attempts }}/{{ activity.max_attempts }} tentativas</p><h2 class="mt-1 text-xl font-black">{{ activity.title }}</h2><p v-if="activity.instructions" class="mt-2 text-sm leading-6 text-slate-600">{{ activity.instructions }}</p></div>
                        <span v-if="activity.available_until" class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">Até {{ new Date(activity.available_until).toLocaleString() }}</span>
                    </div>
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:p-6">
                        <h3 class="mb-4 font-black">{{ activity.form.title }}</h3>
                        <DynamicFormRenderer :schema="activity.form.schema" :model-value="answers[activity.id] ?? {}" :disabled="!activity.can_submit" @update:model-value="answers[activity.id] = $event" />
                    </div>
                    <div class="mt-5 flex justify-end"><button type="button" :disabled="!activity.can_submit || submitting === activity.id" class="rounded-full px-6 py-2.5 text-sm font-black text-white disabled:opacity-40" :style="{ backgroundColor: accent }" @click="submitActivity(activity)">{{ activity.can_submit ? 'Enviar atividade' : 'Limite atingido' }}</button></div>
                </article>
                <p v-if="!activities.length" class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">Nenhuma atividade disponível agora.</p>
            </section>

            <section v-else-if="active === 'materials'" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <a v-for="material in materials" :key="material.id" :href="material.download_url" :target="material.type === 'link' ? '_blank' : undefined" class="group rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex items-center justify-between"><div class="grid size-11 place-items-center rounded-2xl bg-indigo-50 text-indigo-700"><FileText class="size-5" /></div><Download class="size-5 text-slate-400 group-hover:text-indigo-700" /></div>
                    <h2 class="mt-4 font-black">{{ material.title }}</h2><p v-if="material.description" class="mt-2 text-sm leading-6 text-slate-600">{{ material.description }}</p><p class="mt-4 text-xs font-bold text-slate-400">{{ material.mimetype || material.type }} <span v-if="size(material.size)">· {{ size(material.size) }}</span></p>
                </a>
                <p v-if="!materials.length" class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500 sm:col-span-2 lg:col-span-3">Nenhum material publicado.</p>
            </section>

            <section v-else class="mt-6 space-y-5">
                <form v-if="canUseForum" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:p-7" @submit.prevent="createTopic">
                    <h2 class="text-xl font-black">Iniciar discussão</h2>
                    <div class="mt-4 grid gap-3"><input v-model="topic.title" required maxlength="180" class="rounded-xl border-slate-300" placeholder="Assunto" /><textarea v-model="topic.content" required maxlength="10000" rows="4" class="rounded-xl border-slate-300" placeholder="Compartilhe uma dúvida ou ideia..." /></div>
                    <div class="mt-4 flex justify-end"><button class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-black text-white" :style="{ backgroundColor: accent }"><Send class="size-4" />Publicar</button></div>
                </form>
                <article v-for="discussion in discussions" :key="discussion.id" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:p-7">
                    <div class="flex items-start justify-between gap-4"><div><div class="flex items-center gap-2 text-xs font-bold text-slate-500"><Pin v-if="discussion.is_pinned" class="size-3.5" />{{ discussion.author.first_name }} {{ discussion.author.last_name }}</div><h2 class="mt-2 text-xl font-black">{{ discussion.title }}</h2></div><Lock v-if="discussion.is_locked" class="size-5 text-slate-400" /></div>
                    <p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ discussion.content }}</p>
                    <div v-if="discussion.replies.length" class="mt-5 space-y-3 border-t border-slate-100 pt-5"><div v-for="item in discussion.replies" :key="item.id" class="rounded-2xl bg-slate-50 p-4"><strong class="text-sm">{{ item.author.first_name }} {{ item.author.last_name }}</strong><p class="mt-1 whitespace-pre-wrap text-sm text-slate-600">{{ item.content }}</p></div></div>
                    <form v-if="canUseForum && !discussion.is_locked" class="mt-4 flex gap-2" @submit.prevent="reply(discussion)"><input v-model="replies[discussion.id]" required class="min-w-0 flex-1 rounded-xl border-slate-300 text-sm" placeholder="Responder à discussão" /><button class="grid size-11 place-items-center rounded-xl text-white" :style="{ backgroundColor: accent }"><Send class="size-4" /></button></form>
                </article>
                <p v-if="!discussions.length" class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">O fórum ainda não tem discussões.</p>
            </section>
        </main>
        <PublicFooter show-locale />
    </div>
</template>
