<script setup lang="ts">
import { ArrowUpRight, Building2, MapPin, Radio } from '@lucide/vue';
import { useI18n } from '@/lib/i18n';

export type CommunityTreeNodeData = {
    id: string;
    name: string;
    url: string | null;
    unit_label: string;
    is_live: boolean;
    distance_km: number | null;
    city: string | null;
    state: string | null;
    children: CommunityTreeNodeData[];
};

defineProps<{ node: CommunityTreeNodeData }>();
const { t } = useI18n();
</script>

<template>
    <li class="relative">
        <article
            class="relative z-10 flex flex-col gap-3 rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex min-w-0 items-start gap-3">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-700"
                >
                    <Building2 class="size-5" />
                </span>
                <span class="min-w-0">
                    <span
                        class="text-[10px] font-black tracking-wide text-indigo-600 uppercase"
                        >{{ node.unit_label }}</span
                    >
                    <strong class="block truncate text-sm text-slate-950">{{
                        node.name
                    }}</strong>
                    <small
                        v-if="node.city || node.state"
                        class="mt-1 flex items-center gap-1 text-slate-500"
                    >
                        <MapPin class="size-3" />
                        {{
                            [node.city, node.state].filter(Boolean).join(' · ')
                        }}
                    </small>
                </span>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <span
                    v-if="node.is_live"
                    class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-1 text-[9px] font-black text-rose-700 uppercase"
                >
                    <Radio class="size-3" />
                    {{ t('portal.communities.live') }}
                </span>
                <span
                    v-if="node.distance_km != null"
                    class="rounded-full bg-sky-50 px-2 py-1 text-[10px] font-black text-sky-700"
                >
                    {{ node.distance_km }} km
                </span>
                <a
                    v-if="node.url"
                    :href="node.url"
                    :aria-label="
                        t('portal.community.open_church', { name: node.name })
                    "
                    class="grid size-8 place-items-center rounded-lg bg-indigo-700 text-white"
                >
                    <ArrowUpRight class="size-4" />
                </a>
            </div>
        </article>

        <ul
            v-if="node.children.length"
            class="relative mt-3 ml-5 grid gap-3 border-l-2 border-indigo-100 pl-5 sm:ml-8 sm:pl-8"
        >
            <CommunityTreeNode
                v-for="child in node.children"
                :key="`${node.id}-${child.id}`"
                :node="child"
            />
        </ul>
    </li>
</template>
