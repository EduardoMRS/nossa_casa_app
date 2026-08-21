<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CalendarDays,
    Church as ChurchIcon,
    LocateFixed,
    MapPin,
    Network,
    Radio,
    Users,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import CommunityTreeNode from '@/components/CommunityTreeNode.vue';
import type { CommunityTreeNodeData } from '@/components/CommunityTreeNode.vue';
import PortalHeader from '@/components/PortalHeader.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import { useI18n } from '@/lib/i18n';
import { show as communityShow } from '@/routes/communities';

type Church = {
    id: string;
    name: string;
    domain: string | null;
    url: string | null;
    unit_label: string;
    is_live: boolean;
    members_count: number;
    upcoming_events_count: number;
    distance_km: number | null;
    city: string | null;
    state: string | null;
    address: string;
};

const props = defineProps<{
    community: {
        id: string;
        name: string;
        slug: string;
        description: string | null;
        found_date: string | null;
        logo_url: string | null;
    };
    stats: {
        churches: number;
        members: number;
        upcoming_events: number;
    };
    churches: Church[];
    tree: CommunityTreeNodeData[];
    locationApplied: boolean;
    userChurchUrl?: string | null;
}>();

const { locale, t } = useI18n();
const locating = ref(false);
const formatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
            year: 'numeric',
            month: 'long',
            day: '2-digit',
        }),
);
const formattedFoundDate = computed(() =>
    props.community.found_date
        ? formatter.value.format(new Date(props.community.found_date))
        : null,
);

const requestLocalPriority = (latitude: number, longitude: number): void => {
    router.get(
        communityShow(props.community).url,
        { latitude, longitude },
        {
            only: ['churches', 'tree', 'locationApplied'],
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => (locating.value = false),
        },
    );
};

onMounted(() => {
    if (props.locationApplied || !('geolocation' in navigator)) {
        return;
    }

    const storedLocation = window.localStorage.getItem('ncapp_portal_location');

    if (storedLocation) {
        try {
            const parsed = JSON.parse(storedLocation) as {
                latitude: number;
                longitude: number;
            };

            if (
                Number.isFinite(parsed.latitude) &&
                Number.isFinite(parsed.longitude)
            ) {
                locating.value = true;
                requestLocalPriority(parsed.latitude, parsed.longitude);

                return;
            }
        } catch {
            window.localStorage.removeItem('ncapp_portal_location');
        }
    }

    if (window.sessionStorage.getItem('ncapp_location_requested')) {
        return;
    }

    locating.value = true;
    window.sessionStorage.setItem('ncapp_location_requested', '1');
    navigator.geolocation.getCurrentPosition(
        ({ coords }) => {
            const location = {
                latitude: coords.latitude,
                longitude: coords.longitude,
            };
            window.localStorage.setItem(
                'ncapp_portal_location',
                JSON.stringify(location),
            );
            requestLocalPriority(location.latitude, location.longitude);
        },
        () => (locating.value = false),
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 3600000 },
    );
});
</script>

