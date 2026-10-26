<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MapPin, Users } from '@lucide/vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';

type PublicGroup = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    categories: string[];
    responsibles: string[];
    location: string | null;
};

defineProps<{
    groups: PublicGroup[];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <Head :title="t('groups.title')" />
        <PublicHeader />
        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-10">
            <header class="mb-8">
                <h1 class="text-2xl font-black">{{ t('groups.title') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('groups.subtitle') }}
                </p>
            </header>

            <p
                v-if="!groups.length"
                class="rounded-2xl border border-border bg-card p-12 text-center text-sm text-muted-foreground"
            >
                {{ t('groups.empty') }}
            </p>

            <ul v-else class="grid gap-4 sm:grid-cols-2">
                <li
                    v-for="group in groups"
                    :key="group.id"
                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="category in group.categories"
                            :key="category"
                            class="rounded-full bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-700"
                        >
                            {{ category }}
                        </span>
                    </div>
                    <h2 class="mt-2 text-lg font-black">{{ group.name }}</h2>
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
                </li>
            </ul>
        </main>
        <PublicFooter />
    </div>
</template>
