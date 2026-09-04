<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Building2,
    ChevronRight,
    GitBranch,
    Network as NetworkIcon,
} from '@lucide/vue';
import { computed } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { usePublicTemplate } from '@/composables/usePublicTemplate';
import { useTerminology } from '@/composables/useTerminology';
import { useI18n } from '@/lib/i18n';
import type {
    ChurchNetworkOverviewData,
    ChurchNetworkTreeNode,
} from '@/types/church-network';

const props = defineProps<{
    network: ChurchNetworkOverviewData;
}>();

const { t } = useI18n();
const { unitLabel } = useTerminology();
const terms = computed(() => ({
    headquarters: unitLabel('headquarters'),
    headquartersPlural: unitLabel('headquarters', 'plural'),
    branch: unitLabel('branch'),
    branchPlural: unitLabel('branch', 'plural'),
}));
const branchLabel = (count: number): string =>
    unitLabel('branch', count === 1 ? 'singular' : 'plural');

type TreeRow = ChurchNetworkTreeNode & { depth: number };
const flattenTree = (
    node: ChurchNetworkTreeNode,
    depth = 0,
): TreeRow[] => [
    { ...node, depth },
    ...node.children.flatMap((child) => flattenTree(child, depth + 1)),
];

const publicTemplate = usePublicTemplate('network');
const treeRows = computed(() => flattenTree(props.network.tree));
</script>

