<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    CircleAlert,
    Cpu,
    Database,
    ListChecks,
    Server,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from '@/lib/i18n';
const props = defineProps<{
    stats: Array<{ label: string; value: number; tone: string }>;
    queue: { pending: number; failed: number };
    system: {
        environment: string;
        laravel: string;
        php: string;
        queue_connection: string;
    };
    logs: string[];
}>();
const { t } = useI18n();
const query = ref('');
const filteredLogs = computed(() =>
    props.logs
        .filter((line) =>
            line.toLowerCase().includes(query.value.toLowerCase()),
        )
        .reverse(),
);
</script>
<template>
    <Head :title="t('admin.logs.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white shadow-sm"
        >
            <p
                class="text-xs font-bold tracking-[0.2em] text-slate-300 uppercase"
            >
                SYSTEM
            </p>
            <h1 class="mt-2 text-3xl font-black">
                {{ t('admin.logs.title') }}
            </h1>
            <p class="mt-2 text-sm text-slate-300">
                {{ t('admin.logs.description') }}
            </p>
        </header>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border bg-white p-5 shadow-sm">
                <Server class="size-5 text-indigo-600" />
                <p class="mt-4 text-xs font-bold text-slate-400 uppercase">
                    {{ t('admin.logs.environment') }}
                </p>
                <p class="mt-1 text-xl font-black">{{ system.environment }}</p>
            </article>
            <article class="rounded-2xl border bg-white p-5 shadow-sm">
                <Cpu class="size-5 text-cyan-600" />
                <p class="mt-4 text-xs font-bold text-slate-400 uppercase">
                    Laravel / PHP
                </p>
                <p class="mt-1 text-xl font-black">
                    {{ system.laravel }} / {{ system.php }}
                </p>
            </article>
            <article class="rounded-2xl border bg-white p-5 shadow-sm">
                <ListChecks class="size-5 text-amber-600" />
                <p class="mt-4 text-xs font-bold text-slate-400 uppercase">
                    {{ t('admin.logs.pending_jobs') }}
                </p>
                <p class="mt-1 text-3xl font-black">{{ queue.pending }}</p>
                <p class="text-xs text-slate-400">
                    {{ system.queue_connection }}
                </p>
            </article>
            <article class="rounded-2xl border bg-white p-5 shadow-sm">
                <CircleAlert class="size-5 text-rose-600" />
                <p class="mt-4 text-xs font-bold text-slate-400 uppercase">
                    {{ t('admin.logs.failed_jobs') }}
                </p>
                <p class="mt-1 text-3xl font-black">{{ queue.failed }}</p>
            </article>
        </section>
        <section class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
            <article
                v-for="(stat, index) in stats"
                :key="stat.label"
                class="rounded-xl border bg-white p-4"
            >
                <Database class="size-4 text-slate-400" />
                <p class="mt-3 text-xs font-bold text-slate-400 uppercase">
                    {{ t(`admin.logs.stats.${index}`) }}
                </p>
                <p class="text-2xl font-black">{{ stat.value }}</p>
            </article>
        </section>
        <section
            class="overflow-hidden rounded-2xl border bg-slate-950 text-slate-200 shadow-sm"
        >
            <header
                class="flex flex-col gap-3 border-b border-slate-800 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2 class="flex items-center gap-2 font-black">
                    <Activity class="size-4 text-emerald-400" />laravel.log
                </h2>
                <input
                    v-model="query"
                    class="rounded-lg border-slate-700 bg-slate-900 text-sm text-white"
                    :placeholder="t('admin.logs.search')"
                />
            </header>
            <div
                class="max-h-[32rem] overflow-auto p-4 font-mono text-xs leading-6"
            >
                <p
                    v-for="(line, index) in filteredLogs"
                    :key="index"
                    class="border-b border-slate-900 py-1 break-all"
                >
                    {{ line }}
                </p>
                <p
                    v-if="!filteredLogs.length"
                    class="py-10 text-center text-slate-500"
                >
                    {{ t('admin.logs.empty') }}
                </p>
            </div>
        </section>
    </main>
</template>
