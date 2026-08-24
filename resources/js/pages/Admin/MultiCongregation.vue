<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Building2,
    Check,
    ClipboardCheck,
    Network as NetworkIcon,
    Pencil,
    Plus,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import axios from 'axios';
import { ref } from 'vue';
import AppModal from '@/components/AppModal.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useTerminology } from '@/composables/useTerminology';
import { useI18n } from '@/lib/i18n';

type Community = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    churches_count: number;
};

type Church = {
    id: string;
    name: string;
    slug: string;
    domain: string | null;
    status: string;
    community?: { id: string; name: string } | null;
    members_count: number;
    logo_url?: string | null;
    icon_url?: string | null;
};

type RegistrationRequest = {
    id: string;
    name: string;
    domain: string;
    description?: string | null;
    address?: string | null;
    status: string;
    community: { name: string };
    requester: { name: string; email: string };
};

type Network = {
    id: string;
    parent_church?: { name: string };
    child_church?: { name: string };
};

const props = defineProps<{
    churches: Church[];
    communities: Community[];
    networks: Network[];
    registrationRequests: RegistrationRequest[];
    stats: Array<{ label: string; value: number }>;
    canManageCommunities: boolean;
    canChangeChurchCommunity: boolean;
}>();

const { t } = useI18n();
const { confirm, prompt } = useConfirmDialog();
const { unitLabel } = useTerminology();
const churches = ref([...props.churches]);
const communities = ref([...props.communities]);
const tab = ref<'churches' | 'communities' | 'networks' | 'requests'>(
    'churches',
);
const churchModalOpen = ref(false);
const communityModalOpen = ref(false);
const editingChurch = ref<Church | null>(null);
const editingCommunity = ref<Community | null>(null);
const errorMessage = ref('');
const churchLogo = ref<File | null>(null);
const churchIcon = ref<File | null>(null);
const churchForm = ref({
    name: '',
    slug: '',
    domain: '',
    status: 'active',
    community_id: '',
});
const communityForm = ref({ name: '', slug: '', description: '' });

const openChurchEditor = (church?: Church): void => {
    editingChurch.value = church ?? null;
    churchForm.value = church
        ? {
              name: church.name,
              slug: church.slug,
              domain: church.domain ?? '',
              status: church.status,
              community_id: church.community?.id ?? '',
          }
        : {
              name: '',
              slug: '',
              domain: '',
              status: 'active',
              community_id: props.canManageCommunities
                  ? ''
                  : (communities.value[0]?.id ?? ''),
          };
    errorMessage.value = '';
    churchLogo.value = null;
    churchIcon.value = null;
    churchModalOpen.value = true;
};

const openCommunityEditor = (community?: Community): void => {
    editingCommunity.value = community ?? null;
    communityForm.value = community
        ? {
              name: community.name,
              slug: community.slug,
              description: community.description ?? '',
          }
        : { name: '', slug: '', description: '' };
    errorMessage.value = '';
    communityModalOpen.value = true;
};

const requestError = (error: unknown): string => {
    if (axios.isAxiosError(error)) {
        const errors = error.response?.data?.errors as
            Record<string, string[]> | undefined;

        return errors
            ? Object.values(errors).flat()[0]
            : (error.response?.data?.message ??
                  t('admin.multicongregation.save_error'));
    }

    return t('admin.multicongregation.save_error');
};

const saveChurch = async (): Promise<void> => {
    try {
        const payload = new FormData();
        payload.append('name', churchForm.value.name);
        payload.append('slug', churchForm.value.slug);
        payload.append('domain', churchForm.value.domain);
        payload.append('status', churchForm.value.status);

        if (props.canChangeChurchCommunity) {
            payload.append('community_id', churchForm.value.community_id);
        }

        if (churchLogo.value) {
            payload.append('logo', churchLogo.value);
        }

        if (churchIcon.value) {
            payload.append('icon', churchIcon.value);
        }

        if (editingChurch.value) {
            payload.append('_method', 'PUT');
            await axios.post(`/api/church/${editingChurch.value.id}`, payload);
        } else {
            await axios.post('/api/church', payload);
        }

        window.location.reload();
    } catch (error) {
        errorMessage.value = requestError(error);
    }
};

