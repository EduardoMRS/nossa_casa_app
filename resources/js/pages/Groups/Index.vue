<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, MapPin, MessageCircle, Navigation, Users } from '@lucide/vue';
import { ref } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';

type PublicGroup = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    social_link: string | null;
    chat_enabled: boolean;
    categories: string[];
    responsibles: string[];
    location: string | null;
    distance_km: number | null;
};

type CategorySection = { category: string; groups: PublicGroup[] };

defineProps<{
    categories: CategorySection[];
    nearest: PublicGroup | null;
    hasLocation: boolean;
}>();

const { t } = useI18n();
const locating = ref(false);

const findNearby = (): void => {
    if (!('geolocation' in navigator)) {
        return;
    }

    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        ({ coords }) => {
            router.get(
                window.location.pathname,
                { latitude: coords.latitude, longitude: coords.longitude },
                { preserveScroll: true, onFinish: () => (locating.value = false) },
            );
        },
        () => {
            locating.value = false;
        },
    );
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <Head :title="t('groups.title')" />
        <PublicHeader />
        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-10">
            <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black">{{ t('groups.title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('groups.subtitle') }}
                    </p>
                </div>
                <button
                    type="button"
                    :disabled="locating"
                    class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs font-bold text-foreground hover:bg-muted disabled:opacity-50"
                    @click="findNearby"
                >
                    <Navigation class="size-4" />
                    {{ locating ? t('groups.locating') : t('groups.find_nearby') }}
                </button>
            </header>

            <section
                v-if="nearest"
                class="mb-8 rounded-2xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-900 dark:bg-indigo-950/40"
            >
                <p
                    class="text-[10px] font-bold tracking-wider text-indigo-600 uppercase dark:text-indigo-300"
                >
                    {{ t('groups.nearest') }}
                </p>
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-black">{{ nearest.name }}</h2>
                    <span
                        v-if="nearest.distance_km !== null"
                        class="rounded-full bg-indigo-600 px-2.5 py-1 text-[11px] font-bold text-white"
                    >
                        {{ t('groups.away', { distance: nearest.distance_km }) }}
                    </span>
                </div>
                <p
                    v-if="nearest.location"
                    class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <MapPin class="size-4" />{{ nearest.location }}
                </p>
            </section>

            <p
                v-if="!categories.length"
                class="rounded-2xl border border-border bg-card p-12 text-center text-sm text-muted-foreground"
            >
                {{ t('groups.empty') }}
            </p>

            <div v-else class="space-y-10">
                <section
                    v-for="section in categories"
                    :key="section.category"
                >
                    <h2
                        class="mb-3 border-b border-border pb-2 text-sm font-black tracking-wide text-foreground uppercase"
                    >
                        {{ section.category }}
                    </h2>
                    <ul class="grid gap-4 sm:grid-cols-2">
                        <li
                            v-for="group in section.groups"
                            :key="group.id"
                            class="flex flex-col rounded-2xl border border-border bg-card p-5 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-lg font-black">
                                    {{ group.name }}
                                </h3>
                                <span
                                    v-if="group.distance_km !== null"
                                    class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[10px] font-bold text-muted-foreground"
                                >
                                    {{
                                        t('groups.distance_away', {
                                            distance: group.distance_km,
                                        })
                                    }}
                                </span>
                            </div>
                            <p
                                v-if="group.description"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{ group.description }}
                            </p>
                            <p
                                v-if="group.location"
                                class="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <MapPin class="size-4" />{{ group.location }}
                            </p>
                            <p
                                v-if="group.responsibles.length"
                                class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Users class="size-4" />{{ t('groups.leaders') }}:
                                {{ group.responsibles.join(', ') }}
                            </p>
                            <a
                                v-if="group.social_link"
                                :href="group.social_link"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 inline-flex items-center justify-center gap-2 self-start rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700"
                            >
                                <ExternalLink class="size-4" />{{
                                    t('groups.join_social')
                                }}
                            </a>
                            <Link
                                v-if="group.chat_enabled"
                                href="/chat"
                                class="mt-2 inline-flex items-center justify-center gap-2 self-start rounded-lg border border-border px-3 py-2 text-xs font-bold text-foreground hover:bg-muted"
                            >
                                <MessageCircle class="size-4" />{{
                                    t('chat.title')
                                }}
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>
        </main>
        <PublicFooter />
    </div>
</template>
