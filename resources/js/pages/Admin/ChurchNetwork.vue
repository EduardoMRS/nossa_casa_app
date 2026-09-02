<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2,
    ChevronRight,
    GitBranch,
    Network,
    Settings2,
} from '@lucide/vue';
import { computed } from 'vue';
import AdminPageHeader from '@/components/AdminPageHeader.vue';
import { useI18n } from '@/lib/i18n';
import { edit as brandingEdit } from '@/routes/admin/branding';
import type {
    ChurchNetworkSettingsData,
    ChurchNetworkTreeNode,
} from '@/types/church-network';

const props = defineProps<{
    network: ChurchNetworkSettingsData;
}>();

const { t } = useI18n();

type TreeRow = ChurchNetworkTreeNode & { depth: number };

const flattenTree = (
    node: ChurchNetworkTreeNode,
    depth = 0,
): TreeRow[] => [
    { ...node, depth },
    ...node.children.flatMap((child) => flattenTree(child, depth + 1)),
];

const treeRows = computed(() => flattenTree(props.network.tree));
const settingsUrl = computed(
    () => `${brandingEdit().url}#settings-network`,
);
</script>

<template>
    <Head :title="t('admin.church_network.title')" />

    <main class="space-y-6">
        <AdminPageHeader
            :kicker="t('admin.church_network.kicker')"
            :title="t('admin.church_network.title')"
            :description="t('admin.church_network.description')"
        >
            <Link
                :href="settingsUrl"
                class="inline-flex items-center gap-2 rounded-xl bg-primary-foreground px-4 py-3 text-sm font-black text-primary shadow-sm transition hover:opacity-90"
            >
                <Settings2 class="size-4" />
                {{ t('admin.church_network.manage') }}
            </Link>
        </AdminPageHeader>

        <section class="grid gap-4 sm:grid-cols-3">
            <article
                v-for="stat in [
                    {
                        key: 'direct_branches',
                        value: network.stats.direct_branches,
                    },
                    {
                        key: 'all_branches',
                        value: network.stats.all_branches,
                    },
                    { key: 'levels', value: network.stats.levels },
                ]"
                :key="stat.key"
                class="rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm"
            >
                <p
                    class="text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase"
                >
                    {{ t(`admin.church_network.${stat.key}`) }}
                </p>
                <p class="mt-2 text-3xl font-black">{{ stat.value }}</p>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-[1.15fr_0.85fr]">
            <article
                class="rounded-2xl border border-border bg-card p-6 text-card-foreground shadow-sm"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="rounded-xl bg-primary/10 p-2.5 text-primary"
                    >
                        <GitBranch class="size-5" />
                    </div>
                    <div>
                        <h2 class="font-black">
                            {{ t('admin.church_network.parent_chain') }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                t(
                                    'admin.church_network.parent_chain_description',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="network.ancestors.length"
                    class="mt-6 flex flex-wrap items-center gap-2"
                >
                    <template
                        v-for="ancestor in network.ancestors"
                        :key="ancestor.id"
                    >
                        <span
                            class="rounded-xl border border-border bg-muted/50 px-3 py-2 text-sm font-bold"
                        >
                            {{ ancestor.name }}
                        </span>
                        <ChevronRight
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                    </template>
                    <span
                        class="rounded-xl bg-primary px-3 py-2 text-sm font-black text-primary-foreground"
                    >
                        {{ network.church.name }}
                    </span>
                </div>
                <p
                    v-else
                    class="mt-6 rounded-xl border border-dashed border-border bg-muted/30 p-4 text-sm text-muted-foreground"
                >
                    {{ t('admin.church_network.no_parent') }}
                </p>
            </article>

            <article
                class="rounded-2xl border border-primary/25 bg-primary/5 p-6 text-card-foreground shadow-sm"
            >
                <div
                    class="flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground"
                >
                    <Building2 class="size-5" />
                </div>
                <p
                    class="mt-5 text-xs font-bold tracking-[0.14em] text-primary uppercase"
                >
                    {{ t('admin.church_network.current') }}
                </p>
                <h2 class="mt-2 text-2xl font-black">
                    {{ network.church.name }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{
                        t('admin.church_network.branch_count', {
                            count: network.children.length,
                        })
                    }}
                </p>
            </article>
        </section>

        <section
            class="rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm sm:p-6"
        >
            <div class="flex items-start gap-3">
                <div class="rounded-xl bg-primary/10 p-2.5 text-primary">
                    <Network class="size-5" />
                </div>
                <div>
                    <h2 class="font-black">
                        {{ t('admin.church_network.hierarchy') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('admin.church_network.hierarchy_description') }}
                    </p>
                </div>
            </div>

            <div class="mt-6 space-y-2 overflow-hidden">
                <article
                    v-for="row in treeRows"
                    :key="row.id"
                    class="relative flex min-w-0 items-center justify-between gap-3 rounded-xl border p-3.5"
                    :class="
                        row.id === network.church.id
                            ? 'border-primary/35 bg-primary/10'
                            : 'border-border bg-muted/35'
                    "
                    :style="{
                        marginInlineStart: `${Math.min(row.depth, 5) * 0.75}rem`,
                    }"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                            :class="
                                row.id === network.church.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-background text-muted-foreground'
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
                                class="text-xs text-primary"
                            >
                                {{ t('admin.church_network.current') }}
                            </p>
                        </div>
                    </div>
                    <span
                        v-if="row.children.length"
                        class="shrink-0 rounded-full bg-background px-2.5 py-1 text-[10px] font-black text-muted-foreground"
                    >
                        {{
                            t('admin.church_network.branch_count', {
                                count: row.children.length,
                            })
                        }}
                    </span>
                </article>
            </div>

            <p
                v-if="network.tree.children.length === 0"
                class="mt-4 rounded-xl border border-dashed border-border p-5 text-center text-sm text-muted-foreground"
            >
                {{ t('admin.church_network.empty') }}
            </p>
        </section>
    </main>
</template>
