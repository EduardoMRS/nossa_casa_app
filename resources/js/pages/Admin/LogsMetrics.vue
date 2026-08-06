<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/lib/i18n';
type Activity = {
    id: string;
    content: string;
    created_at: string;
    user?: { first_name: string; last_name: string } | null;
};
const props = defineProps<{
    stats: Array<{ label: string; value: number; tone: string }>;
    activity: Activity[];
}>();
const { locale, t } = useI18n();
const statLabel = (index: number): string => t(`admin.logs.stats.${index}`);
const toneClass: Record<string, string> = {
    indigo: 'bg-indigo-50 text-indigo-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    amber: 'bg-amber-50 text-amber-700',
    rose: 'bg-rose-50 text-rose-700',
    sky: 'bg-sky-50 text-sky-700',
    violet: 'bg-violet-50 text-violet-700',
};
</script>
<template>
    <Head :title="t('admin.logs.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <header>
            <p
                class="text-xs font-bold tracking-[0.2em] text-indigo-600 uppercase"
            >
                {{ t('admin.logs.kicker') }}
            </p>
            <h1 class="mt-2 text-3xl font-black text-slate-950">
                {{ t('admin.logs.title') }}
            </h1>
            <p class="mt-2 text-sm text-slate-500">
                {{ t('admin.logs.description') }}
            </p>
        </header>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="(stat, index) in props.stats"
                :key="stat.label"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div
                    :class="toneClass[stat.tone]"
                    class="grid h-10 w-10 place-items-center rounded-xl text-lg font-black"
                >
                    #
                </div>
                <p
                    class="mt-5 text-xs font-bold tracking-wider text-slate-400 uppercase"
                >
                    {{ statLabel(index) }}
                </p>
                <p class="mt-1 text-3xl font-black text-slate-950">
                    {{ stat.value }}
                </p>
            </article>
        </section>
        <section class="rounded-3xl border bg-white shadow-sm">
            <header class="border-b px-6 py-4">
                <h2 class="font-black">
                    {{ t('admin.common.recent_activity') }}
                </h2>
            </header>
            <div class="divide-y">
                <article
                    v-for="item in props.activity"
                    :key="item.id"
                    class="flex gap-3 px-6 py-4"
                >
                    <div class="mt-1 h-2 w-2 rounded-full bg-indigo-500" />
                    <div>
                        <p class="text-sm text-slate-700">{{ item.content }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{
                                item.user
                                    ? `${item.user.first_name} ${item.user.last_name}`
                                    : t('admin.logs.system')
                            }}
                            ·
                            {{
                                new Date(item.created_at).toLocaleString(
                                    locale === 'pt' ? 'pt-BR' : 'en-US',
                                )
                            }}
                        </p>
                    </div>
                </article>
                <p
                    v-if="!props.activity.length"
                    class="p-10 text-center text-sm text-slate-500"
                >
                    {{ t('admin.logs.empty') }}
                </p>
            </div>
        </section>
    </main>
</template>
