<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    Check,
    Church as ChurchIcon,
    Globe2,
    LocateFixed,
    MapPin,
    Network,
    Plus,
    Radio,
    ShieldCheck,
    X,
    FileCheck2,
    Paperclip,
} from '@lucide/vue';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import AppModal from '@/components/AppModal.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import PortalHeader from '@/components/PortalHeader.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useI18n } from '@/lib/i18n';
import { home, login, register } from '@/routes';

type Church = {
    id: string;
    name: string;
    slug: string;
    domain: string | null;
    url: string | null;
    is_live: boolean;
    distance_km?: number | null;
};

type ParentChurch = {
    id: string;
    name: string;
};

type Community = {
    id: string;
    name: string;
    slug: string;
    url: string;
    description: string;
    churches_count: number;
    churches: Church[];
    distance_km?: number | null;
    default_locale: 'pt' | 'en';
};

type RegistrationRequest = {
    id: string;
    name: string;
    slug: string;
    domain: string;
    description?: string | null;
    address?: string | null;
    contact_email?: string | null;
    contact_phone?: string | null;
    locale?: 'pt' | 'en' | null;
    status: string;
    review_notes?: string | null;
    community: { id: string; name: string };
    requested_parent_church?: ParentChurch | null;
    requester?: { name: string; email: string };
    document_url?: string | null;
};

const props = defineProps<{
    communities: Community[];
    nearbyCommunities: Community[];
    locationApplied: boolean;
    canOnboard: boolean;
    userCommunityId?: string | null;
    userChurchUrl?: string | null;
    reviewableRequests: RegistrationRequest[];
    myRequests: RegistrationRequest[];
    mainDomain: string;
}>();

const { locale, t } = useI18n();
const { confirm, prompt } = useConfirmDialog();
const communityModalOpen = ref(false);
const onboardingMode = ref<'new_community' | 'existing_community'>(
    props.userCommunityId ? 'existing_community' : 'new_community',
);
const processing = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const locating = ref(false);
const domainMode = ref<'subdomain' | 'external'>('subdomain');
const domainInput = ref('');
const proofDocument = ref<File | null>(null);
const communityForm = ref({
    name: '',
    slug: '',
    description: '',
    found_date: '',
    default_locale: locale.value,
    address: '',
    latitude: '',
    longitude: '',
});
const churchForm = ref({
    community_id: props.userCommunityId ?? '',
    parent_church_id: '',
    name: '',
    slug: '',
    description: '',
    found_date: '',
    contact_email: '',
    contact_phone: '',
    address: '',
    latitude: '',
    longitude: '',
    locale: locale.value,
});

