<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    Check,
    Church as ChurchIcon,
    Globe2,
    Network,
    Plus,
    Radio,
    ShieldCheck,
    X,
} from '@lucide/vue';
import axios from 'axios';
import { computed, ref } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import { useI18n } from '@/lib/i18n';
import { home, login, register } from '@/routes';

type Church = {
    id: string;
    name: string;
    slug: string;
    domain: string | null;
    url: string | null;
    is_live: boolean;
};

type Community = {
    id: string;
    name: string;
    slug: string;
    description: string;
    churches_count: number;
    churches: Church[];
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
    status: string;
    review_notes?: string | null;
    community: { id: string; name: string };
    requester?: { name: string; email: string };
};

const props = defineProps<{
    communities: Community[];
    canOnboard: boolean;
    userCommunityId?: string | null;
    userChurchUrl?: string | null;
    reviewableRequests: RegistrationRequest[];
    myRequests: RegistrationRequest[];
    mainDomain: string;
}>();

const { t } = useI18n();
const communityModalOpen = ref(false);
const churchModalOpen = ref(false);
const processing = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const domainMode = ref<'subdomain' | 'external'>('subdomain');
const domainInput = ref('');
const communityForm = ref({
    name: '',
    slug: '',
    description: '',
    found_date: '',
});
const churchForm = ref({
    community_id: props.userCommunityId ?? '',
    name: '',
    slug: '',
    description: '',
    found_date: '',
    contact_email: '',
    contact_phone: '',
    address: '',
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

const availableCommunities = computed(() =>
    props.communities.filter(
        (community) => community.id === props.userCommunityId,
    ),
);

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
        await axios.post('/onboarding/churches', {
            ...churchForm.value,
            domain: requestedDomain.value,
        });
        churchModalOpen.value = false;
        successMessage.value = t('portal.onboarding.request_sent');
        window.setTimeout(() => window.location.reload(), 900);
    } catch (error) {
        errorMessage.value = requestError(error);
    } finally {
        processing.value = false;
    }
};

const approveRequest = async (request: RegistrationRequest): Promise<void> => {
    if (
        !window.confirm(
            t('portal.review.approve_confirm', { name: request.name }),
        )
    ) {
        return;
    }

    await axios.post(`/onboarding/churches/${request.id}/approve`);
    window.location.reload();
};

