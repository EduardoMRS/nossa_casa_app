<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, ChevronRight, Users } from '@lucide/vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

defineProps<{ classrooms: Array<{ id: string; name: string; slug: string; description?: string; accent_color?: string; teacher?: { first_name: string; last_name: string }; church?: { name: string } }> }>();
</script>

<template>
    <Head :title="t('classrooms.index.meta_title')" />
    <div class="flex min-h-screen flex-col bg-slate-50">
        <PublicHeader />
        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-10">
            <div class="mb-8">
                <p class="text-xs font-black tracking-[.22em] text-indigo-600 uppercase">{{ t('classrooms.index.kicker') }}</p>
                <h1 class="mt-2 text-3xl font-black text-slate-950 md:text-5xl">{{ t('classrooms.index.title') }}</h1>
                <p class="mt-3 text-slate-600">{{ t('classrooms.index.description') }}</p>
            </div>
            <div v-if="classrooms.length" class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <Link v-for="room in classrooms" :key="room.id" :href="`/classrooms/${room.slug}`" class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="grid size-12 place-items-center rounded-2xl text-white" :style="{ backgroundColor: room.accent_color || '#4f46e5' }"><BookOpen class="size-6" /></div>
                    <p class="mt-5 text-xs font-bold text-slate-500">{{ room.church?.name }}</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">{{ room.name }}</h2>
                    <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">{{ room.description }}</p>
                    <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-sm font-bold text-indigo-700">
                        <span class="inline-flex items-center gap-2"><Users class="size-4" />{{ room.teacher?.first_name }} {{ room.teacher?.last_name }}</span>
                        <ChevronRight class="size-5 transition group-hover:translate-x-1" />
                    </div>
                </Link>
            </div>
            <p v-else class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">{{ t('classrooms.index.empty') }}</p>
        </main>
        <PublicFooter show-locale />
    </div>
</template>