const normalizedDomainInput = computed(() =>
    domainInput.value
        .trim()
        .toLowerCase()
        .replace(/^https?:\/\//, '')
        .split('/')[0]
        .replace(/\.$/, ''),
);

const requestedDomain = computed(() =>
    domainMode.value === 'subdomain'
        ? `${normalizedDomainInput.value}.${props.mainDomain}`
        : normalizedDomainInput.value,
);
const domainModes = ['subdomain', 'external'] as const;

const domainError = computed(() => {
    if (!normalizedDomainInput.value) {
        return t('portal.domain.required');
    }

    if (requestedDomain.value === props.mainDomain) {
        return t('portal.domain.main_forbidden');
    }

    if (
        !/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/.test(
            requestedDomain.value,
        )
    ) {
        return t('portal.domain.invalid');
    }

    return '';
});

const availableCommunities = computed(() => props.communities);
const selectedCommunity = computed(() =>
    props.communities.find(
        (community) => community.id === churchForm.value.community_id,
    ),
);
const registrationParentChurches = computed<ParentChurch[]>(() =>
    (selectedCommunity.value?.churches ?? []).map(({ id, name }) => ({
        id,
        name,
    })),
);

watch(
    () => churchForm.value.community_id,
    () => {
        churchForm.value.parent_church_id = '';
        churchForm.value.locale =
            selectedCommunity.value?.default_locale ?? locale.value;
    },
    { immediate: true },
);

const openOnboarding = (
    mode: 'new_community' | 'existing_community',
): void => {
    onboardingMode.value = mode;
    errorMessage.value = '';
    communityModalOpen.value = true;
};

const useCurrentLocation = (
    form: { latitude: string; longitude: string },
): void => {
    if (!('geolocation' in navigator)) {
        return;
    }

    navigator.geolocation.getCurrentPosition(({ coords }) => {
        form.latitude = String(coords.latitude);
        form.longitude = String(coords.longitude);
    });
};

const requestNearbyCommunities = (
    latitude: number,
    longitude: number,
): void => {
    router.get(
        home(),
        { latitude, longitude },
        {
            only: ['nearbyCommunities', 'locationApplied'],
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
                requestNearbyCommunities(parsed.latitude, parsed.longitude);

                return;
            }
        } catch {
            window.localStorage.removeItem('ncapp_portal_location');
        }

        window.localStorage.removeItem('ncapp_portal_location');
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
            window.dispatchEvent(
                new CustomEvent('ncapp:location-updated', {
                    detail: location,
                }),
            );
            requestNearbyCommunities(location.latitude, location.longitude);
        },
        () => {
            locating.value = false;
        },
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 3600000 },
    );
});

const requestError = (error: unknown): string => {
    if (axios.isAxiosError(error)) {
        const errors = error.response?.data?.errors as
            Record<string, string[]> | undefined;

        return errors
            ? Object.values(errors).flat()[0]
            : (error.response?.data?.message ?? t('portal.errors.generic'));
    }

    return t('portal.errors.generic');
};

const saveCommunity = async (): Promise<void> => {
    processing.value = true;
    errorMessage.value = '';

    try {
        await axios.post('/onboarding/communities', communityForm.value);
        window.location.reload();
    } catch (error) {
        errorMessage.value = requestError(error);
    } finally {
        processing.value = false;
    }
};

const requestChurch = async (): Promise<void> => {
    if (domainError.value) {
        errorMessage.value = domainError.value;

        return;
    }

    processing.value = true;
    errorMessage.value = '';

    try {
        const payload = new FormData();

        Object.entries({
            ...churchForm.value,
            domain: requestedDomain.value,
        }).forEach(([key, value]) => payload.append(key, value));

        if (proofDocument.value) {
            payload.append('proof_document', proofDocument.value);
        }

        await axios.post('/onboarding/churches', payload);
        communityModalOpen.value = false;
        successMessage.value = t('portal.onboarding.request_sent');
        window.setTimeout(() => window.location.reload(), 900);
    } catch (error) {
        errorMessage.value = requestError(error);
    } finally {
        processing.value = false;
    }
};