<template>
    <Head
        :title="
            t('church_network_public.meta_title', {
                name: network.church.name,
            })
        "
    />

     <div
        class="public-template-page flex min-h-screen flex-col text-slate-950"
        :data-public-template="publicTemplate"
        :style="{
            backgroundColor: 'var(--church-surface, #f8fafc)',
            fontFamily: 'var(--church-font, Manrope, ui-sans-serif)',
        }"
    >
        <PublicHeader active="network" show-locale />

        <main
            class="mx-auto w-full max-w-6xl flex-1 space-y-6 px-3 py-6 sm:px-6 sm:py-9 lg:px-8"
        >
            <header
                class="overflow-hidden rounded-3xl p-6 text-white shadow-xl sm:p-9"
                :style="{
                    background: `linear-gradient(135deg, var(--church-primary, #312e81), var(--church-secondary, #6d28d9))`,
                }"
            >
                <div
                    class="flex size-12 items-center justify-center rounded-2xl border border-white/20 bg-white/10"
                >
                    <NetworkIcon class="size-6" />
                </div>
                <p
                    class="mt-6 font-mono text-xs font-black tracking-[0.18em] text-white/70 uppercase"
                >
                    {{
                        t('church_network_public.kicker', {
                            branchPlural: terms.branchPlural,
                        })
                    }}
                </p>
                <h1 class="mt-2 max-w-3xl text-3xl font-black sm:text-5xl">
                    {{
                        t('church_network_public.title', {
                            name: network.church.name,
                        })
                    }}
                </h1>
                <p class="mt-4 max-w-3xl text-sm text-white/80 sm:text-base">
                    {{
                        t('church_network_public.description', {
                            headquartersPlural: terms.headquartersPlural,
                            branchPlural: terms.branchPlural,
                        })
                    }}
                </p>
            </header>

            <section class="grid gap-3 sm:grid-cols-3">
                <article
                    v-for="stat in [
                        {
                            label: t('church_network_public.direct', {
                                branchPlural: terms.branchPlural,
                            }),
                            value: network.stats.direct_branches,
                        },
                        {
                            label: t('church_network_public.total', {
                                branchPlural: terms.branchPlural,
                            }),
                            value: network.stats.all_branches,
                        },
                        {
                            label: t('church_network_public.levels'),
                            value: network.stats.levels,
                        },
                    ]"
                    :key="stat.label"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <p
                        class="text-xs font-black tracking-[0.12em] text-slate-500 uppercase"
                    >
                        {{ stat.label }}
                    </p>
                    <p class="mt-2 text-3xl font-black">{{ stat.value }}</p>
                </article>
            </section>

            <section
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                        :style="{
                            backgroundColor:
                                'color-mix(in srgb, var(--church-primary) 12%, white)',
                            color: 'var(--church-primary)',
                        }"
                    >
                        <GitBranch class="size-5" />
                    </span>
                    <div>
                        <h2 class="font-black">
                            {{
                                t('church_network_public.parent_chain', {
                                    headquartersPlural:
                                        terms.headquartersPlural,
                                })
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{
                                t('church_network_public.parent_chain_hint', {
                                    headquarters: terms.headquarters,
                                })
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="network.ancestors.length"
                    class="mt-5 flex flex-wrap items-center gap-2"
                >
                    <template
                        v-for="ancestor in network.ancestors"
                        :key="ancestor.id"
                    >
                        <span
                            class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold"
                        >
                            {{ ancestor.name }}
                        </span>
                        <ChevronRight class="size-4 text-slate-400" />
                    </template>
                    <span
                        class="rounded-xl px-3 py-2 text-sm font-black text-white"
                        :style="{ backgroundColor: 'var(--church-primary)' }"
                    >
                        {{ network.church.name }}
                    </span>
                </div>
                <p
                    v-else
                    class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500"
                >
                    {{
                        t('church_network_public.no_parent', {
                            headquarters: terms.headquarters,
                        })
                    }}
                </p>
            </section>

            <section
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                        :style="{
                            backgroundColor:
                                'color-mix(in srgb, var(--church-primary) 12%, white)',
                            color: 'var(--church-primary)',
                        }"
                    >
                        <Building2 class="size-5" />
                    </span>
                    <div>
                        <h2 class="font-black">
                            {{
                                t('church_network_public.tree', {
                                    branchPlural: terms.branchPlural,
                                })
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{
                                t('church_network_public.tree_hint', {
                                    branchPlural: terms.branchPlural,
                                })
                            }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 space-y-2 overflow-hidden">
                    <article
                        v-for="row in treeRows"
                        :key="row.id"
                        class="flex min-w-0 items-center justify-between gap-3 rounded-xl border p-3.5"
                        :class="
                            row.id === network.church.id
                                ? ''
                                : 'border-slate-200 bg-slate-50'
                        "
                        :style="{
                            marginInlineStart: `${Math.min(row.depth, 5) * 0.75}rem`,
                            ...(row.id === network.church.id
                                ? {
                                      borderColor: 'var(--church-primary)',
                                      backgroundColor:
                                          'color-mix(in srgb, var(--church-primary) 7%, white)',
                                  }
                                : {}),
                        }"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                                :class="
                                    row.id === network.church.id
                                        ? 'text-white'
                                        : 'bg-white text-slate-500'
                                "
                                :style="
                                    row.id === network.church.id
                                        ? {
                                              backgroundColor:
                                                  'var(--church-primary)',
                                          }
                                        : undefined
                                "
                            >
                                <Building2 class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black">
                                    {{ row.name }}
                                </p>
                                <p
                                    v-if="row.id === network.church.id"
                                    class="text-xs font-semibold"
                                    :style="{ color: 'var(--church-primary)' }"
                                >
                                    {{ t('church_network_public.current') }}
                                </p>
                            </div>
                        </div>
                        <span
                            v-if="row.children.length"
                            class="shrink-0 rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-slate-500"
                        >
                            {{
                                t('church_network_public.branch_count', {
                                    count: row.children.length,
                                    unit: branchLabel(row.children.length),
                                })
                            }}
                        </span>
                    </article>
                </div>

                <p
                    v-if="network.tree.children.length === 0"
                    class="mt-4 rounded-xl border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500"
                >
                    {{
                        t('church_network_public.empty', {
                            branchPlural: terms.branchPlural,
                        })
                    }}
                </p>
            </section>
        </main>

        <PublicFooter show-locale />
    </div>
</template>
