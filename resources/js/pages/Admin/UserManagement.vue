<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { useI18n } from '@/lib/i18n';
type UserItem = {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
    role: string | { value: string };
    birth_date: string | null;
    church?: { id: string; name: string } | null;
    profile?: {
        phone?: string | null;
        gender?: string | null;
        location_lang?: string | null;
    } | null;
};
type Option = { value: string; label: string };
type ChurchOption = { id: string; name: string };
const props = defineProps<{
    users: { data: UserItem[] };
    churches: ChurchOption[];
    roles: Option[];
    stats: Array<{ label: string; value: number }>;
}>();
const users = ref([...props.users.data]);
const { t } = useI18n();
const editing = ref<UserItem | null>(null);
const open = ref(false);
const query = ref('');
const form = ref({
    first_name: '',
    last_name: '',
    email: '',
    role: 'member',
    birth_date: '',
    church_id: '',
    phone: '',
    gender: '',
    location_lang: 'pt-BR',
});
const filtered = computed(() =>
    users.value.filter((user) =>
        `${user.first_name} ${user.last_name} ${user.email}`
            .toLowerCase()
            .includes(query.value.toLowerCase()),
    ),
);
function start(user?: UserItem): void {
    editing.value = user ?? null;
    form.value = user
        ? {
              first_name: user.first_name,
              last_name: user.last_name,
              email: user.email,
              role: typeof user.role === 'string' ? user.role : user.role.value,
              birth_date: user.birth_date ?? '',
              church_id: user.church?.id ?? '',
              phone: user.profile?.phone ?? '',
              gender: user.profile?.gender ?? '',
              location_lang: user.profile?.location_lang ?? 'pt-BR',
          }
        : {
              first_name: '',
              last_name: '',
              email: '',
              role: 'member',
              birth_date: '',
              church_id: '',
              phone: '',
              gender: '',
              location_lang: 'pt-BR',
          };
    open.value = true;
}
function roleValue(user: UserItem): string {
    return typeof user.role === 'string' ? user.role : user.role.value;
}
function sendReset(user: UserItem): void {
    router.post(
        `/admin/gestao-usuarios/${user.id}/redefinir-senha`,
        {},
        { preserveScroll: true },
    );
}
async function remove(user: UserItem): Promise<void> {
    if (
        ['system', 'superadmin'].includes(roleValue(user)) ||
        !window.confirm(
            t('admin.users.delete_confirm', {
                name: `${user.first_name} ${user.last_name}`,
            }),
        )
    ) {
        return;
    }

    await axios.delete(`/api/user/${user.id}`);
    users.value = users.value.filter((item) => item.id !== user.id);
}
async function save(): Promise<void> {
    if (editing.value) {
        await axios.put(`/api/user/${editing.value.id}`, form.value);
    } else {
        await axios.post('/api/user', {
            ...form.value,
            password: 'ChangeMe123!',
            password_confirmation: 'ChangeMe123!',
        });
    }

    window.location.reload();
}
</script>
<template>
    <Head :title="t('admin.users.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="flex flex-col justify-between gap-4 rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white shadow-sm md:flex-row md:items-end"
        >
            <div>
                <p
                    class="text-xs font-bold tracking-[0.2em] text-indigo-200 uppercase"
                >
                    {{ t('admin.users.kicker') }}
                </p>
                <h1 class="mt-2 text-3xl font-black">
                    {{ t('admin.users.title') }}
                </h1>
                <p class="mt-2 text-sm text-indigo-100">
                    {{ t('admin.users.description') }}
                </p>
            </div>
            <button
                class="rounded-xl bg-white px-4 py-3 text-sm font-black text-indigo-900"
                @click="start()"
            >
                {{ t('admin.users.new') }}
            </button>
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
                    {{ t(`admin.users.stats.${index}`) }}
                </p>
                <p class="mt-2 text-3xl font-black">{{ stat.value }}</p>
            </article>
        </section>
        <div class="flex items-center justify-between gap-3">
            <input
                v-model="query"
                class="w-full max-w-sm rounded-xl border-slate-300"
                :placeholder="t('admin.users.search_placeholder')"
            /><span class="text-xs font-bold text-slate-400">{{
                t('admin.users.found', { count: filtered.length })
            }}</span>
        </div>
        <section class="grid gap-3 lg:grid-cols-2">
            <article
                v-for="user in filtered"
                :key="user.id"
                class="flex items-center justify-between rounded-2xl border bg-white p-5 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="grid h-11 w-11 place-items-center rounded-full bg-indigo-100 font-black text-indigo-700"
                    >
                        {{ user.first_name.charAt(0) }}
                    </div>
                    <div>
                        <h2 class="font-bold">
                            {{ user.first_name }} {{ user.last_name }}
                        </h2>
                        <p class="text-sm text-slate-500">{{ user.email }}</p>
                        <span
                            class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase"
                            >{{
                                t(
                                    `dashboard.roles.${typeof user.role === 'string' ? user.role : user.role.value}`,
                                )
                            }}</span
                        >
                    </div>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <button
                        class="rounded-lg px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-50"
                        @click="start(user)"
                    >
                        {{ t('actions.edit') }}</button
                    ><button
                        class="rounded-lg px-3 py-1 text-xs font-bold text-amber-700 hover:bg-amber-50"
                        @click="sendReset(user)"
                    >
                        {{ t('admin.users.reset_password') }}</button
                    ><button
                        v-if="
                            !['system', 'superadmin'].includes(roleValue(user))
                        "
                        class="rounded-lg px-3 py-1 text-xs font-bold text-rose-700 hover:bg-rose-50"
                        @click="remove(user)"
                    >
                        {{ t('actions.delete') }}</button
                    ><span
                        v-else
                        class="px-3 py-1 text-[10px] font-bold text-slate-400 uppercase"
                        >{{ t('admin.users.protected') }}</span
                    >
                </div>
            </article>
        </section>
        <div
            v-if="open"
            class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4"
        >
            <form
                class="w-full max-w-xl space-y-4 rounded-2xl bg-white p-6 shadow-xl"
                @submit.prevent="save"
            >
                <div class="flex justify-between">
                    <h2 class="text-xl font-black">
                        {{
                            editing
                                ? t('admin.users.edit')
                                : t('admin.users.new')
                        }}
                    </h2>
                    <button
                        type="button"
                        class="text-slate-400"
                        @click="open = false"
                    >
                        ×
                    </button>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <input
                        v-model="form.first_name"
                        required
                        class="rounded-lg border-slate-300"
                        :placeholder="t('admin.users.first_name')"
                    /><input
                        v-model="form.last_name"
                        required
                        class="rounded-lg border-slate-300"
                        :placeholder="t('admin.users.last_name')"
                    /><input
                        v-model="form.email"
                        required
                        type="email"
                        class="rounded-lg border-slate-300 md:col-span-2"
                        placeholder="E-mail"
                    /><select
                        v-model="form.role"
                        class="rounded-lg border-slate-300"
                    >
                        <option
                            v-for="role in props.roles"
                            :key="role.value"
                            :value="role.value"
                        >
                            {{ t(`dashboard.roles.${role.value}`) }}
                        </option></select
                    ><select
                        v-model="form.church_id"
                        class="rounded-lg border-slate-300"
                    >
                        <option value="">
                            {{ t('admin.users.no_church') }}
                        </option>
                        <option
                            v-for="church in props.churches"
                            :key="church.id"
                            :value="church.id"
                        >
                            {{ church.name }}
                        </option></select
                    ><input
                        v-model="form.birth_date"
                        type="date"
                        class="rounded-lg border-slate-300"
                    /><input
                        v-model="form.phone"
                        type="tel"
                        class="rounded-lg border-slate-300"
                        :placeholder="t('admin.branding.phone')"
                    /><select
                        v-model="form.gender"
                        class="rounded-lg border-slate-300"
                    >
                        <option value="">
                            {{ t('admin.classrooms.gender.none') }}
                        </option>
                        <option value="male">
                            {{ t('admin.classrooms.gender.male') }}
                        </option>
                        <option value="female">
                            {{ t('admin.classrooms.gender.female') }}
                        </option></select
                    ><input
                        v-model="form.location_lang"
                        class="rounded-lg border-slate-300"
                        placeholder="pt-BR"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-slate-500"
                        @click="open = false"
                    >
                        {{ t('actions.cancel') }}</button
                    ><button
                        class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-bold text-white"
                    >
                        {{ t('actions.save') }}
                    </button>
                </div>
            </form>
        </div>
    </main>
</template>
