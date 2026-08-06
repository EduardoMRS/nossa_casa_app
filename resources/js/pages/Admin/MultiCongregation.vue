<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
type Church = {
    id: string;
    name: string;
    slug: string;
    status: string;
    community?: { name: string } | null;
    members_count: number;
};
type Community = { id: string; name: string };
type Network = {
    id: string;
    parent_church?: { name: string };
    child_church?: { name: string };
};
const props = defineProps<{
    churches: Church[];
    communities: Community[];
    networks: Network[];
    stats: Array<{ label: string; value: number }>;
}>();
const churches = ref([...props.churches]);
const editing = ref<Church | null>(null);
const open = ref(false);
const tab = ref<'churches' | 'networks'>('churches');
const form = ref({ name: '', slug: '', status: 'active', community_id: '' });
function start(church?: Church): void {
    editing.value = church ?? null;
    form.value = church
        ? {
              name: church.name,
              slug: church.slug,
              status: church.status,
              community_id: '',
          }
        : { name: '', slug: '', status: 'active', community_id: '' };
    open.value = true;
}
async function save(): Promise<void> {
    if (editing.value) {
        await axios.put(`/api/church/${editing.value.id}`, form.value);
    } else {
        await axios.post('/api/church', form.value);
    }

    window.location.reload();
}
</script>
<template>
    <Head title="Multicongregações" />
    <main class="space-y-6 p-4 md:p-8">
        <header
            class="flex flex-col justify-between gap-4 rounded-3xl bg-gradient-to-br from-violet-950 to-indigo-700 p-7 text-white md:flex-row md:items-end"
        >
            <div>
                <p
                    class="text-xs font-bold tracking-[0.2em] text-violet-200 uppercase"
                >
                    Estrutura
                </p>
                <h1 class="mt-2 text-3xl font-black">Multicongregações</h1>
                <p class="mt-2 text-sm text-violet-100">
                    Matriz, filiais e comunidades sob uma visão unificada.
                </p>
            </div>
            <button
                class="rounded-xl bg-white px-4 py-3 text-sm font-black text-violet-900"
                @click="start()"
            >
                Nova igreja
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
        <div class="flex gap-5 border-b">
            <button
                :class="
                    tab === 'churches'
                        ? 'border-b-2 border-indigo-700 font-black text-indigo-700'
                        : 'text-slate-400'
                "
                class="px-2 py-3 text-sm"
                @click="tab = 'churches'"
            >
                Igrejas</button
            ><button
                :class="
                    tab === 'networks'
                        ? 'border-b-2 border-indigo-700 font-black text-indigo-700'
                        : 'text-slate-400'
                "
                class="px-2 py-3 text-sm"
                @click="tab = 'networks'"
            >
                Matriz e filiais
            </button>
        </div>
        <section v-if="tab === 'churches'" class="grid gap-3 lg:grid-cols-2">
            <article
                v-for="church in churches"
                :key="church.id"
                class="flex items-center justify-between rounded-2xl border bg-white p-5 shadow-sm"
            >
                <div>
                    <h2 class="font-bold">{{ church.name }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ church.community?.name ?? 'Sem comunidade' }} ·
                        {{ church.members_count }} membros
                    </p>
                    <span
                        class="mt-2 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700 uppercase"
                        >{{ church.status }}</span
                    >
                </div>
                <button
                    class="text-sm font-bold text-indigo-700"
                    @click="start(church)"
                >
                    Editar
                </button>
            </article>
        </section>
        <section v-else class="space-y-3">
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
                Nenhum vínculo cadastrado.
            </p>
        </section>
        <div
            v-if="open"
            class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4"
        >
            <form
                class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl"
                @submit.prevent="save"
            >
                <h2 class="text-xl font-black">
                    {{ editing ? 'Editar igreja' : 'Nova igreja' }}
                </h2>
                <input
                    v-model="form.name"
                    required
                    class="w-full rounded-lg border-slate-300"
                    placeholder="Nome"
                /><input
                    v-model="form.slug"
                    required
                    class="w-full rounded-lg border-slate-300"
                    placeholder="Slug"
                /><select
                    v-model="form.status"
                    class="w-full rounded-lg border-slate-300"
                >
                    <option value="active">Ativa</option>
                    <option value="inactive">Inativa</option></select
                ><select
                    v-model="form.community_id"
                    class="w-full rounded-lg border-slate-300"
                >
                    <option value="">Sem comunidade</option>
                    <option
                        v-for="community in props.communities"
                        :key="community.id"
                        :value="community.id"
                    >
                        {{ community.name }}
                    </option>
                </select>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-slate-500"
                        @click="open = false"
                    >
                        Cancelar</button
                    ><button
                        class="rounded-lg bg-violet-700 px-4 py-2 text-sm font-bold text-white"
                    >
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </main>
</template>
