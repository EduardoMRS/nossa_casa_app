<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    Activity,
    Archive,
    CircleAlert,
    Cpu,
    Database,
    Download,
    LoaderCircle,
    ListChecks,
    Radio,
    Server,
    ShieldCheck,
    Square,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useI18n } from '@/lib/i18n';
import {
    exportMethod as exportBackupRoute,
    importMethod as importBackupRoute,
} from '@/routes/admin/logsMetrics/backup';
import { stop } from '@/routes/admin/logsMetrics/liveStreams';
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
    liveStreams: Array<{
        id: string;
        name: string;
        path: string;
        worker_id: string | null;
        source_type: string | null;
        started_at: string | null;
        recordings_count: number;
        playback_url: string;
    }>;
    maintenance: boolean;
}>();
type LiveStreamMetric = (typeof props.liveStreams)[number];
type MetricsSnapshot = {
    stats: typeof props.stats;
    queue: typeof props.queue;
    logs: string[];
    liveStreams: LiveStreamMetric[];
};

const { t } = useI18n();
const { confirm } = useConfirmDialog();
const stats = ref([...props.stats]);
const queue = ref({ ...props.queue });
const logs = ref([...props.logs]);
const liveStreams = ref([...props.liveStreams]);
const query = ref('');
const operation = ref<'export' | 'import' | null>(null);
const selectedBackup = ref<File | null>(null);
const backupInput = ref<HTMLInputElement | null>(null);
const errors = ref<Record<string, string>>({});
const filteredLogs = computed(() =>
    logs.value
        .filter((line) =>
            line.toLowerCase().includes(query.value.toLowerCase()),
        )
        .reverse(),
);

useEcho<MetricsSnapshot>(
    'system.metrics',
    '.system.metrics.updated',
    (snapshot) => {
        stats.value = snapshot.stats;
        queue.value = snapshot.queue;
        logs.value = snapshot.logs;
        liveStreams.value = snapshot.liveStreams;
    },
);

const chooseBackup = (event: Event): void => {
    selectedBackup.value =
        (event.target as HTMLInputElement).files?.[0] ?? null;
    errors.value = {};
};

