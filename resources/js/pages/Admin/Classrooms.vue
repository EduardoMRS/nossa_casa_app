<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Baby,
    ArrowLeft,
    DoorOpen,
    Filter,
    Pencil,
    Plus,
    Tags,
    UserCheck,
    UserMinus,
} from '@lucide/vue';
import axios from 'axios';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import CategoryManagerModal from '@/components/CategoryManagerModal.vue';
import type { ManagedCategory } from '@/components/CategoryManagerModal.vue';
import { useI18n } from '@/lib/i18n';

type MemberRelationship = {
    relationship_type: string;
    related_user?: Member;
    user?: Member;
};
type Member = {
    id: string;
    first_name: string;
    last_name: string;
    birth_date: string | null;
    profile?: {
        gender: string | null;
        phone?: string | null;
        avatar_path?: string | null;
    };
    relationships?: MemberRelationship[];
    related_relationships?: MemberRelationship[];
};
type Classroom = {
    id: string;
    name: string;
    description: string | null;
    min_age: number | null;
    max_age: number | null;
    gender_restriction: string | null;
    is_kids: boolean;
    max_members: number | null;
    members: Member[];
    active_member_ids: string[];
    active_presences_count: number;
};
const props = defineProps<{
    classrooms: Classroom[];
    members: Member[];
    kidsOnly?: boolean;
    separateKidsMinistry: boolean;
    categories: ManagedCategory[];
}>();
const { t } = useI18n();
const rooms = ref(
    props.classrooms.map((room) => ({
        ...room,
        active_member_ids: [...(room.active_member_ids ?? [])],
    })),
);
const selected = ref<Classroom | null>(null);
const openedId = ref('');
const editorOpen = ref(false);
const categoriesOpen = ref(false);
const selectedMember = ref<Member | null>(null);
const error = ref('');
const filterGender = ref('');
const filterAge = ref<number | null>(null);
const filterKids = ref(props.kidsOnly ? 'kids' : 'all');
const openedRoom = computed(
    () => rooms.value.find((room) => room.id === openedId.value) ?? null,
);
const filteredRooms = computed(() =>
    rooms.value.filter((room) => {
        const matchesGender =
            !filterGender.value ||
            !room.gender_restriction ||
            room.gender_restriction === filterGender.value;
        const matchesAge =
            filterAge.value === null ||
            ((room.min_age === null || filterAge.value >= room.min_age) &&
                (room.max_age === null || filterAge.value <= room.max_age));
        const matchesKids =
            filterKids.value === 'all' ||
            (filterKids.value === 'kids' ? room.is_kids : !room.is_kids);

        return matchesGender && matchesAge && matchesKids;
    }),
);
const form = ref({
    name: '',
    description: '',
    min_age: null as number | null,
    max_age: null as number | null,
    gender_restriction: '',
    is_kids: props.kidsOnly ?? false,
    max_members: null as number | null,
    member_ids: [] as string[],
});

