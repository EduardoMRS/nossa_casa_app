<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { useI18n } from '@/lib/i18n';

type Member = {
    id: string;
    first_name: string;
    last_name: string;
    birth_date: string | null;
    profile?: { gender: string | null };
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
    active_presences_count: number;
};
const props = defineProps<{
    classrooms: Classroom[];
    members: Member[];
    kidsOnly?: boolean;
}>();
const { t } = useI18n();
const selected = ref<Classroom | null>(null);
const editorOpen = ref(false);
const error = ref('');
const checkoutPin = ref('');
const activeTab = ref<'management' | 'attendance'>('management');
const attendanceClassroomId = ref(props.classrooms[0]?.id ?? '');
const attendanceClassroom = computed(
    () =>
        props.classrooms.find(
            (classroom) => classroom.id === attendanceClassroomId.value,
        ) ?? null,
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
    editorOpen.value = true;
    selected.value = item;
    error.value = '';
    checkoutPin.value = '';
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
}
function openClassroom(classroom: Classroom): void {
    attendanceClassroomId.value = classroom.id;
    activeTab.value = 'attendance';
}
function closeEditor(): void {
    editorOpen.value = false;
    error.value = '';
}
async function save(): Promise<void> {
    try {
        error.value = '';
        const payload = {
            ...form.value,
            gender_restriction: form.value.gender_restriction || null,
            is_kids: form.value.is_kids,
        };

        if (selected.value) {
            await axios.put(`/api/classrooms/${selected.value.id}`, payload);
        } else {
            await axios.post('/api/classrooms', payload);
        }

        window.location.reload();
    } catch (caught) {
        error.value = axios.isAxiosError(caught)
            ? Object.values(caught.response?.data?.errors ?? {})
                  .flat()
                  .join(' ')
            : t('admin.classrooms.save_error');
    }
}
async function checkIn(classroom: Classroom, member: Member): Promise<void> {
    try {
        const { data } = await axios.post(
            `/api/classrooms/${classroom.id}/check-in`,
            { user_id: member.id },
        );
        checkoutPin.value = data.checkout_pin
            ? t('admin.classrooms.pin_generated', {
                  name: member.first_name,
                  pin: data.checkout_pin,
              })
            : t('admin.classrooms.checkin_success');
        window.location.reload();
    } catch (caught) {
        error.value = axios.isAxiosError(caught)
            ? Object.values(caught.response?.data?.errors ?? {})
                  .flat()
                  .join(' ')
            : t('admin.classrooms.checkin_error');
    }
}
async function checkOut(classroom: Classroom, member: Member): Promise<void> {
    const pin = window.prompt(
        t('admin.classrooms.pin_prompt', { name: member.first_name }),
    );

    if (pin === null) {
        return;
    }

    try {
        await axios.post(`/api/classrooms/${classroom.id}/check-out`, {
            user_id: member.id,
            pin,
        });
        window.location.reload();
    } catch (caught) {
        error.value = axios.isAxiosError(caught)
            ? Object.values(caught.response?.data?.errors ?? {})
                  .flat()
                  .join(' ')
            : t('admin.classrooms.checkout_error');
    }
}
</script>