const rejectRequest = async (request: RegistrationRequest): Promise<void> => {
    const notes = window.prompt(t('portal.review.reject_reason'));

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
    <div class="min-h-screen bg-[#f7f8fc] text-slate-950">
        <header class="border-b border-indigo-900/60 bg-[#312e81] text-white">
            <div
                class="mx-auto flex h-18 max-w-7xl items-center justify-between px-5 lg:px-8"
            >
                <Link :href="home()" class="flex items-center gap-3">
                    <span
                        class="grid size-11 place-items-center rounded-xl border border-white/25 bg-white/10 text-lg font-black"
                        >NC</span
                    >
                    <span>
                        <strong class="block text-base leading-none"
                            >Nossa Casa</strong
                        >
                        <small
                            class="font-mono text-[9px] tracking-[0.22em] text-indigo-200 uppercase"
                            >{{ t('portal.network_label') }}</small
                        >
                    </span>
                </Link>
                <div class="flex items-center gap-2">
                    <a
                        v-if="userChurchUrl"
                        :href="userChurchUrl"
                        class="rounded-xl bg-white px-4 py-2.5 text-xs font-black text-indigo-950"
                        >{{ t('portal.open_my_church') }}</a
                    >
                    <template v-else-if="!canOnboard">
                        <Link
                            :href="login()"
                            class="rounded-xl px-4 py-2.5 text-xs font-bold text-indigo-100"
                            >{{ t('nav.login') }}</Link
                        >
                        <Link
                            :href="register()"
                            class="rounded-xl bg-white px-4 py-2.5 text-xs font-black text-indigo-950"
                            >{{ t('portal.create_account') }}</Link
                        >
                    </template>
                </div>
            </div>
        </header>

        <main>
            <section
                class="overflow-hidden bg-gradient-to-br from-indigo-950 via-indigo-900 to-violet-800 text-white"
            >
                <div
                    class="mx-auto grid max-w-7xl gap-12 px-5 py-20 lg:grid-cols-[1.15fr_0.85fr] lg:px-8 lg:py-28"
                >
                    <div>
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-violet-300/30 bg-white/10 px-3 py-1 text-xs font-bold text-violet-100"
                            ><Network class="size-4" />
                            {{ t('portal.hero.badge') }}</span
                        >
                        <h1
                            class="mt-6 max-w-3xl text-4xl font-black tracking-tight sm:text-6xl"
                        >
                            {{ t('portal.hero.title') }}
                        </h1>
                        <p
                            class="mt-5 max-w-2xl text-base leading-7 text-indigo-100 sm:text-lg"
                        >
                            {{ t('portal.hero.description') }}
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a
                                href="#communities"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-400 px-5 py-3 text-sm font-black text-emerald-950"
                                >{{ t('portal.hero.explore') }}
                                <ArrowRight class="size-4"
                            /></a>
                            <button
                                v-if="canOnboard"
                                class="rounded-xl border border-white/30 px-5 py-3 text-sm font-black"
                                @click="communityModalOpen = true"
                            >
                                {{ t('portal.onboarding.register_community') }}
                            </button>
                            <Link
                                v-else
                                :href="register()"
                                class="rounded-xl border border-white/30 px-5 py-3 text-sm font-black"
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
                id="communities"
                class="mx-auto max-w-7xl px-5 py-16 lg:px-8"
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
                        v-if="canOnboard && userCommunityId"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-700 px-4 py-3 text-sm font-black text-white"
                        @click="churchModalOpen = true"
                    >
                        <Plus class="size-4" />
                        {{ t('portal.onboarding.request_church') }}
                    </button>
                    <Link
                        v-else-if="!canOnboard"
                        :href="login()"
                        class="inline-flex items-center gap-2 rounded-xl border border-indigo-700 px-4 py-3 text-sm font-black text-indigo-700"
                    >
                        {{ t('auth.login') }}
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
                                    {{ community.name }}
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
                class="mx-auto max-w-7xl space-y-8 px-5 pb-16 lg:px-8"
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

        <div
            v-if="communityModalOpen"
            class="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4"
            @click.self="communityModalOpen = false"
        >
            <form
                class="w-full max-w-xl space-y-4 rounded-3xl bg-white p-7 shadow-2xl"
                @submit.prevent="saveCommunity"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-indigo-600 uppercase">
                            {{ t('portal.onboarding.community_kicker') }}
                        </p>
                        <h2 class="text-2xl font-black">
                            {{ t('portal.onboarding.register_community') }}
                        </h2>
                    </div>
                    <button type="button" @click="communityModalOpen = false">
                        <X class="size-5" />
                    </button>
                </div>
                <input
                    v-model="communityForm.name"
                    required
                    class="w-full rounded-xl border-slate-300"
                    :placeholder="t('portal.fields.name')"
                /><input
                    v-model="communityForm.slug"
                    required
                    class="w-full rounded-xl border-slate-300"
                    :placeholder="t('portal.fields.slug')"
                /><textarea
                    v-model="communityForm.description"
                    required
                    rows="4"
                    class="w-full rounded-xl border-slate-300"
                    :placeholder="t('portal.fields.description')"
                /><input
                    v-model="communityForm.found_date"
                    type="date"
                    class="w-full rounded-xl border-slate-300"
                />
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
        </div>

        <div
            v-if="churchModalOpen"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/60 p-4"
            @click.self="churchModalOpen = false"
        >
            <form
                class="my-8 w-full max-w-2xl space-y-4 rounded-3xl bg-white p-7 shadow-2xl"
                @submit.prevent="requestChurch"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-indigo-600 uppercase">
                            {{ t('portal.onboarding.church_kicker') }}
                        </p>
                        <h2 class="text-2xl font-black">
                            {{ t('portal.onboarding.request_church') }}
                        </h2>
                    </div>
                    <button type="button" @click="churchModalOpen = false">
                        <X class="size-5" />
                    </button>
                </div>
                <select
                    v-model="churchForm.community_id"
                    required
                    class="w-full rounded-xl border-slate-300"
                >
                    <option value="" disabled>
                        {{ t('portal.fields.community') }}
                    </option>
                    <option
                        v-for="community in availableCommunities"
                        :key="community.id"
                        :value="community.id"
                    >
                        {{ community.name }}
                    </option>
                </select>
                <div class="grid gap-4 sm:grid-cols-2">
                    <input
                        v-model="churchForm.name"
                        required
                        class="rounded-xl border-slate-300"
                        :placeholder="t('portal.fields.name')"
                    /><input
                        v-model="churchForm.slug"
                        required
                        class="rounded-xl border-slate-300"
                        :placeholder="t('portal.fields.slug')"
                    />
                    <div class="space-y-2">
                        <div class="grid gap-2 sm:grid-cols-[11rem_1fr]">
                            <select
                                v-model="domainMode"
                                class="rounded-xl border-slate-300"
                            >
                                <option value="subdomain">
                                    {{ t('portal.domain.subdomain') }}
                                </option>
                                <option value="external">
                                    {{ t('portal.domain.external') }}
                                </option>
                            </select>
                            <input
                                v-model="domainInput"
                                required
                                class="rounded-xl border-slate-300"
                                :placeholder="
                                    domainMode === 'subdomain'
                                        ? t(
                                              'portal.domain.subdomain_placeholder',
                                          )
                                        : t('portal.fields.domain')
                                "
                            />
                        </div>
                        <p class="text-xs text-slate-500">
                            {{
                                domainMode === 'subdomain'
                                    ? requestedDomain || 'subdominio'
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
                    <input
                        v-model="churchForm.found_date"
                        type="date"
                        class="rounded-xl border-slate-300"
                    /><input
                        v-model="churchForm.contact_email"
                        type="email"
                        class="rounded-xl border-slate-300"
                        :placeholder="t('portal.fields.contact_email')"
                    /><input
                        v-model="churchForm.contact_phone"
                        class="rounded-xl border-slate-300"
                        :placeholder="t('portal.fields.contact_phone')"
                    />
                </div>
                <textarea
                    v-model="churchForm.description"
                    rows="3"
                    class="w-full rounded-xl border-slate-300"
                    :placeholder="t('portal.fields.description')"
                /><textarea
                    v-model="churchForm.address"
                    rows="2"
                    class="w-full rounded-xl border-slate-300"
                    :placeholder="t('portal.fields.address')"
                />
                <p class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                    {{ t('portal.onboarding.approval_notice') }}
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
        </div>
    </div>
</template>