function edit(item: Classroom | null): void {
    selected.value = item;
    error.value = '';
    form.value = item
        ? {
              name: item.name,
              description: item.description ?? '',
              min_age: item.min_age,
              max_age: item.max_age,
              gender_restriction: item.gender_restriction ?? '',
              is_kids: item.is_kids,
              max_members: item.max_members,
              member_ids: item.members.map((member) => member.id),
          }
        : {
              name: '',
              description: '',
              min_age: null,
              max_age: null,
              gender_restriction: '',
              is_kids: props.kidsOnly ?? false,
              max_members: null,
              member_ids: [],
          };
    editorOpen.value = true;
}
async function save(): Promise<void> {
    try {
        const payload = {
            ...form.value,
            gender_restriction: form.value.gender_restriction || null,
        };

        if (selected.value) {
            await axios.put(`/api/classrooms/${selected.value.id}`, payload);
        } else {
            await axios.post('/api/classrooms', payload);
        }

        router.reload();
    } catch (caught) {
        error.value = axios.isAxiosError(caught)
            ? Object.values(caught.response?.data?.errors ?? {})
                  .flat()
                  .join(' ')
            : t('admin.classrooms.save_error');
    }
}
async function checkIn(room: Classroom, member: Member): Promise<void> {
    try {
        const { data } = await axios.post(
            `/api/classrooms/${room.id}/check-in`,
            { user_id: member.id },
        );

        if (!room.active_member_ids.includes(member.id)) {
            room.active_member_ids.push(member.id);
            room.active_presences_count++;
        }

        const message = data.checkout_pin
            ? t('admin.classrooms.pin_generated', {
                  name: member.first_name,
                  pin: data.checkout_pin,
              })
            : t('admin.classrooms.checkin_success');

        if (data.checkout_pin) {
            toast.warning(message, { duration: 20000 });
        } else {
            toast.success(message);
        }
    } catch (caught) {
        toast.error(
            axios.isAxiosError(caught)
                ? Object.values(caught.response?.data?.errors ?? {})
                      .flat()
                      .join(' ')
                : t('admin.classrooms.checkin_error'),
        );
    }
}
async function checkOut(room: Classroom, member: Member): Promise<void> {
    const pin = room.is_kids
        ? window.prompt(
              t('admin.classrooms.pin_prompt', { name: member.first_name }),
          )
        : '';

    if (room.is_kids && pin === null) {
        return;
    }

    try {
        await axios.post(`/api/classrooms/${room.id}/check-out`, {
            user_id: member.id,
            pin,
        });
        room.active_member_ids = room.active_member_ids.filter(
            (id) => id !== member.id,
        );
        room.active_presences_count = Math.max(
            0,
            room.active_presences_count - 1,
        );
        toast.success(t('admin.classrooms.checkout_success'));
    } catch (caught) {
        toast.error(
            axios.isAxiosError(caught)
                ? Object.values(caught.response?.data?.errors ?? {})
                      .flat()
                      .join(' ')
                : t('admin.classrooms.checkout_error'),
        );
    }
}
function updateSeparation(value: boolean): void {
    router.put(
        '/admin/salas-aula/configuracoes',
        { separate_kids_ministry: value },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head
        :title="
            kidsOnly
                ? t('admin.classrooms.kids_title')
                : t('admin.classrooms.title')
        "
    />
    <main class="space-y-6 p-4 xl:p-6">
        <section v-if="!openedRoom" class="space-y-5">
            <header
                class="flex flex-col justify-between gap-4 rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white shadow-sm md:flex-row md:items-end"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.2em] text-cyan-200 uppercase"
                    >
                        {{
                            kidsOnly
                                ? t('admin.classrooms.kids_title')
                                : t('admin.classrooms.title')
                        }}
                    </p>
                    <h1 class="mt-2 text-3xl font-black">
                        {{ t('admin.classrooms.title') }}
                    </h1>
                    <p class="mt-2 text-sm text-cyan-100">
                        {{ t('admin.classrooms.description') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/30 px-4 py-3 text-sm font-black text-white"
                        @click="categoriesOpen = true"
                    >
                        <Tags class="size-4" />{{
                            t('admin.categories.title')
                        }}</button
                    ><button
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-black text-slate-900"
                        @click="edit(null)"
                    >
                        <Plus class="size-4" />{{ t('admin.classrooms.new') }}
                    </button>
                </div>
            </header>

            <section
                v-if="!kidsOnly"
                class="flex flex-col gap-4 rounded-2xl border bg-white p-4 shadow-sm md:flex-row md:items-end"
            >
                <div class="flex items-center gap-2 font-black text-slate-700">
                    <Filter class="size-4" />{{ t('admin.classrooms.filters') }}
                </div>
                <label class="text-xs font-bold text-slate-500"
                    >{{ t('admin.classrooms.age')
                    }}<input
                        v-model.number="filterAge"
                        type="number"
                        min="0"
                        class="mt-1 w-full rounded-lg border-slate-300"
                /></label>
                <label class="text-xs font-bold text-slate-500"
                    >{{ t('admin.classrooms.gender_restriction')
                    }}<select
                        v-model="filterGender"
                        class="mt-1 w-full rounded-lg border-slate-300"
                    >
                        <option value="">
                            {{ t('admin.classrooms.gender.all') }}
                        </option>
                        <option value="male">
                            {{ t('admin.classrooms.gender.male') }}
                        </option>
                        <option value="female">
                            {{ t('admin.classrooms.gender.female') }}
                        </option>
                    </select></label
                >
                <label class="text-xs font-bold text-slate-500"
                    >Kids<select
                        v-model="filterKids"
                        class="mt-1 w-full rounded-lg border-slate-300"
                    >
                        <option value="all">
                            {{ t('admin.classrooms.all_rooms') }}
                        </option>
                        <option value="kids">Kids</option>
                        <option value="regular">
                            {{ t('admin.classrooms.regular_rooms') }}
                        </option>
                    </select></label
                >
                <label
                    class="ml-auto flex items-center gap-2 rounded-xl bg-cyan-50 px-3 py-2 text-sm font-bold text-cyan-900"
                    ><input
                        :checked="separateKidsMinistry"
                        type="checkbox"
                        @change="
                            updateSeparation(
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />{{ t('admin.classrooms.separate_kids') }}</label
                >
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <article
                    v-for="room in filteredRooms"
                    :key="room.id"
                    :class="[
                        'rounded-2xl border bg-white p-5 shadow-sm transition',
                        openedId === room.id && 'ring-2 ring-cyan-600',
                    ]"
                >
                    <div class="flex items-start gap-3">
                        <div
                            :class="[
                                'grid size-11 shrink-0 place-items-center rounded-xl',
                                room.is_kids
                                    ? 'bg-amber-100 text-amber-700'
                                    : 'bg-cyan-100 text-cyan-700',
                            ]"
                        >
                            <Baby v-if="room.is_kids" class="size-5" /><DoorOpen
                                v-else
                                class="size-5"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-black">{{ room.name }}</h2>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ room.min_age ?? 0 }}–{{
                                    room.max_age ?? '∞'
                                }}
                                {{ t('admin.classrooms.years') }} ·
                                {{
                                    room.gender_restriction
                                        ? t(
                                              `admin.classrooms.gender.${room.gender_restriction}`,
                                          )
                                        : t('admin.classrooms.gender.all')
                                }}
                            </p>
                            <p class="mt-2 text-xs font-bold text-emerald-700">
                                {{
                                    t('admin.classrooms.present', {
                                        count: room.active_presences_count,
                                    })
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-cyan-700 px-3 py-2 text-xs font-bold text-white"
                            @click="openedId = room.id"
                        >
                            <DoorOpen class="size-4" />{{
                                t('admin.classrooms.open')
                            }}</button
                        ><button
                            class="rounded-lg border px-3 py-2 text-slate-600"
                            @click="edit(room)"
                        >
                            <Pencil class="size-4" />
                        </button>
                    </div>
                </article>
            </section>
            <p
                v-if="!filteredRooms.length"
                class="rounded-2xl border border-dashed p-12 text-center text-sm text-slate-500"
            >
                {{ t('admin.classrooms.empty_attendance') }}
            </p>
        </section>

        <aside
            v-if="openedRoom"
            class="mx-auto w-full max-w-7xl overflow-hidden rounded-2xl border bg-white shadow-sm"
        >
            <template v-if="openedRoom"
                ><header class="border-b p-5">
                    <button
                        type="button"
                        class="mb-4 inline-flex items-center gap-2 text-sm font-bold text-indigo-700"
                        @click="openedId = ''"
                    >
                        <ArrowLeft class="size-4" />{{ t('actions.back') }}
                    </button>
                    <p class="text-xs font-bold text-cyan-700 uppercase">
                        {{ t('admin.classrooms.open_room') }}
                    </p>
                    <h2 class="mt-1 text-xl font-black">
                        {{ openedRoom.name }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ t('admin.classrooms.attendance_hint') }}
                    </p>
                    <button
                        type="button"
                        class="mt-3 inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-bold text-cyan-700"
                        @click="edit(openedRoom)"
                    >
                        <Pencil class="size-4" />{{
                            t('admin.classrooms.manage_students')
                        }}
                    </button>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="member in openedRoom.members"
                        :key="member.id"
                        class="flex items-center gap-3 rounded-xl border bg-slate-50 p-4"
                    >
                        <div
                            :class="[
                                'size-2 rounded-full',
                                openedRoom.active_member_ids.includes(member.id)
                                    ? 'bg-emerald-500'
                                    : 'bg-slate-300',
                            ]"
                        />
                        <div class="min-w-0 flex-1">
                            <button
                                type="button"
                                class="truncate text-left text-sm font-bold hover:text-indigo-700"
                                @click="selectedMember = member"
                            >
                                {{ member.first_name }} {{ member.last_name }}
                            </button>
                            <p class="text-xs text-slate-400">
                                {{
                                    openedRoom.active_member_ids.includes(
                                        member.id,
                                    )
                                        ? t('admin.classrooms.checked_in')
                                        : t('admin.classrooms.not_checked_in')
                                }}
                            </p>
                        </div>
                        <button
                            v-if="
                                !openedRoom.active_member_ids.includes(
                                    member.id,
                                )
                            "
                            class="rounded-lg bg-emerald-50 p-2 text-emerald-700"
                            @click="checkIn(openedRoom, member)"
                        >
                            <UserCheck class="size-4" /></button
                        ><button
                            v-else
                            class="rounded-lg bg-amber-50 p-2 text-amber-700"
                            @click="checkOut(openedRoom, member)"
                        >
                            <UserMinus class="size-4" />
                        </button>
                    </article>
                    <p
                        v-if="!openedRoom.members.length"
                        class="p-8 text-center text-sm text-slate-500"
                    >
                        {{ t('admin.classrooms.no_students') }}
                    </p>
                </div></template
            >
            <p v-else class="p-10 text-center text-sm text-slate-500">
                {{ t('admin.classrooms.select_room') }}
            </p>
        </aside>

        <div
            v-if="selectedMember"
            class="fixed inset-0 z-[60] grid place-items-center bg-slate-950/65 p-4 backdrop-blur-sm"
            @click.self="selectedMember = null"
        >
            <section
                class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl"
            >
                <header class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold text-indigo-600 uppercase">
                            {{ t('admin.classrooms.student_profile') }}
                        </p>
                        <h2 class="mt-1 text-2xl font-black">
                            {{ selectedMember.first_name }}
                            {{ selectedMember.last_name }}
                        </h2>
                    </div>
                    <button
                        class="rounded-lg border px-3 py-1.5 text-sm"
                        @click="selectedMember = null"
                    >
                        {{ t('a11y.close') }}
                    </button>
                </header>
                <dl class="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 text-sm">
                    <div>
                        <dt class="text-xs font-bold text-slate-400 uppercase">
                            {{ t('admin.classrooms.age') }}
                        </dt>
                        <dd>{{ selectedMember.birth_date || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-slate-400 uppercase">
                            {{ t('admin.classrooms.gender_restriction') }}
                        </dt>
                        <dd>{{ selectedMember.profile?.gender || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-slate-400 uppercase">
                            {{ t('settings.profile.phone') }}
                        </dt>
                        <dd>{{ selectedMember.profile?.phone || '—' }}</dd>
                    </div>
                </dl>
                <section
                    v-if="
                        selectedMember.relationships?.length ||
                        selectedMember.related_relationships?.length
                    "
                    class="mt-5"
                >
                    <h3 class="text-sm font-black">
                        {{ t('admin.classrooms.guardians') }}
                    </h3>
                    <div class="mt-2 space-y-2">
                        <div
                            v-for="relation in [
                                ...(selectedMember.relationships ?? []),
                                ...(selectedMember.related_relationships ?? []),
                            ]"
                            :key="`${relation.relationship_type}-${relation.related_user?.id ?? relation.user?.id}`"
                            class="rounded-lg border p-3 text-sm"
                        >
                            <strong
                                >{{
                                    relation.related_user?.first_name ??
                                    relation.user?.first_name
                                }}
                                {{
                                    relation.related_user?.last_name ??
                                    relation.user?.last_name
                                }}</strong
                            ><span class="ml-2 text-xs text-slate-400">{{
                                relation.relationship_type
                            }}</span>
                        </div>
                    </div>
                </section>
            </section>
        </div>

        <div
            v-if="editorOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm"
        >
            <form
                class="mx-auto max-w-2xl space-y-4 rounded-2xl bg-white p-6 text-slate-900 shadow-xl"
                @submit.prevent="save"
            >
                <header class="flex items-center justify-between">
                    <h2 class="text-xl font-black">
                        {{
                            selected
                                ? t('admin.classrooms.edit')
                                : t('admin.classrooms.new')
                        }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-sm"
                        @click="editorOpen = false"
                    >
                        {{ t('a11y.close') }}
                    </button>
                </header>
                <p
                    v-if="error"
                    class="rounded-lg bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ error }}
                </p>
                <label class="block text-sm font-semibold"
                    >{{ t('admin.common.name')
                    }}<input
                        v-model="form.name"
                        required
                        class="mt-1 w-full rounded-lg border-slate-300" /></label
                ><label class="block text-sm font-semibold"
                    >{{ t('admin.common.description')
                    }}<textarea
                        v-model="form.description"
                        class="mt-1 w-full rounded-lg border-slate-300"
                    />
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm font-semibold"
                        >{{ t('admin.classrooms.min_age')
                        }}<input
                            v-model.number="form.min_age"
                            type="number"
                            min="0"
                            class="mt-1 w-full rounded-lg border-slate-300" /></label
                    ><label class="text-sm font-semibold"
                        >{{ t('admin.classrooms.max_age')
                        }}<input
                            v-model.number="form.max_age"
                            type="number"
                            min="0"
                            class="mt-1 w-full rounded-lg border-slate-300"
                    /></label>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-semibold"
                        >{{ t('admin.classrooms.gender_restriction')
                        }}<select
                            v-model="form.gender_restriction"
                            class="mt-1 w-full rounded-lg border-slate-300"
                        >
                            <option value="">
                                {{ t('admin.classrooms.gender.none') }}
                            </option>
                            <option value="male">
                                {{ t('admin.classrooms.gender.male') }}
                            </option>
                            <option value="female">
                                {{ t('admin.classrooms.gender.female') }}
                            </option>
                        </select></label
                    ><label class="text-sm font-semibold"
                        >{{ t('admin.classrooms.max_members')
                        }}<input
                            v-model.number="form.max_members"
                            type="number"
                            min="1"
                            class="mt-1 w-full rounded-lg border-slate-300"
                    /></label>
                </div>
                <label v-if="!kidsOnly" class="flex gap-2 text-sm font-semibold"
                    ><input v-model="form.is_kids" type="checkbox" />{{
                        t('admin.classrooms.is_kids')
                    }}</label
                >
                <fieldset class="rounded-xl border p-4">
                    <legend class="px-2 text-sm font-black">
                        {{ t('admin.classrooms.students') }}
                    </legend>
                    <div
                        class="grid max-h-52 gap-2 overflow-y-auto sm:grid-cols-2"
                    >
                        <label
                            v-for="member in members"
                            :key="member.id"
                            class="flex gap-2 text-sm"
                            ><input
                                v-model="form.member_ids"
                                type="checkbox"
                                :value="member.id"
                            />{{ member.first_name }}
                            {{ member.last_name }}</label
                        >
                    </div>
                </fieldset>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-slate-500"
                        @click="editorOpen = false"
                    >
                        {{ t('actions.cancel') }}</button
                    ><button
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white"
                    >
                        {{ t('admin.classrooms.save') }}
                    </button>
                </div>
            </form>
        </div>
        <CategoryManagerModal
            :open="categoriesOpen"
            category-type="classroom"
            :categories="categories"
            @close="categoriesOpen = false"
        />
    </main>
</template>