const selectChurchLogo = (event: Event): void => {
    churchLogo.value = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const selectChurchIcon = (event: Event): void => {
    churchIcon.value = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const removeChurch = async (church: Church): Promise<void> => {
    if (
        !(await confirm({
            message: t('admin.multicongregation.delete_church_confirm', {
                name: church.name,
            }),
            confirmLabel: t('actions.delete'),
            intent: 'danger',
        }))
    ) {
        return;
    }

    try {
        await axios.delete(`/api/church/${church.id}`);
        churches.value = churches.value.filter((item) => item.id !== church.id);
    } catch (error) {
        errorMessage.value = requestError(error);
    }
};

const saveCommunity = async (): Promise<void> => {
    try {
        if (editingCommunity.value) {
            await axios.put(
                `/api/community/${editingCommunity.value.id}`,
                communityForm.value,
            );
        } else {
            await axios.post('/api/community', communityForm.value);
        }

        window.location.reload();
    } catch (error) {
        errorMessage.value = requestError(error);
    }
};

const removeCommunity = async (community: Community): Promise<void> => {
    if (
        !(await confirm({
            message: t('admin.multicongregation.delete_community_confirm', {
                name: community.name,
            }),
            confirmLabel: t('actions.delete'),
            intent: 'danger',
        }))
    ) {
        return;
    }

    try {
        await axios.delete(`/api/community/${community.id}`);
        communities.value = communities.value.filter(
            (item) => item.id !== community.id,
        );
    } catch (error) {
        errorMessage.value = requestError(error);
    }
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
    <Head :title="t('admin.multicongregation.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="flex flex-col justify-between gap-5 rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white shadow-sm md:flex-row md:items-end"
        >
            <div>
                <p
                    class="text-xs font-bold tracking-[0.2em] text-violet-200 uppercase"
                >
                    {{ t('admin.multicongregation.kicker') }}
                </p>
                <h1 class="mt-2 text-3xl font-black">
                    {{ t('admin.multicongregation.title') }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-violet-100">
                    {{ t('admin.multicongregation.description') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-if="props.canManageCommunities"
                    class="inline-flex items-center gap-2 rounded-xl border border-white/30 px-4 py-3 text-sm font-black"
                    @click="openCommunityEditor()"
                >
                    <Users class="size-4" />
                    {{ t('admin.multicongregation.new_community') }}
                </button>
                <button
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-black text-violet-900"
                    @click="openChurchEditor()"
                >
                    <Plus class="size-4" />
                    {{
                        t('admin.multicongregation.new_unit', {
                            unit: unitLabel('branch'),
                        })
                    }}
                </button>
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-3">
            <article
                v-for="(stat, index) in props.stats"
                :key="stat.label"
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <p
                    class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                >
                    {{ t(`admin.multicongregation.stats.${index}`) }}
                </p>
                <p class="mt-2 text-3xl font-black">{{ stat.value }}</p>
            </article>
        </section>

        <p
            v-if="errorMessage"
            class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700"
        >
            {{ errorMessage }}
        </p>

        <div class="flex flex-wrap gap-5 border-b">
            <button
                class="flex items-center gap-2 px-2 py-3 text-sm"
                :class="
                    tab === 'requests'
                        ? 'border-b-2 border-indigo-700 font-black text-indigo-700'
                        : 'text-slate-400'
                "
                @click="tab = 'requests'"
            >
                <ClipboardCheck class="size-4" />
                {{ t('admin.multicongregation.requests') }}
                <span
                    v-if="
                        props.registrationRequests.filter(
                            (item) => item.status === 'pending',
                        ).length
                    "
                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-700"
                    >{{
                        props.registrationRequests.filter(
                            (item) => item.status === 'pending',
                        ).length
                    }}</span
                >
            </button>
            <button
                class="flex items-center gap-2 px-2 py-3 text-sm"
                :class="
                    tab === 'churches'
                        ? 'border-b-2 border-indigo-700 font-black text-indigo-700'
                        : 'text-slate-400'
                "
                @click="tab = 'churches'"
            >
                <Building2 class="size-4" />
                {{ unitLabel('branch', 'plural') }}
            </button>
            <button
                v-if="props.canManageCommunities"
                class="flex items-center gap-2 px-2 py-3 text-sm"
                :class="
                    tab === 'communities'
                        ? 'border-b-2 border-indigo-700 font-black text-indigo-700'
                        : 'text-slate-400'
                "
                @click="tab = 'communities'"
            >
                <Users class="size-4" />
                {{ t('admin.multicongregation.communities') }}
            </button>
            <button
                class="flex items-center gap-2 px-2 py-3 text-sm"
                :class="
                    tab === 'networks'
                        ? 'border-b-2 border-indigo-700 font-black text-indigo-700'
                        : 'text-slate-400'
                "
                @click="tab = 'networks'"
            >
                <NetworkIcon class="size-4" />
                {{ t('admin.multicongregation.networks') }}
            </button>
        </div>

        <section v-if="tab === 'churches'" class="grid gap-3 lg:grid-cols-2">
            <article
                v-for="church in churches"
                :key="church.id"
                class="flex items-center justify-between gap-4 rounded-2xl border bg-white p-5 shadow-sm"
            >
                <div>
                    <h2 class="font-bold">{{ church.name }}</h2>
                    <p class="text-sm text-slate-500">
                        {{
                            church.community?.name ??
                            t('admin.multicongregation.no_community')
                        }}
                        ·
                        {{
                            t('admin.multicongregation.members', {
                                count: church.members_count,
                            })
                        }}
                    </p>
                    <span
                        class="mt-2 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700 uppercase"
                        >{{
                            t(`admin.multicongregation.status.${church.status}`)
                        }}</span
                    >
                </div>
                <div class="flex gap-2">
                    <button
                        class="rounded-lg border p-2 text-indigo-700"
                        :aria-label="t('actions.edit')"
                        @click="openChurchEditor(church)"
                    >
                        <Pencil class="size-4" />
                    </button>
                    <button
                        class="rounded-lg border p-2 text-rose-600"
                        :aria-label="t('actions.delete')"
                        @click="removeChurch(church)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </article>
            <p
                v-if="!churches.length"
                class="rounded-2xl border border-dashed p-10 text-center text-sm text-slate-500"
            >
                {{
                    t('admin.multicongregation.empty_units', {
                        units: unitLabel('branch', 'plural').toLowerCase(),
                    })
                }}
            </p>
        </section>

        <section
            v-else-if="tab === 'communities'"
            class="grid gap-3 lg:grid-cols-2"
        >
            <article
                v-for="community in communities"
                :key="community.id"
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-bold">{{ community.name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ community.description }}
                        </p>
                        <p class="mt-3 text-xs font-bold text-indigo-700">
                            {{
                                t('admin.multicongregation.church_count', {
                                    count: community.churches_count,
                                })
                            }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            class="rounded-lg border p-2 text-indigo-700"
                            @click="openCommunityEditor(community)"
                        >
                            <Pencil class="size-4" /></button
                        ><button
                            class="rounded-lg border p-2 text-rose-600"
                            @click="removeCommunity(community)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
            </article>
        </section>

        <section v-else-if="tab === 'networks'" class="space-y-3">
            <article
                v-for="network in props.networks"
                :key="network.id"
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <p class="text-sm font-bold">
                    {{ network.parent_church?.name }}
                    <span class="mx-2 text-indigo-500">→</span>
                    {{ network.child_church?.name }}
                </p>
            </article>
            <p
                v-if="!props.networks.length"
                class="rounded-2xl border border-dashed p-10 text-center text-sm text-slate-500"
            >
                {{ t('admin.multicongregation.empty_networks') }}
            </p>
        </section>

        <section v-else class="space-y-3">
            <article
                v-for="request in props.registrationRequests"
                :key="request.id"
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <div class="flex flex-col justify-between gap-4 md:flex-row">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-black">{{ request.name }}</h2>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase"
                                :class="
                                    request.status === 'approved'
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : request.status === 'rejected'
                                          ? 'bg-rose-50 text-rose-700'
                                          : 'bg-amber-50 text-amber-700'
                                "
                                >{{
                                    t(`portal.status.${request.status}`)
                                }}</span
                            >
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ request.community.name }} ·
                            {{ request.domain }} ·
                            {{ request.requester.name }} ({{
                                request.requester.email
                            }})
                        </p>
                        <p class="mt-3 max-w-3xl text-sm text-slate-600">
                            {{ request.description }}
                        </p>
                        <p
                            v-if="request.address"
                            class="mt-2 text-xs text-slate-500"
                        >
                            {{ request.address }}
                        </p>
                    </div>
                    <div
                        v-if="request.status === 'pending'"
                        class="flex shrink-0 gap-2"
                    >
                        <button
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-black text-white"
                            @click="approveRequest(request)"
                        >
                            <Check class="size-4" />
                            {{ t('portal.review.approve') }}
                        </button>
                        <button
                            class="inline-flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-black text-rose-700"
                            @click="rejectRequest(request)"
                        >
                            <X class="size-4" /> {{ t('portal.review.reject') }}
                        </button>
                    </div>
                </div>
            </article>
            <p
                v-if="!props.registrationRequests.length"
                class="rounded-2xl border border-dashed p-10 text-center text-sm text-slate-500"
            >
                {{ t('admin.multicongregation.empty_requests') }}
            </p>
        </section>

        <AppModal
            v-model:open="churchModalOpen"
            :title="
                editingChurch
                    ? t('admin.multicongregation.edit_unit', {
                          unit: unitLabel('branch'),
                              })
                    : t('admin.multicongregation.new_unit', {
                          unit: unitLabel('branch'),
                      })
            "
            scrollable
        >
            <form class="space-y-4" @submit.prevent="saveChurch">
                <input
                    v-model="churchForm.name"
                    required
                    class="w-full rounded-lg border-slate-300"
                    :placeholder="t('admin.common.name')"
                />
                <input
                    v-model="churchForm.slug"
                    required
                    class="w-full rounded-lg border-slate-300"
                    :placeholder="t('admin.common.slug')"
                />
                <input
                    v-model="churchForm.domain"
                    class="w-full rounded-lg border-slate-300"
                    :placeholder="t('admin.multicongregation.domain')"
                />
                <select
                    v-model="churchForm.status"
                    class="w-full rounded-lg border-slate-300"
                >
                    <option value="active">
                        {{ t('admin.multicongregation.status.active') }}
                    </option>
                    <option value="inactive">
                        {{ t('admin.multicongregation.status.inactive') }}
                    </option>
                    <option value="closed">
                        {{ t('admin.multicongregation.status.closed') }}
                    </option>
                </select>
                <select
                    v-if="props.canChangeChurchCommunity"
                    v-model="churchForm.community_id"
                    class="w-full rounded-lg border-slate-300"
                >
                    <option value="">
                        {{ t('admin.multicongregation.no_community') }}
                    </option>
                    <option
                        v-for="community in communities"
                        :key="community.id"
                        :value="community.id"
                    >
                        {{ community.name }}
                    </option>
                </select>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="space-y-2 text-sm font-bold text-slate-700">
                        <span>{{ t('admin.multicongregation.logo') }}</span>
                        <img
                            v-if="editingChurch?.logo_url"
                            :src="editingChurch.logo_url"
                            :alt="t('admin.multicongregation.logo_preview')"
                            class="size-16 rounded-xl border object-contain p-1"
                        />
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-xs font-normal"
                            @change="selectChurchLogo"
                        />
                    </label>
                    <label class="space-y-2 text-sm font-bold text-slate-700">
                        <span>{{ t('admin.multicongregation.icon') }}</span>
                        <img
                            v-if="editingChurch?.icon_url"
                            :src="editingChurch.icon_url"
                            :alt="t('admin.multicongregation.icon_preview')"
                            class="size-16 rounded-xl border object-contain p-1"
                        />
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-xs font-normal"
                            @change="selectChurchIcon"
                        />
                    </label>
                </div>
                <p
                    v-if="errorMessage"
                    class="text-sm font-semibold text-rose-600"
                >
                    {{ errorMessage }}
                </p>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-slate-500"
                        @click="churchModalOpen = false"
                    >
                        {{ t('actions.cancel') }}</button
                    ><button
                        class="rounded-lg bg-violet-700 px-4 py-2 text-sm font-bold text-white"
                    >
                        {{ t('actions.save') }}
                    </button>
                </div>
            </form>
        </AppModal>

        <AppModal
            v-model:open="communityModalOpen"
            :title="
                editingCommunity
                    ? t('admin.multicongregation.edit_community')
                    : t('admin.multicongregation.new_community')
            "
            scrollable
        >
            <form class="space-y-4" @submit.prevent="saveCommunity">
                <input
                    v-model="communityForm.name"
                    required
                    class="w-full rounded-lg border-slate-300"
                    :placeholder="t('admin.common.name')"
                />
                <input
                    v-model="communityForm.slug"
                    required
                    class="w-full rounded-lg border-slate-300"
                    :placeholder="t('admin.common.slug')"
                />
                <textarea
                    v-model="communityForm.description"
                    required
                    rows="4"
                    class="w-full rounded-lg border-slate-300"
                    :placeholder="
                        t('admin.multicongregation.community_description')
                    "
                />
                <p
                    v-if="errorMessage"
                    class="text-sm font-semibold text-rose-600"
                >
                    {{ errorMessage }}
                </p>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-slate-500"
                        @click="communityModalOpen = false"
                    >
                        {{ t('actions.cancel') }}</button
                    ><button
                        class="rounded-lg bg-violet-700 px-4 py-2 text-sm font-bold text-white"
                    >
                        {{ t('actions.save') }}
                    </button>
                </div>
            </form>
        </AppModal>
    </main>
</template>
