<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import { useI18n } from '@/lib/i18n';
type Prayer = { id: string; content: string; created_at: string };
const props = defineProps<{
    requests: { data: Prayer[] };
    stats: Array<{ label: string; value: number }>;
}>();
const requests = ref([...props.requests.data]);
const { locale, t } = useI18n();
const content = ref('');
const open = ref(false);
const sending = ref(false);
async function submit(): Promise<void> {
    sending.value = true;
    const { data } = await axios.post('/api/prayer-requests', {
        content: content.value,
    });
    requests.value.unshift(data);
    content.value = '';
    open.value = false;
    sending.value = false;
}
</script>
<template>
    <Head :title="t('admin.prayers.title')" />
    <main class="mx-auto max-w-6xl space-y-6 p-4 md:p-8">
        <header
            class="flex flex-col justify-between gap-4 rounded-3xl bg-gradient-to-br from-emerald-950 to-teal-700 p-7 text-white shadow-xl md:flex-row md:items-end"
        >
            <div>
                <p
                    class="text-xs font-bold tracking-[0.2em] text-emerald-200 uppercase"
                >
                    {{ t('admin.prayers.kicker') }}
                </p>
                <h1 class="mt-2 text-3xl font-black">
                    {{ t('admin.prayers.title') }}
                </h1>
                <p class="mt-2 text-sm text-emerald-100">
                    {{ t('admin.prayers.description') }}
                </p>
            </div>
            <button
                class="rounded-xl bg-white px-4 py-3 text-sm font-black text-emerald-900"
                @click="open = true"
            >
                {{ t('admin.prayers.new') }}
            </button>
        </header>
        <section class="grid gap-4 sm:grid-cols-2">
            <article
                v-for="(stat, index) in props.stats"
                :key="stat.label"
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <p
                    class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                >
                    {{ t(`admin.prayers.stats.${index}`) }}
                </p>
                <p class="mt-2 text-3xl font-black">{{ stat.value }}</p>
            </article>
        </section>
        <section class="space-y-3">
            <article
                v-for="request in requests"
                :key="request.id"
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <p class="text-sm leading-6 text-slate-700">
                    {{ request.content }}
                </p>
                <p class="mt-3 text-xs font-semibold text-slate-400">
                    {{
                        new Date(request.created_at).toLocaleString(
                            locale === 'pt' ? 'pt-BR' : 'en-US',
                        )
                    }}
                </p>
            </article>
            <div
                v-if="!requests.length"
                class="rounded-2xl border border-dashed p-12 text-center text-sm text-slate-500"
            >
                {{ t('admin.prayers.empty') }}
            </div>
        </section>
        <div
            v-if="open"
            class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4"
        >
            <form
                class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl"
                @submit.prevent="submit"
            >
                <h2 class="text-xl font-black">
                    {{ t('admin.prayers.new_title') }}
                </h2>
                <textarea
                    v-model="content"
                    required
                    rows="6"
                    class="w-full rounded-xl border-slate-300"
                    :placeholder="t('admin.prayers.placeholder')"
                />
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-slate-500"
                        @click="open = false"
                    >
                        {{ t('actions.cancel') }}</button
                    ><button
                        :disabled="sending"
                        class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white"
                    >
                        {{ t('admin.prayers.submit') }}
                    </button>
                </div>
            </form>
        </div>
    </main>
</template>