<template>
    <Head :title="t('portal.community.meta_title', { name: community.name })" />
    <div class="min-h-screen bg-[#f7f8fc] text-slate-950">
        <PortalHeader :user-church-url="userChurchUrl" />

        <main>
            <section
                class="bg-gradient-to-br from-indigo-950 via-indigo-900 to-violet-800 text-white"
            >
                <div
                    class="mx-auto grid max-w-7xl gap-10 px-5 py-16 lg:grid-cols-[1fr_auto] lg:items-end lg:px-8 lg:py-20"
                >
                    <div>
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-black text-indigo-100"
                        >
                            <Network class="size-4" />
                            {{ t('portal.community.badge') }}
                        </span>
                        <div class="mt-6 flex items-center gap-4">
                            <img
                                v-if="community.logo_url"
                                :src="community.logo_url"
                                :alt="community.name"
                                class="size-20 rounded-2xl bg-white object-contain p-2"
                            />
                            <div>
                                <h1 class="text-4xl font-black sm:text-6xl">
                                    {{ community.name }}
                                </h1>
                                <p
                                    v-if="formattedFoundDate"
                                    class="mt-2 text-sm text-indigo-200"
                                >
                                    {{
                                        t('portal.community.founded', {
                                            date: formattedFoundDate,
                                        })
                                    }}
                                </p>
                            </div>
                        </div>
                        <p
                            v-if="community.description"
                            class="mt-6 max-w-3xl text-base leading-7 text-indigo-100"
                        >
                            {{ community.description }}
                        </p>
                    </div>
                    <span
                        v-if="locating"
                        class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-3 text-xs font-black"
                    >
                        <LocateFixed class="size-4 animate-pulse" />
                        {{ t('portal.nearby.locating') }}
                    </span>
                </div>
            </section>

            <section
                class="mx-auto grid max-w-7xl gap-4 px-5 py-10 sm:grid-cols-3 lg:px-8"
            >
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <ChurchIcon class="size-5 text-indigo-700" />
                    <strong class="mt-3 block text-3xl">{{
                        stats.churches
                    }}</strong>
                    <span class="text-xs text-slate-500">{{
                        t('portal.community.stats.churches')
                    }}</span>
                </article>
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <Users class="size-5 text-indigo-700" />
                    <strong class="mt-3 block text-3xl">{{
                        stats.members
                    }}</strong>
                    <span class="text-xs text-slate-500">{{
                        t('portal.community.stats.members')
                    }}</span>
                </article>
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <CalendarDays class="size-5 text-indigo-700" />
                    <strong class="mt-3 block text-3xl">{{
                        stats.upcoming_events
                    }}</strong>
                    <span class="text-xs text-slate-500">{{
                        t('portal.community.stats.upcoming_events')
                    }}</span>
                </article>
            </section>

            <section class="mx-auto max-w-7xl px-5 pb-14 lg:px-8">
                <div>
                    <p
                        class="font-mono text-xs font-black tracking-[0.18em] text-indigo-600 uppercase"
                    >
                        {{ t('portal.community.churches_kicker') }}
                    </p>
                    <h2 class="mt-2 text-3xl font-black">
                        {{ t('portal.community.churches_title') }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-500">
                        {{
                            locationApplied
                                ? t('portal.community.local_priority')
                                : t('portal.community.churches_description')
                        }}
                    </p>
                </div>

                <div class="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="church in churches"
                        :key="church.id"
                        class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span
                                    class="text-[10px] font-black tracking-wide text-indigo-600 uppercase"
                                    >{{ church.unit_label }}</span
                                >
                                <h3 class="mt-1 text-lg font-black">
                                    {{ church.name }}
                                </h3>
                            </div>
                            <span
                                v-if="church.is_live"
                                class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-1 text-[9px] font-black text-rose-700 uppercase"
                            >
                                <Radio class="size-3" />
                                {{ t('portal.communities.live') }}
                            </span>
                        </div>
                        <p
                            v-if="church.address"
                            class="mt-3 flex items-start gap-2 text-xs leading-5 text-slate-500"
                        >
                            <MapPin class="mt-0.5 size-3.5 shrink-0" />
                            {{ church.address }}
                        </p>
                        <div
                            class="mt-4 flex flex-wrap gap-2 text-[10px] font-black text-slate-600"
                        >
                            <span class="rounded-full bg-slate-100 px-2 py-1">
                                {{
                                    t('portal.community.members_count', {
                                        count: church.members_count,
                                    })
                                }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-2 py-1">
                                {{
                                    t('portal.community.events_count', {
                                        count: church.upcoming_events_count,
                                    })
                                }}
                            </span>
                            <span
                                v-if="church.distance_km != null"
                                class="rounded-full bg-sky-50 px-2 py-1 text-sky-700"
                            >
                                {{ church.distance_km }} km
                            </span>
                        </div>
                        <a
                            v-if="church.url"
                            :href="church.url"
                            class="mt-5 inline-flex items-center gap-2 self-end text-xs font-black text-indigo-700"
                        >
                            {{
                                t('portal.community.open_church', {
                                    name: church.name,
                                })
                            }}
                            <ArrowUpRight class="size-4" />
                        </a>
                    </article>
                </div>
            </section>

            <section class="border-t border-indigo-100 bg-indigo-50/60">
                <div class="mx-auto max-w-5xl px-5 py-14 lg:px-8">
                    <p
                        class="font-mono text-xs font-black tracking-[0.18em] text-indigo-600 uppercase"
                    >
                        {{ t('portal.community.tree_kicker') }}
                    </p>
                    <h2 class="mt-2 text-3xl font-black">
                        {{ t('portal.community.tree_title') }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ t('portal.community.tree_description') }}
                    </p>
                    <ul class="mt-8 grid gap-4">
                        <CommunityTreeNode
                            v-for="node in tree"
                            :key="node.id"
                            :node="node"
                        />
                    </ul>
                </div>
            </section>
        </main>

        <PublicFooter show-locale />
    </div>
</template>