<template>
    <Head
        :title="
            props.kidsOnly
                ? t('admin.classrooms.kids_title')
                : t('admin.classrooms.title')
        "
    />
    <main class="grid gap-6 p-4 lg:grid-cols-[minmax(0,1fr)_26rem] lg:p-6">
        <section class="space-y-4">
            <header class="flex justify-between">
                <div>
                    <h1 class="text-2xl font-black">
                        {{
                            props.kidsOnly
                                ? t('admin.classrooms.kids_title')
                                : t('admin.classrooms.title')
                        }}
                    </h1>
                    <p class="text-sm text-slate-500">
                        {{ t('admin.classrooms.description') }}
                    </p>
                </div>
                <button
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white"
                    @click="edit(null)"
                >
                    {{ t('admin.classrooms.new') }}
                </button>
            </header>
            <p
                v-if="checkoutPin"
                class="rounded-lg bg-amber-50 p-3 font-bold text-amber-800"
            >
                {{ checkoutPin }}
            </p>
            <p v-if="error" class="rounded-lg bg-red-50 p-3 text-red-700">
                {{ error }}
            </p>
            <div class="flex gap-2 border-b">
                <button
                    :class="
                        activeTab === 'management'
                            ? 'border-b-2 border-slate-900 font-bold'
                            : 'text-slate-500'
                    "
                    class="px-3 py-2 text-sm"
                    @click="activeTab = 'management'"
                >
                    {{ t('admin.classrooms.manage_tab') }}
                </button>
                <button
                    :class="
                        activeTab === 'attendance'
                            ? 'border-b-2 border-slate-900 font-bold'
                            : 'text-slate-500'
                    "
                    class="px-3 py-2 text-sm"
                    @click="activeTab = 'attendance'"
                >
                    {{ t('admin.classrooms.attendance_tab') }}
                </button>
            </div>
            <template v-if="activeTab === 'attendance'">
                <label class="block max-w-md text-sm font-semibold">
                    {{ t('admin.classrooms.select_room') }}
                    <select
                        v-model="attendanceClassroomId"
                        class="mt-1 w-full rounded-lg border-slate-300"
                    >
                        <option
                            v-for="room in props.classrooms"
                            :key="room.id"
                            :value="room.id"
                        >
                            {{ room.name }}
                        </option>
                    </select>
                </label>
                <article
                    v-if="attendanceClassroom"
                    class="rounded-2xl border bg-white p-5"
                >
                    <h2 class="font-bold">{{ attendanceClassroom.name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ t('admin.classrooms.attendance_hint') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="member in attendanceClassroom.members"
                            :key="member.id"
                            class="rounded-lg bg-slate-100 px-2 py-1 text-sm"
                        >
                            {{ member.first_name }} {{ member.last_name }}
                            <button
                                class="ml-1 text-emerald-700"
                                @click="checkIn(attendanceClassroom, member)"
                            >
                                {{ t('admin.classrooms.checkin') }}
                            </button>
                            <button
                                class="ml-1 text-amber-700"
                                @click="checkOut(attendanceClassroom, member)"
                            >
                                {{ t('admin.classrooms.checkout') }}
                            </button>
                        </span>
                    </div>
                </article>
                <p
                    v-else
                    class="rounded-xl border border-dashed p-6 text-sm text-slate-500"
                >
                    {{ t('admin.classrooms.empty_attendance') }}
                </p>
            </template>
            <template v-else>
                <article
                    v-for="room in props.classrooms"
                    :key="room.id"
                    class="rounded-2xl border bg-white p-5"
                >
                    <div class="flex justify-between">
                        <div>
                            <h2 class="font-bold">{{ room.name }}</h2>
                            <p class="text-sm text-slate-500">
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
                                ·
                                {{
                                    t('admin.classrooms.present', {
                                        count: room.active_presences_count,
                                    })
                                }}
                            </p>
                        </div>
                        <button
                            class="text-sm font-semibold"
                            @click="edit(room)"
                        >
                            {{ t('actions.edit') }}
                        </button>
                        <button
                            class="text-sm font-semibold text-sky-700"
                            @click="openClassroom(room)"
                        >
                            {{ t('admin.classrooms.open') }}
                        </button>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="member in room.members"
                            :key="member.id"
                            class="rounded-lg bg-slate-100 px-2 py-1 text-sm"
                            >{{ member.first_name }} {{ member.last_name }}
                            <button
                                class="ml-1 text-emerald-700"
                                @click="checkIn(room, member)"
                            >
                                {{ t('admin.classrooms.checkin') }}</button
                            ><button
                                class="ml-1 text-amber-700"
                                @click="checkOut(room, member)"
                            >
                                {{ t('admin.classrooms.checkout') }}
                            </button></span
                        >
                    </div>
                </article>
            </template>
        </section>
        <div
            v-if="editorOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/45 p-4 backdrop-blur-sm lg:p-8"
        >
            <form
                class="mx-auto max-w-2xl space-y-3 rounded-2xl border bg-white p-5 shadow-xl"
                @submit.prevent="save"
            >
                <h2 class="font-black">
                    {{
                        selected
                            ? t('admin.classrooms.edit')
                            : t('admin.classrooms.new')
                    }}
                </h2>
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
                <label class="block text-sm font-semibold"
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
                ><label
                    v-if="!props.kidsOnly"
                    class="flex gap-2 text-sm font-semibold"
                    ><input v-model="form.is_kids" type="checkbox" />{{
                        t('admin.classrooms.is_kids')
                    }}</label
                ><label class="block text-sm font-semibold"
                    >{{ t('admin.classrooms.max_members')
                    }}<input
                        v-model.number="form.max_members"
                        type="number"
                        min="1"
                        class="mt-1 w-full rounded-lg border-slate-300"
                /></label>
                <fieldset>
                    <legend class="text-sm font-semibold">
                        {{ t('admin.classrooms.students') }}
                    </legend>
                    <label
                        v-for="member in props.members"
                        :key="member.id"
                        class="mt-1 flex gap-2 text-sm"
                        ><input
                            v-model="form.member_ids"
                            type="checkbox"
                            :value="member.id"
                        />{{ member.first_name }} {{ member.last_name }}</label
                    >
                </fieldset>
                <button
                    class="w-full rounded-lg bg-slate-900 py-2 text-sm font-bold text-white"
                >
                    {{ t('admin.classrooms.save') }}
                </button>
                <button
                    type="button"
                    class="w-full rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-600"
                    @click="closeEditor"
                >
                    {{ t('actions.cancel') }}
                </button>
            </form>
        </div>
    </main>
</template>
