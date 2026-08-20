<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Baby,
    BookHeart,
    CalendarDays,
    DoorOpen,
    HeartHandshake,
    KeyRound,
    Plus,
    Send,
    ShieldCheck,
    Trash2,
    UserRound,
} from '@lucide/vue';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';
import { index as eventsIndex, show as eventsShow } from '@/routes/events';

type Person = {
    id: string;
    first_name: string;
    last_name: string;
    birth_date?: string | null;
    profile?: {
        phone?: string | null;
        gender?: string | null;
        avatar_path?: string | null;
        avatar_url?: string | null;
        community_id?: string | null;
        church_id?: string | null;
        medical_notes?: string | null;
    };
};
type Relationship = {
    id: number;
    relationship_type: string;
    related_user?: Person;
    user?: Person;
};
type WorkspaceUser = Person & {
    email: string;
    name: string;
    avatar?: string | null;
    registered_events: Array<{
        id: string;
        title: string;
        slug: string;
        start_time: string;
        pivot?: { status: string };
    }>;
    classrooms: Array<{
        id: string;
        name: string;
        is_kids: boolean;
        min_age: number | null;
        max_age: number | null;
    }>;
    relationships: Relationship[];
    related_relationships: Relationship[];
};
type CommunityOption = {
    id: string;
    name: string;
    churches: Array<{ id: string; name: string; slug: string }>;
};
type PendingCheckout = {
    id: string;
    user_id: string;
    child_name: string;
    classroom_name: string;
    check_in: string;
    checkout_pin: string | null;
};
const props = defineProps<{
    workspaceUser: WorkspaceUser;
    prayerRequests: Array<{ id: string; content: string; created_at: string }>;
    churchMembers: Person[];
    mustVerifyEmail: boolean;
    status?: string;
    communities: CommunityOption[];
    pendingChildCheckouts: PendingCheckout[];
}>();
const { t } = useI18n();
const activeTab = ref('profile');
const saving = ref(false);
const relationUserId = ref('');
const relationType = ref('parent');
const relationSaving = ref(false);
const prayerContent = ref('');
const prayerSaving = ref(false);
const childSaving = ref(false);
const profile = reactive({
    name: props.workspaceUser.name,
    email: props.workspaceUser.email,
    birth_date: props.workspaceUser.birth_date ?? '',
    phone: props.workspaceUser.profile?.phone ?? '',
    gender: props.workspaceUser.profile?.gender ?? '',
    avatar: null as File | null,
    community_id: props.workspaceUser.profile?.community_id ?? '',
    church_id: props.workspaceUser.profile?.church_id ?? '',
});
const childForm = reactive({
    first_name: '',
    last_name: '',
    birth_date: '',
    gender: '',
    medical_notes: '',
    avatar: null as File | null,
});
const tabs = computed(() => [
    { id: 'profile', label: t('settings.workspace.profile'), icon: UserRound },
    { id: 'events', label: t('settings.workspace.events'), icon: CalendarDays },
    {
        id: 'classrooms',
        label: t('settings.workspace.classrooms'),
        icon: DoorOpen,
    },
    { id: 'prayers', label: t('settings.workspace.prayers'), icon: BookHeart },
    {
        id: 'relationships',
        label: t('settings.workspace.relationships'),
        icon: HeartHandshake,
    },
]);
const relationships = computed(() => props.workspaceUser.relationships);
const children = computed(() =>
    relationships.value.filter((item) => item.relationship_type === 'parent'),
);
const authorizedAdults = computed(() =>
    relationships.value.filter((item) => item.relationship_type !== 'parent'),
);
const availableChurches = computed(
    () =>
        props.communities.find((item) => item.id === profile.community_id)
            ?.churches ?? [],
);
const saveProfile = (): void => {
    saving.value = true;
    router.post(
        '/settings/profile',
        { ...profile, _method: 'patch' },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
};
const selectAvatar = (event: Event): void => {
    profile.avatar = (event.target as HTMLInputElement).files?.[0] ?? null;
};
const selectChildAvatar = (event: Event): void => {
    childForm.avatar = (event.target as HTMLInputElement).files?.[0] ?? null;
};
const changeCommunity = (): void => {
    if (
        !availableChurches.value.some(
            (church) => church.id === profile.church_id,
        )
    ) {
        profile.church_id = '';
    }
};
const submitPrayer = async (): Promise<void> => {
    if (!prayerContent.value.trim()) {
        return;
    }

    prayerSaving.value = true;

    try {
        await axios.post('/api/prayer-requests', {
            content: prayerContent.value,
            is_anonymous: false,
        });
        window.location.reload();
    } finally {
        prayerSaving.value = false;
    }
};
const addChild = async (): Promise<void> => {
    childSaving.value = true;

    try {
        await axios.post('/settings/children', childForm, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        window.location.reload();
    } finally {
        childSaving.value = false;
    }
};
const pendingCheckoutFor = (userId?: string): PendingCheckout | undefined =>
    props.pendingChildCheckouts.find((presence) => presence.user_id === userId);
const addRelationship = async (): Promise<void> => {
    if (!relationUserId.value) {
        return;
    }

    relationSaving.value = true;

    try {
        await axios.post(`/settings/relationships/${props.workspaceUser.id}`, {
            related_user_id: relationUserId.value,
            relationship_type: relationType.value,
        });
        window.location.reload();
    } finally {
        relationSaving.value = false;
    }
};
const removeRelationship = async (item: Relationship): Promise<void> => {
    if (!confirm(t('settings.workspace.remove_relationship_confirm'))) {
        return;
    }

    await axios.delete(`/settings/relationships/${item.id}`);
    window.location.reload();
};
const personFor = (item: Relationship): Person | undefined =>
    item.related_user ?? item.user;
</script>

<template>
    <Head :title="t('settings.workspace.title')" />
    <div class="flex min-h-screen flex-col bg-slate-50 text-slate-950">
        <PublicHeader />
        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
            <header
                class="overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-6 text-white shadow-sm"
            >
                <div class="flex items-center gap-4">
                    <img
                        v-if="workspaceUser.avatar"
                        :src="workspaceUser.avatar"
                        class="size-16 rounded-xl border-2 border-white/30 object-cover"
                    />
                    <div
                        v-else
                        class="grid size-16 place-items-center rounded-xl bg-white/15 text-xl font-black"
                    >
                        {{ workspaceUser.first_name?.[0]
                        }}{{ workspaceUser.last_name?.[0] }}
                    </div>
                    <div>
                        <p
                            class="font-mono text-[10px] font-bold tracking-widest text-amber-300 uppercase"
                        >
                            {{ t('settings.workspace.account') }}
                        </p>
                        <h1 class="mt-1 text-2xl font-black">
                            {{ workspaceUser.name }}
                        </h1>
                        <p class="text-sm text-indigo-100">
                            {{ workspaceUser.email }}
                        </p>
                    </div>
                </div>
            </header>
            <nav
                class="mt-5 flex gap-1 overflow-x-auto border-b border-slate-200"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    :class="[
                        'inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-bold',
                        activeTab === tab.id
                            ? 'border-indigo-600 text-indigo-700'
                            : 'border-transparent text-slate-500',
                    ]"
                    @click="activeTab = tab.id"
                >
                    <component :is="tab.icon" class="size-4" />{{ tab.label }}
                </button>
            </nav>

            <section
                v-if="activeTab === 'profile'"
                class="mt-6 max-w-3xl rounded-2xl border bg-white p-6 shadow-sm"
            >
                <h2 class="text-xl font-black">
                    {{ t('settings.workspace.profile') }}
                </h2>
                <form
                    class="mt-5 grid gap-4 sm:grid-cols-2"
                    @submit.prevent="saveProfile"
                >
                    <label
                        class="text-xs font-bold text-slate-600 sm:col-span-2"
                        >{{ t('settings.profile.name')
                        }}<input
                            v-model="profile.name"
                            required
                            class="mt-1 w-full rounded-lg border-slate-300" /></label
                    ><label
                        class="text-xs font-bold text-slate-600 sm:col-span-2"
                        >{{ t('auth.common.email')
                        }}<input
                            v-model="profile.email"
                            required
                            type="email"
                            class="mt-1 w-full rounded-lg border-slate-300" /></label
                    ><label class="text-xs font-bold text-slate-600"
                        >{{ t('settings.profile.birth_date')
                        }}<input
                            v-model="profile.birth_date"
                            type="date"
                            class="mt-1 w-full rounded-lg border-slate-300" /></label
                    ><label class="text-xs font-bold text-slate-600"
                        >{{ t('settings.profile.phone')
                        }}<input
                            v-model="profile.phone"
                            class="mt-1 w-full rounded-lg border-slate-300" /></label
                    ><label class="text-xs font-bold text-slate-600"
                        >{{ t('settings.profile.gender')
                        }}<select
                            v-model="profile.gender"
                            class="mt-1 w-full rounded-lg border-slate-300"
                        >
                            <option value="">—</option>
                            <option value="female">
                                {{ t('admin.classrooms.gender.female') }}
                            </option>
                            <option value="male">
                                {{ t('admin.classrooms.gender.male') }}
                            </option>
                            <option value="other">
                                {{ t('settings.profile.other') }}
                            </option>
                        </select></label
                    >
                    <label class="text-xs font-bold text-slate-600"
                        >{{ t('settings.workspace.community')
                        }}<select
                            v-model="profile.community_id"
                            class="mt-1 w-full rounded-lg border-slate-300"
                            @change="changeCommunity"
                        >
                            <option value="">
                                {{ t('settings.workspace.select_community') }}
                            </option>
                            <option
                                v-for="community in communities"
                                :key="community.id"
                                :value="community.id"
                            >
                                {{ community.name }}
                            </option>
                        </select></label
                    ><label class="text-xs font-bold text-slate-600"
                        >{{ t('settings.workspace.church')
                        }}<select
                            v-model="profile.church_id"
                            :disabled="!profile.community_id"
                            class="mt-1 w-full rounded-lg border-slate-300 disabled:bg-slate-100"
                        >
                            <option value="">
                                {{ t('settings.workspace.select_church') }}
                            </option>
                            <option
                                v-for="church in availableChurches"
                                :key="church.id"
                                :value="church.id"
                            >
                                {{ church.name }}
                            </option>
                        </select></label
                    >
                    <label
                        class="text-xs font-bold text-slate-600 sm:col-span-2"
                        >{{ t('settings.workspace.avatar')
                        }}<input
                            type="file"
                            accept="image/*"
                            class="mt-1 w-full rounded-lg border p-2 text-sm"
                            @change="selectAvatar"
                    /></label>
                    <div class="sm:col-span-2">
                        <button
                            :disabled="saving"
                            class="rounded-lg bg-indigo-600 px-5 py-3 text-sm font-black text-white disabled:opacity-50"
                        >
                            {{
                                saving
                                    ? t('admin.common.saving')
                                    : t('settings.profile.save')
                            }}
                        </button>
                    </div>
                </form>
            </section>

            <section
                v-else-if="activeTab === 'events'"
                class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <Link
                    :href="eventsIndex()"
                    class="flex items-center justify-between rounded-2xl bg-indigo-700 p-5 font-black text-white shadow-sm md:col-span-2 xl:col-span-3"
                >
                    <span>{{ t('settings.workspace.browse_events') }}</span>
                    <CalendarDays class="size-5" />
                </Link>
                <Link
                    v-for="event in workspaceUser.registered_events"
                    :key="event.id"
                    :href="eventsShow({ event: event.slug })"
                    class="rounded-2xl border bg-white p-5 shadow-sm hover:border-indigo-300"
                    ><CalendarDays class="size-5 text-indigo-600" />
                    <h2 class="mt-3 font-black">{{ event.title }}</h2>
                    <p class="mt-2 text-xs text-slate-500">
                        {{ new Date(event.start_time).toLocaleString() }}
                    </p>
                    <span
                        class="mt-3 inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-700"
                        >{{ event.pivot?.status }}</span
                    ></Link
                >
                <p
                    v-if="!workspaceUser.registered_events.length"
                    class="rounded-2xl border border-dashed p-10 text-center text-sm text-slate-500"
                >
                    {{ t('settings.workspace.no_events') }}
                </p>
            </section>

            <section
                v-else-if="activeTab === 'classrooms'"
                class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="room in workspaceUser.classrooms"
                    :key="room.id"
                    class="rounded-2xl border bg-white p-5 shadow-sm"
                >
                    <Baby
                        v-if="room.is_kids"
                        class="size-5 text-amber-600"
                    /><DoorOpen v-else class="size-5 text-indigo-600" />
                    <h2 class="mt-3 font-black">{{ room.name }}</h2>
                    <p class="mt-2 text-xs text-slate-500">
                        {{ room.min_age ?? 0 }}–{{ room.max_age ?? '∞' }}
                        {{ t('admin.classrooms.years') }}
                    </p>
                </article>
                <p
                    v-if="!workspaceUser.classrooms.length"
                    class="rounded-2xl border border-dashed p-10 text-center text-sm text-slate-500"
                >
                    {{ t('settings.workspace.no_classrooms') }}
                </p>
            </section>

            <section
                v-else-if="activeTab === 'prayers'"
                class="mt-6 max-w-4xl space-y-3"
            >
                <form
                    class="rounded-2xl border bg-white p-5 shadow-sm"
                    @submit.prevent="submitPrayer"
                >
                    <div
                        class="flex items-center gap-2 font-black text-slate-900"
                    >
                        <BookHeart class="size-5 text-rose-500" />
                        {{ t('settings.workspace.new_prayer') }}
                    </div>
                    <textarea
                        v-model="prayerContent"
                        required
                        rows="4"
                        :placeholder="
                            t('settings.workspace.prayer_placeholder')
                        "
                        class="mt-4 w-full rounded-xl border-slate-300 text-sm"
                    />
                    <button
                        :disabled="prayerSaving"
                        class="mt-3 inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-black text-white disabled:opacity-50"
                    >
                        <Send class="size-4" />{{
                            t('settings.workspace.send_prayer')
                        }}
                    </button>
                </form>
                <article
                    v-for="prayer in prayerRequests"
                    :key="prayer.id"
                    class="rounded-2xl border bg-white p-5 shadow-sm"
                >
                    <BookHeart class="size-5 text-rose-500" />
                    <p
                        class="mt-3 text-sm leading-6 whitespace-pre-wrap text-slate-700"
                    >
                        {{ prayer.content }}
                    </p>
                    <time class="mt-3 block text-xs text-slate-400">{{
                        new Date(prayer.created_at).toLocaleString()
                    }}</time>
                </article>
                <p
                    v-if="!prayerRequests.length"
                    class="rounded-2xl border border-dashed p-10 text-center text-sm text-slate-500"
                >
                    {{ t('settings.workspace.no_prayers') }}
                </p>
            </section>

            <section
                v-else
                class="mt-6 rounded-2xl border bg-white p-5 shadow-sm md:p-6"
            >
                <header class="border-b border-slate-100 pb-4">
                    <div
                        class="flex items-center gap-2 font-black text-slate-950"
                    >
                        <HeartHandshake class="size-5 text-indigo-600" />
                        {{ t('settings.workspace.family_management') }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ t('settings.workspace.family_management_hint') }}
                    </p>
                </header>

                <div class="mt-5 grid gap-6 lg:grid-cols-2">
                    <div class="space-y-4">
                        <div
                            class="flex items-center gap-2 border-b pb-3 text-sm font-black"
                        >
                            <ShieldCheck class="size-4 text-emerald-600" />
                            {{ t('settings.workspace.authorized_adults') }}
                        </div>
                        <article
                            v-for="relation in authorizedAdults"
                            :key="relation.id"
                            class="flex items-center gap-3 rounded-xl border bg-slate-50 p-3"
                        >
                            <div
                                class="grid size-10 place-items-center rounded-full bg-white font-black text-indigo-700"
                            >
                                {{ personFor(relation)?.first_name?.[0] }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-black">
                                    {{ personFor(relation)?.first_name }}
                                    {{ personFor(relation)?.last_name }}
                                </p>
                                <p
                                    class="text-[10px] font-bold text-emerald-600 uppercase"
                                >
                                    {{
                                        t(
                                            `settings.workspace.relationship_types.${relation.relationship_type}`,
                                        )
                                    }}
                                </p>
                            </div>
                            <button
                                class="rounded-lg p-2 text-rose-600"
                                @click="removeRelationship(relation)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </article>
                        <p
                            v-if="!authorizedAdults.length"
                            class="rounded-xl border border-dashed p-6 text-center text-xs text-slate-400"
                        >
                            {{ t('settings.workspace.no_authorized_adults') }}
                        </p>

                        <form
                            class="space-y-3 rounded-xl border bg-slate-50 p-4"
                            @submit.prevent="addRelationship"
                        >
                            <h3
                                class="flex items-center gap-2 text-xs font-black uppercase"
                            >
                                <Plus class="size-4 text-indigo-600" />{{
                                    t('settings.workspace.authorize_adult')
                                }}
                            </h3>
                            <select
                                v-model="relationUserId"
                                required
                                class="w-full rounded-lg border-slate-300 text-sm"
                            >
                                <option value="">
                                    {{ t('settings.workspace.select_person') }}
                                </option>
                                <option
                                    v-for="member in churchMembers"
                                    :key="member.id"
                                    :value="member.id"
                                >
                                    {{ member.first_name }}
                                    {{ member.last_name }}
                                </option>
                            </select>
                            <select
                                v-model="relationType"
                                class="w-full rounded-lg border-slate-300 text-sm"
                            >
                                <option value="spouse">
                                    {{
                                        t(
                                            'settings.workspace.relationship_types.spouse',
                                        )
                                    }}
                                </option>
                                <option value="child">
                                    {{ t('settings.workspace.child_of') }}
                                </option>
                            </select>
                            <button
                                :disabled="relationSaving"
                                class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-black text-white"
                            >
                                {{ t('settings.workspace.authorize') }}
                            </button>
                        </form>
                    </div>

                    <div class="space-y-4">
                        <div
                            class="flex items-center gap-2 border-b pb-3 text-sm font-black"
                        >
                            <Baby class="size-4 text-indigo-600" />
                            {{ t('settings.workspace.registered_children') }}
                        </div>
                        <article
                            v-for="relation in children"
                            :key="relation.id"
                            class="rounded-xl border bg-slate-50 p-4"
                        >
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="
                                        personFor(relation)?.profile?.avatar_url
                                    "
                                    :src="
                                        personFor(relation)?.profile
                                            ?.avatar_url || undefined
                                    "
                                    class="size-11 rounded-full object-cover"
                                />
                                <div
                                    v-else
                                    class="grid size-11 place-items-center rounded-full bg-indigo-100 font-black text-indigo-700"
                                >
                                    {{ personFor(relation)?.first_name?.[0] }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-black">
                                        {{ personFor(relation)?.first_name }}
                                        {{ personFor(relation)?.last_name }}
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {{ personFor(relation)?.birth_date }}
                                    </p>
                                </div>
                                <button
                                    class="rounded-lg p-2 text-rose-600"
                                    @click="removeRelationship(relation)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                            <p
                                v-if="
                                    personFor(relation)?.profile?.medical_notes
                                "
                                class="mt-3 rounded-lg bg-amber-50 p-2 text-xs text-amber-800"
                            >
                                {{
                                    personFor(relation)?.profile?.medical_notes
                                }}
                            </p>
                            <div
                                v-if="
                                    pendingCheckoutFor(personFor(relation)?.id)
                                "
                                class="mt-3 rounded-xl bg-indigo-950 p-3 text-white"
                            >
                                <p
                                    class="flex items-center gap-2 text-[10px] font-bold text-amber-300 uppercase"
                                >
                                    <KeyRound class="size-4" />{{
                                        t('settings.workspace.checkout_pin')
                                    }}
                                </p>
                                <div
                                    class="mt-2 flex items-end justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="font-mono text-2xl font-black tracking-[0.25em]"
                                        >
                                            {{
                                                pendingCheckoutFor(
                                                    personFor(relation)?.id,
                                                )?.checkout_pin || '------'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-[10px] text-indigo-200"
                                        >
                                            {{
                                                pendingCheckoutFor(
                                                    personFor(relation)?.id,
                                                )?.classroom_name
                                            }}
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full bg-emerald-400/15 px-2 py-1 text-[10px] font-bold text-emerald-300"
                                    >
                                        {{ t('settings.workspace.checked_in') }}
                                    </span>
                                </div>
                            </div>
                        </article>
                        <p
                            v-if="!children.length"
                            class="rounded-xl border border-dashed p-8 text-center text-xs text-slate-400"
                        >
                            {{ t('settings.workspace.no_children') }}
                        </p>

                        <form
                            class="space-y-3 rounded-xl border bg-slate-50 p-4"
                            @submit.prevent="addChild"
                        >
                            <h3
                                class="flex items-center gap-2 text-xs font-black uppercase"
                            >
                                <Plus class="size-4 text-indigo-600" />{{
                                    t('settings.workspace.add_child')
                                }}
                            </h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input
                                    v-model="childForm.first_name"
                                    required
                                    :placeholder="
                                        t('settings.workspace.first_name')
                                    "
                                    class="rounded-lg border-slate-300 text-sm"
                                />
                                <input
                                    v-model="childForm.last_name"
                                    required
                                    :placeholder="
                                        t('settings.workspace.last_name')
                                    "
                                    class="rounded-lg border-slate-300 text-sm"
                                />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input
                                    v-model="childForm.birth_date"
                                    required
                                    type="date"
                                    class="rounded-lg border-slate-300 text-sm"
                                />
                                <select
                                    v-model="childForm.gender"
                                    class="rounded-lg border-slate-300 text-sm"
                                >
                                    <option value="">
                                        {{ t('settings.workspace.gender') }}
                                    </option>
                                    <option value="female">
                                        {{
                                            t('admin.classrooms.gender.female')
                                        }}
                                    </option>
                                    <option value="male">
                                        {{ t('admin.classrooms.gender.male') }}
                                    </option>
                                    <option value="other">
                                        {{ t('settings.workspace.other') }}
                                    </option>
                                </select>
                            </div>
                            <input
                                type="file"
                                accept="image/*"
                                class="w-full rounded-lg border bg-white p-2 text-xs"
                                @change="selectChildAvatar"
                            />
                            <textarea
                                v-model="childForm.medical_notes"
                                rows="3"
                                :placeholder="
                                    t('settings.workspace.medical_notes')
                                "
                                class="w-full rounded-lg border-slate-300 text-sm"
                            />
                            <button
                                :disabled="childSaving"
                                class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-xs font-black text-white"
                            >
                                {{ t('settings.workspace.register_child') }}
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </main>
        <PublicFooter />
    </div>
</template>
