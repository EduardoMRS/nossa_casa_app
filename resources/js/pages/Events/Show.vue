<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { usePublicTemplate } from '@/composables/usePublicTemplate';
import { useI18n } from '@/lib/i18n';
import {
    index as eventsIndex,
    register as registerEvent,
} from '@/routes/events';

type EventDetail = {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    description_html: string;
    start_time: string;
    end_time: string;
    cover_path: string | null;
    church?: {
        id: string;
        name: string;
        slug: string;
    } | null;
    categories?: Array<{ id: string; name: string }>;
};

const props = defineProps<{
    event: EventDetail;
    registration: {
        has_form: boolean;
        form_id: string | null;
        form_title: string | null;
        already_registered: boolean;
    };
}>();

const { locale, t } = useI18n();
const publicTemplate = usePublicTemplate('events_show');

const formatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }),
);

const dateRange = computed(() => {
    return `${formatter.value.format(new Date(props.event.start_time))} - ${formatter.value.format(new Date(props.event.end_time))}`;
});

const coverStyle = computed(() => {
    if (props.event.cover_path) {
        return `background-image: linear-gradient(180deg, rgba(6, 20, 34, 0.18), rgba(6, 20, 34, 0.74)), url('${props.event.cover_path}'); background-size: cover; background-position: center;`;
    }

    return 'background-image: linear-gradient(140deg, #0b3d44 0%, #0f5f68 45%, #0f7a69 100%);';
});
</script>

<template>
    <Head :title="event.title" />

    <div
        class="public-template-page flex min-h-screen flex-col bg-[#f8fafc] text-slate-950"
        :data-public-template="publicTemplate"
    >
        <PublicHeader active="events" />

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
            <section
                class="relative overflow-hidden rounded-3xl p-7 text-white md:p-10"
                :style="coverStyle"
            >
                <p
                    class="mb-2 text-xs tracking-[0.2em] text-[#b9ece6] uppercase"
                >
                    {{ event.church?.name ?? t('events.shared.community') }}
                </p>
                <h1
                    class="max-w-4xl [font-family:Manrope,ui-sans-serif] text-3xl font-black md:text-5xl"
                >
                    {{ event.title }}
                </h1>
                <p class="mt-3 max-w-3xl text-sm text-[#d8f6ef] md:text-base">
                    {{ dateRange }}
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <Link
                        v-if="registration.has_form"
                        :href="registerEvent({ event: event.slug })"
                        class="rounded-full bg-white px-5 py-2.5 text-sm font-extrabold text-[#0b3d44]"
                    >
                        {{
                            registration.already_registered
                                ? t('events.show.manage_registration')
                                : t('events.show.register')
                        }}
                    </Link>
                    <span
                        v-else
                        class="rounded-full border border-white/35 bg-white/10 px-5 py-2.5 text-sm font-semibold text-white"
                    >
                        {{ t('events.show.registrations_unavailable') }}
                    </span>
                    <Link
                        :href="eventsIndex()"
                        class="rounded-full border border-white/35 px-5 py-2.5 text-sm font-semibold text-white"
                    >
                        {{ t('events.show.back_to_events') }}
                    </Link>
                </div>
            </section>

            <section class="mt-7 grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
                <article
                    class="rounded-3xl border border-[#d8e2ec] bg-white p-6 md:p-8"
                >
                    <h2
                        class="mb-4 [font-family:Manrope,ui-sans-serif] text-xl font-black"
                    >
                        {{ t('events.show.about') }}
                    </h2>
                    <div
                        class="prose prose-slate max-w-none text-[#23384d]"
                        v-html="event.description_html"
                    />
                </article>

                <aside class="space-y-4">
                    <section
                        class="rounded-2xl border border-[#d8e2ec] bg-white p-5"
                    >
                        <p
                            class="text-xs tracking-[0.14em] text-[#5b7388] uppercase"
                        >
                            {{ t('events.show.registration') }}
                        </p>
                        <p class="mt-2 text-sm text-[#3f566c]">
                            {{
                                registration.form_title ??
                                t('events.show.form_not_configured')
                            }}
                        </p>
                    </section>

                    <section
                        v-if="event.categories?.length"
                        class="rounded-2xl border border-[#d8e2ec] bg-white p-5"
                    >
                        <p
                            class="text-xs tracking-[0.14em] text-[#5b7388] uppercase"
                        >
                            {{ t('events.show.categories') }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="category in event.categories"
                                :key="category.id"
                                class="rounded-full border border-[#cfe0ea] bg-[#f4f9fc] px-3 py-1 text-xs font-semibold text-[#395672]"
                            >
                                {{ category.name }}
                            </span>
                        </div>
                    </section>
                </aside>
            </section>
        </main>
        <PublicFooter show-locale />
    </div>
</template>