const selectProofDocument = (event: Event): void => {
    proofDocument.value = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const approveRequest = async (request: RegistrationRequest): Promise<void> => {
    if (
        !(await confirm({
            message: t('portal.review.approve_confirm', {
                name: request.name,
            }),
        }))
    ) {
        return;
    }

    await axios.post(`/onboarding/churches/${request.id}/approve`);
    window.location.reload();
};

const rejectRequest = async (request: RegistrationRequest): Promise<void> => {
    const notes = await prompt({
        message: t('portal.review.reject_reason'),
        inputLabel: t('portal.review.reject_reason'),
        intent: 'danger',
    });

    if (!notes) {
        return;
    }

    await axios.post(`/onboarding/churches/${request.id}/reject`, {
        review_notes: notes,
    });
    window.location.reload();
};
</script>

<template>
    <Head :title="t('portal.meta_title')" />
    <div class="min-h-screen min-w-0 overflow-x-clip bg-[#f7f8fc] text-slate-950">
        <PortalHeader :user-church-url="userChurchUrl" />

        <main>
            <section
                class="overflow-hidden bg-gradient-to-br from-indigo-950 via-indigo-900 to-violet-800 text-white"
            >
                <div
                    class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-5 sm:py-16 lg:grid-cols-[1.15fr_0.85fr] lg:gap-10 lg:px-8 lg:py-20"
                >
                    <div>
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-violet-300/30 bg-white/10 px-3 py-1 text-xs font-bold text-violet-100"
                            ><Network class="size-4" />
                            {{ t('portal.hero.badge') }}</span
                        >
                        <h1
                            class="mt-5 max-w-3xl text-3xl font-black tracking-tight sm:mt-6 sm:text-4xl lg:text-5xl"
                        >
                            {{ t('portal.hero.title') }}
                        </h1>
                        <p
                            class="mt-5 max-w-2xl text-base leading-7 text-indigo-100 sm:text-lg"
                        >
                            {{ t('portal.hero.description') }}
                        </p>
                        <div class="mt-7 grid gap-3 sm:flex sm:flex-wrap">
                            <a
                                href="#communities"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-400 px-5 py-3 text-sm font-black text-emerald-950"
                                >{{ t('portal.hero.explore') }}
                                <ArrowRight class="size-4"
                            /></a>
                            <button
                                v-if="canOnboard"
                                class="rounded-xl border border-white/30 px-5 py-3 text-center text-sm font-black"
                                @click="openOnboarding('new_community')"
                            >
                                {{ t('portal.onboarding.register_community') }}
                            </button>
                            <Link
                                v-else
                                :href="register()"
                                class="rounded-xl border border-white/30 px-5 py-3 text-center text-sm font-black"
                                >{{ t('portal.hero.join') }}</Link
                            >
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                        <article
                            class="rounded-3xl border border-white/15 bg-white/10 p-6 backdrop-blur"
                        >
                            <Globe2 class="size-8 text-emerald-300" />
                            <h2 class="mt-5 text-xl font-black">
                                {{ t('portal.hero.domains_title') }}
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-indigo-100">
                                {{ t('portal.hero.domains_description') }}
                            </p>
                        </article>
                        <article
                            class="rounded-3xl border border-white/15 bg-white/10 p-6 backdrop-blur"
                        >
                            <ShieldCheck class="size-8 text-amber-300" />
                            <h2 class="mt-5 text-xl font-black">
                                {{ t('portal.hero.governance_title') }}
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-indigo-100">
                                {{ t('portal.hero.governance_description') }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section
                v-if="locating || nearbyCommunities.length"
                class="min-w-0 overflow-hidden border-b border-indigo-100 bg-indigo-50/70"
            >
                <div
                    data-test="nearby-communities-container"
                    class="mx-auto min-w-0 max-w-6xl px-4 py-8 sm:px-5 sm:py-10 lg:px-8"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="grid size-11 place-items-center rounded-2xl bg-indigo-700 text-white"
                        >
                            <LocateFixed class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <p
                                class="font-mono text-[10px] font-black tracking-[0.18em] text-indigo-600 uppercase"
                            >
                                {{ t('portal.nearby.kicker') }}
                            </p>
                            <h2
                                class="text-2xl font-black break-words text-indigo-950"
                            >
                                {{
                                    locating
                                        ? t('portal.nearby.locating')
                                        : t('portal.nearby.title')
                                }}
                            </h2>
                        </div>
                    </div>
                    <div
                        v-if="nearbyCommunities.length"
                        class="mt-6 grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-4"
                    >
                        <article
                            v-for="community in nearbyCommunities"
                            :key="community.id"
                            class="min-w-0 rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm"
                        >
                            <div
                                class="flex min-w-0 items-start justify-between gap-3"
                            >
                                <Link
                                    :href="community.url"
                                    class="min-w-0 flex-1 break-words font-black text-indigo-950 hover:text-indigo-700"
                                >
                                    {{ community.name }}
                                </Link>
                                <span
                                    v-if="community.distance_km != null"
                                    class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full bg-sky-50 px-2 py-1 text-[10px] font-black text-sky-700"
                                >
                                    <MapPin class="size-3" />
                                    {{ community.distance_km }} {{ t('units.kilometers_short') }}
                                </span>
                            </div>
                            <div class="mt-4 space-y-2">
                                <a
                                    v-for="church in community.churches.slice(
                                        0,
                                        3,
                                    )"
                                    :key="church.id"
                                    :href="church.url ?? undefined"
                                    class="flex min-w-0 items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-indigo-100"
                                    :class="{
                                        'pointer-events-none opacity-60':
                                            !church.url,
                                    }"
                                >
                                    <span class="min-w-0 flex-1 truncate">{{
                                        church.name
                                    }}</span>
                                    <span
                                        v-if="church.distance_km != null"
                                        class="ml-2 shrink-0 whitespace-nowrap text-slate-400"
                                    >
                                        {{ church.distance_km }} {{ t('units.kilometers_short') }}
                                    </span>
                                </a>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section
                id="communities"
                class="mx-auto max-w-6xl px-4 py-12 sm:px-5 sm:py-16 lg:px-8"
            >
                <div
                    class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
                >
                    <div>
                        <p
                            class="font-mono text-xs font-bold tracking-[0.18em] text-indigo-600 uppercase"
                        >
                            {{ t('portal.communities.kicker') }}
                        </p>
                        <h2 class="mt-2 text-3xl font-black">
                            {{ t('portal.communities.title') }}
                        </h2>
                        <p class="mt-2 max-w-2xl text-sm text-slate-500">
                            {{ t('portal.communities.description') }}
                        </p>
                    </div>
                    <button
                        v-if="canOnboard"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-700 px-4 py-3 text-sm font-black text-white"
                        @click="openOnboarding('existing_community')"
                    >
                        <Plus class="size-4" />
                        {{ t('portal.onboarding.request_church') }}
                    </button>
                    <Link
                        v-else-if="!canOnboard"
                        :href="login()"
                        class="inline-flex items-center gap-2 rounded-xl border border-indigo-700 px-4 py-3 text-sm font-black text-indigo-700"
                    >
                        {{ t('nav.login') }}
                    </Link>
                </div>
                <div class="mt-8 grid gap-5 lg:grid-cols-2">
                    <article
                        v-for="community in communities"
                        :key="community.id"
                        class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <span
                                    class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-[10px] font-black text-indigo-700 uppercase"
                                    >{{
                                        t('portal.communities.church_count', {
                                            count: community.churches_count,
                                        })
                                    }}</span
                                >
                                <h3 class="mt-3 text-xl font-black">
                                    <Link
                                        :href="community.url"
                                        class="hover:text-indigo-700"
                                    >
                                        {{ community.name }}
                                    </Link>
                                </h3>
                                <p
                                    class="mt-2 text-sm leading-6 text-slate-500"
                                >
                                    {{ community.description }}
                                </p>
                            </div>
                            <Building2
                                class="size-8 shrink-0 text-indigo-300"
                            />
                        </div>
                        <div class="mt-5 space-y-2 border-t pt-4">
                            <template
                                v-for="church in community.churches"
                                :key="church.id"
                            >
                                <a
                                    v-if="church.url"
                                    :href="church.url"
                                    class="group flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 hover:border-indigo-200 hover:bg-indigo-50"
                                    ><span class="flex items-center gap-3"
                                        ><ChurchIcon
                                            class="size-4 text-indigo-600"
                                        /><span
                                            ><span
                                                class="flex items-center gap-2"
                                                ><strong
                                                    class="block text-sm"
                                                    >{{ church.name }}</strong
                                                ><span
                                                    v-if="church.is_live"
                                                    class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black text-rose-700 uppercase"
                                                    ><Radio class="size-3" />
                                                    {{
                                                        t(
                                                            'portal.communities.live',
                                                        )
                                                    }}</span
                                                ></span
                                            ><small class="text-slate-400">{{
                                                church.domain
                                            }}</small></span
                                        ></span
                                    ><ArrowRight
                                        class="size-4 text-slate-300 transition group-hover:translate-x-1 group-hover:text-indigo-600"
                                /></a>
                                <div
                                    v-else
                                    class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 opacity-70"
                                >
                                    <span class="flex items-center gap-3"
                                        ><ChurchIcon
                                            class="size-4 text-slate-400"
                                        /><span
                                            ><strong class="block text-sm">{{
                                                church.name
                                            }}</strong
                                            ><small class="text-slate-400">{{
                                                t(
                                                    'portal.communities.domain_pending',
                                                )
                                            }}</small></span
                                        ></span
                                    >
                                </div>
                            </template>
                            <Link
                                :href="community.url"
                                class="mt-3 inline-flex items-center gap-2 text-xs font-black text-indigo-700"
                            >
                                {{ t('portal.communities.view') }}
                                <ArrowRight class="size-4" />
                            </Link>
                        </div>
                    </article>
                    <p
                        v-if="!communities.length"
                        class="rounded-3xl border border-dashed p-12 text-center text-sm text-slate-500"
                    >
                        {{ t('portal.communities.empty') }}
                    </p>
                </div>
            </section>

            <section
                v-if="
                    canOnboard &&
                    (myRequests.length || reviewableRequests.length)
                "
                class="mx-auto max-w-6xl space-y-8 px-4 pb-12 sm:px-5 sm:pb-16 lg:px-8"
            >
                <div
                    v-if="successMessage"
                    class="rounded-xl bg-emerald-50 p-4 text-sm font-bold text-emerald-700"
                >
                    {{ successMessage }}
                </div>
                <div v-if="myRequests.length">
                    <h2 class="text-xl font-black">
                        {{ t('portal.onboarding.my_requests') }}
                    </h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <article
                            v-for="item in myRequests"
                            :key="item.id"
                            class="rounded-2xl border bg-white p-5"
                        >
                            <div class="flex justify-between gap-3">
                                <div>
                                    <h3 class="font-black">{{ item.name }}</h3>
                                    <p class="text-xs text-slate-500">
                                        {{ item.community.name }} ·
                                        {{ item.domain }}
                                    </p>
                                    <p
                                        v-if="item.requested_parent_church"
                                        class="mt-1 text-xs font-bold text-indigo-700"
                                    >
                                        {{
                                            t('portal.review.requested_parent', {
                                                name: item.requested_parent_church.name,
                                            })
                                        }}
                                    </p>
                                </div>
                                <span
                                    class="h-fit rounded-full px-2 py-1 text-[10px] font-black uppercase"
                                    :class="
                                        item.status === 'approved'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : item.status === 'rejected'
                                              ? 'bg-rose-50 text-rose-700'
                                              : 'bg-amber-50 text-amber-700'
                                    "
                                    >{{
                                        t(`portal.status.${item.status}`)
                                    }}</span
                                >
                            </div>
                            <p
                                v-if="item.review_notes"
                                class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600"
                            >
                                {{ item.review_notes }}
                            </p>
                        </article>
                    </div>
                </div>
                <div v-if="reviewableRequests.length">
                    <h2 class="text-xl font-black">
                        {{ t('portal.review.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ t('portal.review.description') }}
                    </p>
                    <div class="mt-4 space-y-3">
                        <article
                            v-for="item in reviewableRequests"
                            :key="item.id"
                            class="rounded-2xl border bg-white p-5"
                        >
                            <div
                                class="flex flex-col justify-between gap-4 md:flex-row"
                            >
                                <div>
                                    <h3 class="font-black">{{ item.name }}</h3>
                                    <p class="text-xs text-slate-500">
                                        {{ item.community.name }} ·
                                        {{ item.domain }} ·
                                        {{ item.requester?.name }} ({{
                                            item.requester?.email
                                        }})
                                    </p>
                                    <p
                                        v-if="item.requested_parent_church"
                                        class="mt-2 text-xs font-bold text-indigo-700"
                                    >
                                        {{
                                            t('portal.review.requested_parent', {
                                                name: item.requested_parent_church.name,
                                            })
                                        }}
                                    </p>
                                    <p
                                        class="mt-3 max-w-3xl text-sm text-slate-600"
                                    >
                                        {{ item.description }}
                                    </p>
                                    <p
                                        v-if="item.address"
                                        class="mt-2 text-xs text-slate-500"
                                    >
                                        {{ item.address }}
                                    </p>
                                    <p
                                        v-if="item.locale"
                                        class="mt-1 text-xs font-bold text-slate-500"
                                    >
                                        {{ t('portal.fields.default_language') }}:
                                        {{ t(`portal.fields.language_${item.locale}`) }}
                                    </p>
                                    <a
                                        v-if="item.document_url"
                                        :href="item.document_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-3 inline-flex items-center gap-2 rounded-lg bg-indigo-50 px-3 py-2 text-xs font-black text-indigo-700"
                                    >
                                        <FileCheck2 class="size-4" />
                                        {{ t('portal.review.view_document') }}
                                    </a>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button
                                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white"
                                        @click="approveRequest(item)"
                                    >
                                        <Check class="size-4" />
                                        {{ t('portal.review.approve') }}</button
                                    ><button
                                        class="inline-flex items-center gap-2 rounded-xl bg-rose-50 px-4 py-2 text-xs font-black text-rose-700"
                                        @click="rejectRequest(item)"
                                    >
                                        <X class="size-4" />
                                        {{ t('portal.review.reject') }}
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </main>

        <PublicFooter show-locale />

        <AppModal
            v-model:open="communityModalOpen"
            :title="
                onboardingMode === 'new_community'
                    ? t('portal.onboarding.register_community')
                    : t('portal.onboarding.request_church')
            "
            :description="
                onboardingMode === 'new_community'
                    ? t('portal.onboarding.community_kicker')
                    : t('portal.onboarding.church_kicker')
            "
            size="lg"
            content-class="max-h-[90dvh] grid-rows-[auto_auto_minmax(0,1fr)] overflow-hidden border-slate-200 bg-white text-slate-950 shadow-2xl"
            header-class="text-slate-950 [&_p]:text-slate-500"
        >
            <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
                <button
                    type="button"
                    class="rounded-lg px-3 py-2.5 text-sm font-bold transition"
                    :class="
                        onboardingMode === 'new_community'
                            ? 'bg-white text-indigo-700 shadow-sm'
                            : 'text-slate-500 hover:text-slate-800'
                    "
                    @click="onboardingMode = 'new_community'"
                >
                    {{ t('portal.onboarding.new_community') }}
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-2.5 text-sm font-bold transition"
                    :class="
                        onboardingMode === 'existing_community'
                            ? 'bg-white text-indigo-700 shadow-sm'
                            : 'text-slate-500 hover:text-slate-800'
                    "
                    @click="onboardingMode = 'existing_community'"
                >
                    {{ t('portal.onboarding.existing_community') }}
                </button>
            </div>

            <form
                v-if="onboardingMode === 'new_community'"
                class="min-h-0 space-y-5 overflow-y-auto overscroll-contain pr-1 [scrollbar-gutter:stable]"
                @submit.prevent="saveCommunity"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.name') }}
                        <input
                            v-model="communityForm.name"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.slug') }}
                        <input
                            v-model="communityForm.slug"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700 sm:col-span-2">
                        {{ t('portal.fields.description') }}
                        <textarea
                            v-model="communityForm.description"
                            required
                            rows="4"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.found_date') }}
                        <input
                            v-model="communityForm.found_date"
                            type="date"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none [color-scheme:light] focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.default_language') }}
                        <select
                            v-model="communityForm.default_locale"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        >
                            <option value="pt">{{ t('portal.fields.language_pt') }}</option>
                            <option value="en">{{ t('portal.fields.language_en') }}</option>
                        </select>
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700 sm:col-span-2">
                        {{ t('portal.fields.address') }}
                        <textarea
                            v-model="communityForm.address"
                            rows="2"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            :placeholder="t('portal.fields.address_hint')"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.latitude') }}
                        <input
                            v-model="communityForm.latitude"
                            type="number"
                            step="0.0000001"
                            min="-90"
                            max="90"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.longitude') }}
                        <input
                            v-model="communityForm.longitude"
                            type="number"
                            step="0.0000001"
                            min="-180"
                            max="180"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-bold text-indigo-700 transition hover:bg-indigo-100"
                    @click="useCurrentLocation(communityForm)"
                >
                    <LocateFixed class="size-4" />
                    {{ t('portal.fields.use_current_location') }}
                </button>
                <p v-if="errorMessage" class="text-sm font-bold text-rose-600">
                    {{ errorMessage }}
                </p>
                <button
                    :disabled="processing"
                    class="w-full rounded-xl bg-indigo-700 py-3 text-sm font-black text-white"
                >
                    {{ t('actions.save') }}
                </button>
            </form>
            <form
                v-else
                class="min-h-0 space-y-5 overflow-y-auto overscroll-contain pr-1 [scrollbar-gutter:stable]"
                @submit.prevent="requestChurch"
            >
                <label class="block space-y-1.5 text-sm font-bold text-slate-700">
                    {{ t('portal.fields.community') }}
                    <select
                        v-model="churchForm.community_id"
                        required
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                    >
                        <option value="" disabled>
                            {{ t('portal.fields.select_community') }}
                        </option>
                        <option
                            v-for="community in availableCommunities"
                            :key="community.id"
                            :value="community.id"
                        >
                            {{ community.name }}
                        </option>
                    </select>
                </label>
                <div>
                    <label
                        for="registration-parent-church"
                        class="mb-1.5 block text-sm font-bold text-slate-700"
                    >
                        {{ t('portal.fields.parent_church') }}
                    </label>
                    <select
                        id="registration-parent-church"
                        v-model="churchForm.parent_church_id"
                        :disabled="!churchForm.community_id"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                    >
                        <option value="">
                            {{ t('portal.onboarding.community_approval') }}
                        </option>
                        <option
                            v-for="church in registrationParentChurches"
                            :key="church.id"
                            :value="church.id"
                        >
                            {{ church.name }}
                        </option>
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500">
                        {{ t('portal.onboarding.parent_church_hint') }}
                    </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.name') }}
                        <input
                            v-model="churchForm.name"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.slug') }}
                        <input
                            v-model="churchForm.slug"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                    <div class="space-y-3 sm:col-span-2 sm:contents">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="mode in domainModes"
                                :key="mode"
                                class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 text-slate-800"
                                :class="{
                                    'border-indigo-500 bg-indigo-50':
                                        domainMode === mode,
                                }"
                            >
                                <input
                                    v-model="domainMode"
                                    type="radio"
                                    :value="mode"
                                    class="mt-1"
                                />
                                <span>
                                    <strong class="block text-sm">{{
                                        t(`portal.domain.${mode}`)
                                    }}</strong>
                                    <span class="text-xs text-slate-500">{{
                                        t(`portal.domain.${mode}_description`)
                                    }}</span>
                                </span>
                            </label>
                        </div>
                        <div
                            class="flex overflow-hidden rounded-xl border border-slate-300 bg-white"
                        >
                            <input
                                v-model="domainInput"
                                required
                                class="min-w-0 flex-1 border-0 bg-white px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:ring-0"
                                :placeholder="
                                    domainMode === 'subdomain'
                                        ? t(
                                              'portal.domain.subdomain_placeholder',
                                          )
                                        : t('portal.fields.domain')
                                "
                            />
                            <span
                                v-if="domainMode === 'subdomain'"
                                class="flex items-center border-l border-slate-200 bg-slate-50 px-3 text-sm text-slate-500"
                                >.{{ mainDomain }}</span
                            >
                        </div>
                        <p class="text-xs text-slate-500">
                            {{
                                domainMode === 'subdomain'
                                    ? requestedDomain ||
                                      t('portal.domain.preview_empty')
                                    : t('portal.domain.external_hint')
                            }}
                        </p>
                        <p
                            v-if="domainError"
                            class="text-xs font-bold text-rose-600"
                        >
                            {{ domainError }}
                        </p>
                    </div>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.found_date') }}
                        <input
                            v-model="churchForm.found_date"
                            type="date"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none [color-scheme:light] focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.default_language') }}
                        <select
                            v-model="churchForm.locale"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        >
                            <option value="pt">{{ t('portal.fields.language_pt') }}</option>
                            <option value="en">{{ t('portal.fields.language_en') }}</option>
                        </select>
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.contact_email') }}
                        <input
                            v-model="churchForm.contact_email"
                            type="email"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.contact_phone') }}
                        <PhoneInput
                            v-model="churchForm.contact_phone"
                            class="!border-slate-300 !bg-white [&_input]:!bg-white [&_input]:!text-slate-900 [&_input]:placeholder:!text-slate-400 [&_option]:!bg-white [&_option]:!text-slate-900 [&_select]:!bg-white [&_select]:!text-slate-900"
                        />
                    </label>
                </div>
                <label class="block space-y-1.5 text-sm font-bold text-slate-700">
                    {{ t('portal.fields.description') }}
                    <textarea
                        v-model="churchForm.description"
                        rows="3"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                    />
                </label>
                <label class="block space-y-1.5 text-sm font-bold text-slate-700">
                    {{ t('portal.fields.address') }}
                    <textarea
                        v-model="churchForm.address"
                        rows="2"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        :placeholder="t('portal.fields.address_hint')"
                    />
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.latitude') }}
                        <input
                            v-model="churchForm.latitude"
                            type="number"
                            step="0.0000001"
                            min="-90"
                            max="90"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                    <label class="space-y-1.5 text-sm font-bold text-slate-700">
                        {{ t('portal.fields.longitude') }}
                        <input
                            v-model="churchForm.longitude"
                            type="number"
                            step="0.0000001"
                            min="-180"
                            max="180"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                        />
                    </label>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-bold text-indigo-700 transition hover:bg-indigo-100"
                    @click="useCurrentLocation(churchForm)"
                >
                    <LocateFixed class="size-4" />
                    {{ t('portal.fields.use_current_location') }}
                </button>
                <label
                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-indigo-300 bg-indigo-50/50 p-4"
                >
                    <Paperclip class="size-5 text-indigo-600" />
                    <span class="min-w-0 flex-1">
                        <strong class="block text-sm text-slate-900">{{
                            t('portal.fields.proof_document')
                        }}</strong>
                        <span class="block truncate text-xs text-slate-500">{{
                            proofDocument?.name ||
                            t('portal.fields.proof_document_hint')
                        }}</span>
                    </span>
                    <input
                        type="file"
                        accept="application/pdf,image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="selectProofDocument"
                    />
                </label>
                <p class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                    {{
                        churchForm.parent_church_id
                            ? t('portal.onboarding.parent_approval_notice')
                            : t('portal.onboarding.approval_notice')
                    }}
                </p>
                <p v-if="errorMessage" class="text-sm font-bold text-rose-600">
                    {{ errorMessage }}
                </p>
                <button
                    :disabled="processing"
                    class="w-full rounded-xl bg-indigo-700 py-3 text-sm font-black text-white"
                >
                    {{ t('portal.onboarding.send_request') }}
                </button>
            </form>
        </AppModal>
    </div>
</template>
