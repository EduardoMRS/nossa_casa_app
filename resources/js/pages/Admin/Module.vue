<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type ModuleStat = {
    label: string;
    value: string | number;
};

type ModuleAction = {
    label: string;
    href: string;
};

type ModuleItem = {
    label: string;
    value: string;
};

const props = defineProps<{
    title: string;
    subtitle: string;
    description: string;
    stats: ModuleStat[];
    actions: ModuleAction[];
    items: ModuleItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Workspace admin',
                href: '/dashboard',
            },
        ],
    },
});
</script>

<template>
    <Head :title="props.title" />

    <div class="space-y-6 p-4 md:p-6">
        <section class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ props.subtitle }}</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">{{ props.title }}</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">{{ props.description }}</p>

            <div v-if="props.actions.length > 0" class="mt-5 flex flex-wrap gap-2">
                <Link
                    v-for="action in props.actions"
                    :key="`${action.label}-${action.href}`"
                    :href="action.href"
                    class="inline-flex items-center rounded-full bg-slate-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-800"
                >
                    {{ action.label }}
                </Link>
            </div>
        </section>

        <section v-if="props.stats.length > 0" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="stat in props.stats"
                :key="stat.label"
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ stat.label }}</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ stat.value }}</p>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <header class="border-b border-slate-200/80 px-5 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-700">Atividade recente</h2>
            </header>

            <div v-if="props.items.length > 0" class="divide-y divide-slate-100">
                <div
                    v-for="(item, index) in props.items"
                    :key="`${item.label}-${index}`"
                    class="flex flex-col gap-1 px-5 py-4 md:flex-row md:items-center md:justify-between"
                >
                    <p class="text-sm font-semibold text-slate-900">{{ item.label }}</p>
                    <p class="text-xs text-slate-500">{{ item.value }}</p>
                </div>
            </div>

            <div v-else class="px-5 py-8 text-center text-sm text-slate-500">
                Nenhum registro recente para este modulo no momento.
            </div>
        </section>
    </div>
</template>
