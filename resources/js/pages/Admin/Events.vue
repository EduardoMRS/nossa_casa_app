<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    Edit3,
    Eye,
    Plus,
    Tags,
    Trash2,
    Users,
} from '@lucide/vue';
import { ref } from 'vue';
import CategoryManagerModal from '@/components/CategoryManagerModal.vue';
import type { ManagedCategory } from '@/components/CategoryManagerModal.vue';
import { useI18n } from '@/lib/i18n';
import {
    create as createEvent,
    edit as editEvent,
    show as showEvent,
} from '@/routes/events';

type EventItem = {
    id: string;
    title: string;
    slug: string;
    start_time: string;
    end_time: string;
    users_count: number;
    forms_count: number;
};

defineProps<{
    events: {
        data: EventItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    categories: ManagedCategory[];
}>();

const { locale, t } = useI18n();
const categoriesOpen = ref(false);

const formatDate = (value: string): string =>
    new Date(value).toLocaleString(locale.value === 'pt' ? 'pt-BR' : 'en-US');

const remove = (event: EventItem): void => {
    if (
        window.confirm(t('admin.events.delete_confirm', { title: event.title }))
    ) {
        router.delete(`/api/event/${event.id}`);
    }
};
</script>

<template>
    <Head :title="t('admin.events.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="flex flex-col justify-between gap-4 rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white shadow-sm md:flex-row md:items-end"
        >
            <div>
                <p
                    class="text-xs font-bold tracking-[0.2em] text-blue-200 uppercase"
                >
                    {{ t('admin.events.kicker') }}
                </p>
                <h1 class="mt-2 text-3xl font-black">
                    {{ t('admin.events.title') }}
                </h1>
                <p class="mt-2 text-sm text-blue-100">
                    {{ t('admin.events.description') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/30 px-4 py-3 text-sm font-black text-white"
                    @click="categoriesOpen = true"
                >
                    <Tags class="size-4" />{{
                        t('admin.categories.title')
                    }}</button
                ><Link
                    :href="createEvent()"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-black text-indigo-950"
                >
                    <Plus class="size-4" /> {{ t('admin.events.new') }}
                </Link>
            </div>
        </header>

        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead
                        class="border-b bg-slate-50 text-xs font-bold tracking-wide text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">
                                {{ t('admin.events.event') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ t('admin.events.schedule') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ t('admin.events.registration') }}
                            </th>
                            <th class="px-5 py-3 text-right">
                                {{ t('posts.index.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="event in events.data"
                            :key="event.id"
                            class="hover:bg-slate-50"
                        >
                            <td class="px-5 py-4">
                                <p class="font-bold text-slate-900">
                                    {{ event.title }}
                                </p>
                                <p class="text-xs text-slate-400">
                                    /{{ event.slug }}
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-2"
                                    ><CalendarDays
                                        class="size-4 text-indigo-600"
                                    />{{ formatDate(event.start_time) }}</span
                                >
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-2"
                                    ><Users class="size-4 text-emerald-600" />{{
                                        t('admin.events.registration_summary', {
                                            users: event.users_count,
                                            forms: event.forms_count,
                                        })
                                    }}</span
                                >
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="showEvent({ event: event.slug })"
                                        class="rounded-lg border p-2 text-slate-600"
                                        ><Eye class="size-4"
                                    /></Link>
                                    <Link
                                        :href="editEvent({ event: event.id })"
                                        class="rounded-lg border p-2 text-indigo-600"
                                        ><Edit3 class="size-4"
                                    /></Link>
                                    <button
                                        class="rounded-lg border p-2 text-rose-600"
                                        @click="remove(event)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-if="!events.data.length"
                class="p-12 text-center text-sm text-slate-500"
            >
                {{ t('admin.events.empty') }}
            </p>
            <nav class="flex flex-wrap gap-2 border-t p-4">
                <Link
                    v-for="link in events.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'rounded-lg border px-3 py-1.5 text-xs',
                        link.active
                            ? 'bg-indigo-700 text-white'
                            : 'bg-white text-slate-600',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                    ><span v-html="link.label"
                /></Link>
            </nav>
        </section>
        <CategoryManagerModal
            :open="categoriesOpen"
            category-type="event"
            :categories="categories"
            @close="categoriesOpen = false"
        />
    </main>
</template>