const exportBackup = async (): Promise<void> => {
    if (operation.value) {
        return;
    }

    operation.value = 'export';
    errors.value = {};

    try {
        const response = await fetch(exportBackupRoute.url(), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/zip, application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            const body = await response.json().catch(() => null);

            throw new Error(body?.message ?? t('admin.logs.backup.error'));
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `nossa-casa-backup-${new Date().toISOString().replace(/[:.]/g, '-')}.zip`;
        link.click();
        URL.revokeObjectURL(url);
    } catch (error) {
        errors.value = {
            backup:
                error instanceof Error
                    ? error.message
                    : t('admin.logs.backup.error'),
        };
    } finally {
        operation.value = null;
    }
};

const importBackup = async (): Promise<void> => {
    if (operation.value || !selectedBackup.value) {
        errors.value = { backup: t('admin.logs.backup.choose_file') };

        return;
    }

    if (
        !(await confirm({
            message: t('admin.logs.backup.import_confirm'),
            intent: 'danger',
        }))
    ) {
        return;
    }

    operation.value = 'import';
    errors.value = {};

    router.post(
        importBackupRoute.url(),
        { backup: selectedBackup.value },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (value: Record<string, string | string[]>) => {
                errors.value = Object.fromEntries(
                    Object.entries(value).map(([key, message]) => [
                        key,
                        Array.isArray(message) ? message[0] : message,
                    ]),
                );
            },
            onSuccess: () => {
                selectedBackup.value = null;

                if (backupInput.value) {
                    backupInput.value.value = '';
                }
            },
            onFinish: () => {
                operation.value = null;
            },
        },
    );
};
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
                {{ t('admin.logs.system') }}
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
                    {{ t('admin.logs.runtime') }}
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
            class="overflow-hidden rounded-2xl border border-amber-200 bg-amber-50 shadow-sm"
        >
            <header
                class="flex items-start gap-3 border-b border-amber-200 p-5"
            >
                <ShieldCheck class="mt-0.5 size-5 shrink-0 text-amber-700" />
                <div>
                    <h2 class="font-black text-amber-950">
                        {{ t('admin.logs.backup.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-amber-900">
                        {{ t('admin.logs.backup.description') }}
                    </p>
                </div>
            </header>
            <div class="grid gap-4 p-5 lg:grid-cols-2">
                <article
                    class="rounded-xl border border-amber-200 bg-white p-4"
                >
                    <div class="flex items-center gap-3">
                        <Download class="size-5 text-indigo-600" />
                        <div>
                            <h3 class="font-black text-slate-900">
                                {{ t('admin.logs.backup.export_title') }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ t('admin.logs.backup.export_description') }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        :disabled="operation !== null"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-black text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="exportBackup"
                    >
                        <LoaderCircle
                            v-if="operation === 'export'"
                            class="size-4 animate-spin"
                        />
                        <Archive v-else class="size-4" />
                        {{
                            operation === 'export'
                                ? t('admin.logs.backup.processing')
                                : t('admin.logs.backup.export_action')
                        }}
                    </button>
                </article>
                <article
                    class="rounded-xl border border-amber-200 bg-white p-4"
                >
                    <div class="flex items-center gap-3">
                        <Upload class="size-5 text-emerald-600" />
                        <div>
                            <h3 class="font-black text-slate-900">
                                {{ t('admin.logs.backup.import_title') }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ t('admin.logs.backup.import_description') }}
                            </p>
                        </div>
                    </div>
                    <input
                        ref="backupInput"
                        type="file"
                        accept=".zip,application/zip"
                        class="mt-4 block w-full rounded-lg border border-slate-300 p-2 text-sm"
                        :disabled="operation !== null"
                        @change="chooseBackup"
                    />
                    <button
                        type="button"
                        :disabled="operation !== null || !selectedBackup"
                        class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-black text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="importBackup"
                    >
                        <LoaderCircle
                            v-if="operation === 'import'"
                            class="size-4 animate-spin"
                        />
                        <Upload v-else class="size-4" />
                        {{
                            operation === 'import'
                                ? t('admin.logs.backup.processing')
                                : t('admin.logs.backup.import_action')
                        }}
                    </button>
                </article>
            </div>
            <p
                v-if="errors.backup"
                class="border-t border-rose-200 bg-rose-50 px-5 py-3 text-sm font-bold text-rose-700"
            >
                {{ errors.backup }}
            </p>
        </section>
        <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
            <header
                class="flex items-center justify-between gap-4 border-b p-5"
            >
                <div>
                    <h2 class="flex items-center gap-2 text-lg font-black">
                        <Radio class="size-5 text-rose-600" />
                        {{ t('admin.logs.live_streams') }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ t('admin.logs.live_streams_description') }}
                    </p>
                </div>
                <span
                    class="rounded-full bg-rose-100 px-3 py-1 text-sm font-black text-rose-700"
                >
                    {{ liveStreams.length }}
                </span>
            </header>
            <div v-if="liveStreams.length" class="divide-y">
                <article
                    v-for="liveStream in liveStreams"
                    :key="liveStream.id"
                    class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span
                                class="size-2 animate-pulse rounded-full bg-rose-500"
                            />
                            <p class="truncate font-black">
                                {{ liveStream.name }}
                            </p>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ liveStream.path }} ·
                            {{
                                liveStream.worker_id ??
                                t('admin.logs.unknown_worker')
                            }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ liveStream.recordings_count }}
                            {{ t('admin.logs.recordings') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            :href="liveStream.playback_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-lg border px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"
                        >
                            {{ t('admin.logs.open_stream') }}
                        </a>
                        <Link
                            :href="stop(liveStream).url"
                            method="post"
                            as="button"
                            preserve-scroll
                            class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-3 py-2 text-sm font-bold text-white hover:bg-rose-700"
                        >
                            <Square class="size-3 fill-current" />
                            {{ t('admin.logs.stop_stream') }}
                        </Link>
                    </div>
                </article>
            </div>
            <p v-else class="p-8 text-center text-sm text-slate-500">
                {{ t('admin.logs.no_live_streams') }}
            </p>
        </section>
        <section
            class="overflow-hidden rounded-2xl border bg-slate-950 text-slate-200 shadow-sm"
        >
            <header
                class="flex flex-col gap-3 border-b border-slate-800 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2 class="flex items-center gap-2 font-black">
                    <Activity class="size-4 text-emerald-400" />
                    <span>{{ t('admin.logs.log_file') }}</span>
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
