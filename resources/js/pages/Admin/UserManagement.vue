<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
type UserItem = {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
    role: string | { value: string };
    birth_date: string | null;
    church?: { id: string; name: string } | null;
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
          }
        : {
              first_name: '',
              last_name: '',
              email: '',
              role: 'member',
              birth_date: '',
              church_id: '',
          };
    open.value = true;
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
    <Head title="Gestão de usuários" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="flex flex-col justify-between gap-4 rounded-3xl bg-gradient-to-br from-slate-950 to-indigo-800 p-7 text-white md:flex-row md:items-end"
        >
            <div>
                <p
                    class="text-xs font-bold tracking-[0.2em] text-indigo-200 uppercase"
                >
                    Administração
                </p>
                <h1 class="mt-2 text-3xl font-black">Gestão de usuários</h1>
                <p class="mt-2 text-sm text-indigo-100">
                    Papéis, acesso e vínculo congregacional em um só lugar.
                </p>
            </div>
            <button
                class="rounded-xl bg-white px-4 py-3 text-sm font-black text-indigo-900"
                @click="start()"
            >
                Novo usuário
            </button>
        </header>
        <section class="grid gap-4 sm:grid-cols-3">
            <article
                v-for="stat in props.stats"
                :key="stat.label"
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <p
                    class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                >
                    {{ stat.label }}
                </p>
                <p class="mt-2 text-3xl font-black">{{ stat.value }}</p>
            </article>
        </section>
        <div class="flex items-center justify-between gap-3">
            <input
                v-model="query"
                class="w-full max-w-sm rounded-xl border-slate-300"
                placeholder="Buscar por nome ou e-mail"
            /><span class="text-xs font-bold text-slate-400"
                >{{ filtered.length }} encontrados</span
            >
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
                                typeof user.role === 'string'
                                    ? user.role
                                    : user.role.value
                            }}</span
                        >
                    </div>
                </div>
                <button
                    class="rounded-lg px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-50"
                    @click="start(user)"
                >
                    Editar
                </button>
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
                        {{ editing ? 'Editar usuário' : 'Novo usuário' }}
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
                        placeholder="Nome"
                    /><input
                        v-model="form.last_name"
                        required
                        class="rounded-lg border-slate-300"
                        placeholder="Sobrenome"
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
                            {{ role.label }}
                        </option></select
                    ><select
                        v-model="form.church_id"
                        class="rounded-lg border-slate-300"
                    >
                        <option value="">Sem igreja</option>
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
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-slate-500"
                        @click="open = false"
                    >
                        Cancelar</button
                    ><button
                        class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-bold text-white"
                    >
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </main>
</template>
