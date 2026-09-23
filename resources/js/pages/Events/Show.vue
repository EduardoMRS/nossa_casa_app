<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Edit, Share2 } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { destroy as destroyEvent } from '@/actions/App/Http/Controllers/EventController';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { usePublicTemplate } from '@/composables/usePublicTemplate';
import { useI18n } from '@/lib/i18n';
import {
    edit as editEvent,
    index as eventsIndex,
    privateArea as eventPrivateArea,
    register as registerEvent,
} from '@/routes/events';

type EventDetail = {
    id: string;
    title: string;
    slug: string;
    canEdit: boolean;
    canDelete: boolean;
    description: string | null;
    description_html: string;
    start_time: string;
    end_time: string;
    cover_path: string | null;
    price: string | null;
    currency: string;
    address: Record<string, string | null> | null;
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
        status: string | null;
        can_access_private_area: boolean;
    };
}>();

const { locale, t } = useI18n();
const page = usePage();
const { confirm } = useConfirmDialog();

const deleteEvent = async (): Promise<void> => {
    if (
        await confirm({
            message: t('events.delete_confirm', { title: props.event.title }),
            confirmLabel: t('actions.delete'),
            intent: 'danger',
        })
    ) {
        router.delete(destroyEvent.url({ event: props.event.id }));
    }
};

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

const priceLabel = computed(() => {
    if (!props.event.price || Number(props.event.price) <= 0) {
        return t('events.show.free');
    }

    return new Intl.NumberFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
        style: 'currency',
        currency: props.event.currency,
    }).format(Number(props.event.price));
});

const addressLabel = computed(() =>
    props.event.address
        ? [
              props.event.address.street,
              props.event.address.number,
              props.event.address.neighborhood,
              props.event.address.city,
              props.event.address.state,
          ]
              .filter(Boolean)
              .join(', ')
        : '',
);

const coverStyle = computed(() => {
    if (props.event.cover_path) {
        return `background-image: linear-gradient(180deg, rgba(6, 20, 34, 0.18), rgba(6, 20, 34, 0.74)), url('${props.event.cover_path}'); background-size: cover; background-position: center;`;
    }

    return 'background-image: linear-gradient(140deg, #0b3d44 0%, #0f5f68 45%, #0f7a69 100%);';
});
const shareEvent = async (): Promise<void> => {
    const shareData = {
        title: props.event.title,
        text: props.event.description ?? props.event.title,
        url: window.location.href,
    };

    try {
        if (navigator.share) {
            await navigator.share(shareData);
        } else if (navigator.clipboard) {
            await navigator.clipboard.writeText(shareData.url);
            toast.success(t('share.copied'));
        } else {
            toast.error(t('share.unavailable'));
        }
    } catch (error) {
        if ((error as DOMException).name !== 'AbortError') {
            toast.error(t('share.unavailable'));
        }
    }
};
</script>

<template>
    <Head :title="event.title">
        <meta
            head-key="description"
            name="description"
            :content="event.description ?? event.title"
        />
        <meta head-key="og:title" property="og:title" :content="event.title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="event.description ?? event.title"
        />
        <meta
            v-if="event.cover_path"
            head-key="og:image"
            property="og:image"
            :content="event.cover_path"
        />
        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="event.title"
        />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="event.description ?? event.title"
        />
    </Head>

    <div
        class="public-template-page flex min-h-screen flex-col bg-[#f8fafc] text-slate-950"
        :data-public-template="publicTemplate"
    >
        <PublicHeader active="events" />

        <main
            class="mx-auto w-full max-w-6xl flex-1 px-3 py-5 sm:px-6 sm:py-8 lg:px-8"
        >
            <section
                class="relative overflow-hidden rounded-2xl p-5 text-white sm:rounded-3xl sm:p-7 md:p-9"
                :style="coverStyle"
            >
                <p
                    class="mb-2 text-xs tracking-[0.2em] text-[#b9ece6] uppercase"
                >
                    {{ event.church?.name ?? t('events.shared.community') }}
                </p>
                <h1
                    class="max-w-4xl [font-family:Manrope,ui-sans-serif] text-2xl font-black sm:text-3xl lg:text-4xl"
                >
                    {{ event.title }}
                </h1>
                <p class="mt-3 max-w-3xl text-sm text-[#d8f6ef] md:text-base">
                    {{ dateRange }}
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <Link
                        v-if="registration.can_access_private_area"
                        :href="eventPrivateArea({ event: event.slug })"
                        class="rounded-full bg-[#a9f4e3] px-5 py-2.5 text-sm font-extrabold text-[#0b3d44]"
                    >
                        {{ t('events.show.private_area') }}
                    </Link>
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
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-full bg-white text-[#0b3d44]"
                        :title="t('share.button')"
                        :aria-label="t('share.button')"
                        @click="shareEvent"
                    >
                        <Share2 class="size-4" /><span class="sr-only">{{
                            t('share.button')
                        }}</span>
                    </button>
                    <Link
                        v-if="event.canEdit"
                        :href="
                            editEvent(event.id, {
                                query: { return_to: page.url },
                            })
                        "
                        class="flex size-10 items-center justify-center rounded-full bg-white text-[#0b3d44]"
                        :title="t('actions.edit')"
                        :aria-label="t('actions.edit')"
                    >
                        <Edit class="size-4" /><span class="sr-only">{{
                            t('actions.edit')
                        }}</span>
                    </Link>
                    <button
                        v-if="event.canDelete"
                        type="button"
                        class="flex size-10 items-center justify-center rounded-full bg-rose-600 text-white"
                        :title="t('actions.delete')"
                        :aria-label="t('actions.delete')"
                        @click="deleteEvent"
                    >
                        <Trash class="size-4" /><span class="sr-only">{{
                            t('actions.delete')
                        }}</span>
                    </button>
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
                    class="rounded-2xl border border-[#d8e2ec] bg-white p-4 sm:rounded-3xl sm:p-6 md:p-8"
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
                        <p class="mt-3 text-lg font-black text-[#20374f]">
                            {{ priceLabel }}
                        </p>
                        <p
                            v-if="registration.status === 'pending'"
                            class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800"
                        >
                            {{ t('events.show.awaiting_confirmation') }}
                        </p>
                    </section>

                    <section
                        v-if="addressLabel"
                        class="rounded-2xl border border-[#d8e2ec] bg-white p-5"
                    >
                        <p
                            class="text-xs tracking-[0.14em] text-[#5b7388] uppercase"
                        >
                            {{ t('events.show.location') }}
                        </p>
                        <p class="mt-2 text-sm text-[#3f566c]">
                            {{ addressLabel }}
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
