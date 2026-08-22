<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import { useI18n } from '@/lib/i18n';
import { show as eventRegistrations } from '@/routes/admin/events';

const props = defineProps<{
    event: { id: string; title: string; slug: string };
    posts: Array<{ id: string; title: string; content: string }>;
    materials: Array<{
        id: string;
        title: string;
        type: string;
        url: string | null;
    }>;
    linkedMediaIds: string[];
    availableMedia: Array<{
        id: string;
        title: string;
        url: string;
        mimetype: string;
        gallery: boolean;
    }>;
}>();
const { t } = useI18n();
const material = ref<{
    title: string;
    type: 'link' | 'file';
    url: string;
    file: File | null;
}>({ title: '', type: 'link', url: '', file: null });
const selectedMedia = ref([...props.linkedMediaIds]);
const processing = ref(false);
const refresh = () => router.reload();
const baseUrl = `/dashboard/events/${props.event.id}/content`;
const deletePost = async (id: string) => {
    if (window.confirm(t('admin.event_content.delete_confirm'))) {
        await axios.delete(`${baseUrl}/posts/${id}`);
        refresh();
    }
};
const createMaterial = async () => {
    processing.value = true;
    const data = new FormData();
    data.append('title', material.value.title);
    data.append('type', material.value.type);
    data.append('url', material.value.url);

    if (material.value.file) {
        data.append('file', material.value.file);
    }

    try {
        await axios.post(`${baseUrl}/materials`, data);
        material.value = { title: '', type: 'link', url: '', file: null };
        refresh();
    } finally {
        processing.value = false;
    }
};
const deleteMaterial = async (id: string) => {
    if (window.confirm(t('admin.event_content.delete_confirm'))) {
        await axios.delete(`${baseUrl}/materials/${id}`);
        refresh();
    }
};
const saveMedia = async () => {
    processing.value = true;

    try {
        await axios.put(`${baseUrl}/media`, {
            media_ids: selectedMedia.value,
        });
        refresh();
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Head :title="`${t('admin.event_content.title')} - ${event.title}`" />
    <main class="mx-auto max-w-6xl space-y-6 px-4 py-8">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold text-teal-700">
                    {{ t('admin.event_content.kicker') }}
                </p>
                <h1 class="text-3xl font-black">{{ event.title }}</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    :href="eventRegistrations({ event: event.id })"
                    class="rounded-lg border px-4 py-2 text-sm font-bold"
                    >{{ t('admin.event_content.registrations') }}</Link
                >
                <Link
                    :href="`${baseUrl}/posts/create`"
                    class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-bold text-white"
                    >{{ t('admin.event_content.new_post') }}</Link
                >
            </div>
        </header>
        <section class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border bg-white p-5 lg:col-span-2">
                <h2 class="text-xl font-black">
                    {{ t('admin.event_content.posts') }}
                </h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="item in posts"
                        :key="item.id"
                        class="rounded-xl border bg-slate-50 p-4"
                    >
                        <h3 class="font-black">{{ item.title }}</h3>
                        <div class="mt-4 flex gap-2">
                            <Link
                                :href="`${baseUrl}/posts/${item.id}/edit`"
                                class="rounded-lg border bg-white px-3 py-2 text-xs font-bold"
                                >{{ t('admin.common.edit') }}</Link
                            >
                            <button
                                class="rounded-lg px-3 py-2 text-xs font-bold text-red-700"
                                @click="deletePost(item.id)"
                            >
                                {{ t('admin.common.delete') }}
                            </button>
                        </div>
                    </article>
                    <p v-if="!posts.length" class="text-sm text-slate-500">
                        {{ t('admin.event_content.empty_posts') }}
                    </p>
                </div>
            </div>
            <div class="rounded-2xl border bg-white p-5">
                <h2 class="text-xl font-black">
                    {{ t('admin.event_content.materials') }}
                </h2>
                <form class="mt-4 space-y-3" @submit.prevent="createMaterial">
                    <input
                        v-model="material.title"
                        required
                        :placeholder="t('admin.common.title')"
                        class="w-full rounded-lg border-slate-300"
                    />
                    <select
                        v-model="material.type"
                        class="w-full rounded-lg border-slate-300"
                    >
                        <option value="link">
                            {{ t('admin.event_content.link') }}
                        </option>
                        <option value="file">
                            {{ t('admin.event_content.file') }}
                        </option>
                    </select>
                    <input
                        v-if="material.type === 'link'"
                        v-model="material.url"
                        type="url"
                        required
                        class="w-full rounded-lg border-slate-300"
                    />
                    <input
                        v-else
                        type="file"
                        required
                        class="w-full text-sm"
                        @change="
                            material.file =
                                ($event.target as HTMLInputElement)
                                    .files?.[0] ?? null
                        "
                    />
                    <button
                        :disabled="processing"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white"
                    >
                        {{ t('admin.common.save') }}
                    </button>
                </form>
                <ul class="mt-5 divide-y">
                    <li
                        v-for="item in materials"
                        :key="item.id"
                        class="flex items-center justify-between py-3"
                    >
                        <a
                            :href="item.url ?? '#'"
                            target="_blank"
                            class="font-semibold text-teal-800"
                            >{{ item.title }}</a
                        ><button
                            class="text-sm font-bold text-red-700"
                            @click="deleteMaterial(item.id)"
                        >
                            {{ t('admin.common.delete') }}
                        </button>
                    </li>
                </ul>
            </div>
        </section>
        <section class="rounded-2xl border bg-white p-5">
            <h2 class="text-xl font-black">
                {{ t('admin.event_content.media') }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ t('admin.event_content.media_hint') }}
            </p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label
                    v-for="item in availableMedia"
                    :key="item.id"
                    class="flex gap-3 rounded-xl border p-3"
                    ><input
                        v-model="selectedMedia"
                        type="checkbox"
                        :value="item.id"
                    /><img
                        :src="item.url"
                        :alt="item.title"
                        class="h-16 w-20 rounded object-cover"
                    /><span class="text-sm font-semibold">{{
                        item.title
                    }}</span></label
                >
            </div>
            <button
                :disabled="processing"
                class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white"
                @click="saveMedia"
            >
                {{ t('admin.common.save') }}
            </button>
        </section>
    </main>
</template>
