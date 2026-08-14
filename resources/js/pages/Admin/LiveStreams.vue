<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import {
    Check,
    Clipboard,
    ExternalLink,
    KeyRound,
    Plus,
    Radio,
    RefreshCw,
    Square,
    Video,
    X,
} from '@lucide/vue';
import axios from 'axios';
import { ref } from 'vue';
import { index as liveStreamControlIndex } from '@/actions/App/Http/Controllers/Admin/LiveStreamControlController';
import {
    destroy as destroyLiveStream,
    rotateToken as rotateLiveStreamToken,
    store as storeLiveStream,
} from '@/actions/App/Http/Controllers/LiveStreamController';
import { update as updateMedia } from '@/actions/App/Http/Controllers/MediaController';

type RecordingItem = {
    id: string;
    status: string;
    uploaded_at: string | null;
    media_id: string | null;
    is_public: boolean;
};

type StreamItem = {
    id: string;
    name: string;
    status: string;
    active: boolean;
    input_mode: string;
    record: boolean;
    is_public: boolean;
    started_at: string | null;
    ended_at: string | null;
    recordings_count: number;
    token: string | null;
    token_rotated_at: string | null;
    ingest_url: string | null;
    public_url: string;
    recordings: RecordingItem[];
};

const props = defineProps<{
    church: { id: string; name: string };
    churches: Array<{ id: string; name: string }>;
    streams: StreamItem[];
    canCreate: boolean;
}>();

usePoll(5000, { only: ['streams', 'canCreate'] });

const createOpen = ref(false);
const name = ref('');
const record = ref(true);
const isPublic = ref(true);
const processing = ref(false);
const error = ref('');
const copied = ref('');
const selectedChurchId = ref(props.church.id);

const refresh = (): void => router.reload({ only: ['streams', 'canCreate'] });

const switchChurch = (): void => {
    router.get(liveStreamControlIndex.url(), {
        church_id: selectedChurchId.value,
    });
};

const createStream = async (): Promise<void> => {
    processing.value = true;
    error.value = '';

    try {
        await axios.post(storeLiveStream.url(), {
            name: name.value,
            mode: 'publisher',
            record: record.value,
            is_public: isPublic.value,
            church_id: props.church.id,
        });
        createOpen.value = false;
        name.value = '';
        isPublic.value = true;
        refresh();
    } catch (exception: unknown) {
        const responseErrors = axios.isAxiosError<{
            errors?: Record<string, string[]>;
        }>(exception)
            ? exception.response?.data?.errors
            : undefined;
        error.value =
            responseErrors?.church?.[0] ||
            responseErrors?.name?.[0] ||
            'Não foi possível criar a transmissão.';
    } finally {
        processing.value = false;
    }
};

const stopStream = async (stream: StreamItem): Promise<void> => {
    if (!window.confirm(`Encerrar a transmissão “${stream.name}”?`)) {
        return;
    }

    await axios.delete(destroyLiveStream.url(stream.id));
    refresh();
};

const rotateToken = async (stream: StreamItem): Promise<void> => {
    if (
        !window.confirm(
            'Renovar o token desconectará a transmissão atual. Continuar?',
        )
    ) {
        return;
    }

    processing.value = true;

    try {
        await axios.post(rotateLiveStreamToken.url(stream.id));
        refresh();
    } finally {
        processing.value = false;
    }
};

const toggleRecordingVisibility = async (
    recording: RecordingItem,
): Promise<void> => {
    if (!recording.media_id) {
        return;
    }

    processing.value = true;

    try {
        await axios.put(updateMedia.url(recording.media_id), {
            gallery: !recording.is_public,
        });
        refresh();
    } finally {
        processing.value = false;
    }
};

const copy = async (value: string | null, key: string): Promise<void> => {
    if (!value) {
        return;
    }

    await navigator.clipboard.writeText(value);
    copied.value = key;
    window.setTimeout(() => {
        if (copied.value === key) {
            copied.value = '';
        }
    }, 1800);
};

const formatDate = (value: string | null): string =>
    value
        ? new Intl.DateTimeFormat('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';
</script>

<template>
    <Head title="Controle de transmissões" />

    <main class="space-y-6 p-4 md:p-8">
        <header
            class="flex flex-col justify-between gap-5 rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white shadow-sm md:flex-row md:items-end"
        >
            <div>
                <p
                    class="text-xs font-black tracking-[0.18em] text-cyan-200 uppercase"
                >
                    {{ church.name }}
                </p>
                <h1 class="mt-2 text-3xl font-black">
                    Controle de transmissões
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-indigo-100">
                    Crie a transmissão, copie o endereço para o OBS e acompanhe
                    o estado da conexão.
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <select
                    v-if="churches.length > 1"
                    v-model="selectedChurchId"
                    class="rounded-xl border-white/30 bg-indigo-950 px-3 py-2 text-sm text-white"
                    aria-label="Selecionar igreja"
                    @change="switchChurch"
                >
                    <option
                        v-for="option in churches"
                        :key="option.id"
                        :value="option.id"
                        class="text-slate-950"
                    >
                        {{ option.name }}
                    </option>
                </select>
                <button
                    v-if="canCreate"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-black text-indigo-950"
                    @click="createOpen = true"
                >
                    <Plus class="size-4" /> Nova transmissão
                </button>
            </div>
        </header>

        <section v-if="streams.length" class="space-y-5">
            <article
                v-for="stream in streams"
                :key="stream.id"
                class="overflow-hidden rounded-2xl border bg-white shadow-sm"
            >
                <header
                    class="flex flex-col justify-between gap-4 border-b p-5 sm:flex-row sm:items-center"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-11 place-items-center rounded-xl"
                            :class="
                                stream.status === 'live'
                                    ? 'bg-rose-100 text-rose-600'
                                    : 'bg-slate-100 text-slate-500'
                            "
                        >
                            <Radio class="size-5" />
                        </span>
                        <div>
                            <h2 class="font-black text-slate-950">
                                {{ stream.name }}
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ stream.status }} ·
                                {{ stream.recordings_count }} gravação(ões) ·
                                {{ stream.is_public ? 'Pública' : 'Privada' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            :href="stream.public_url"
                            target="_blank"
                            class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-bold text-indigo-700"
                        >
                            <ExternalLink class="size-4" /> Abrir página
                        </a>
                        <button
                            v-if="stream.active"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-3 py-2 text-xs font-bold text-white"
                            @click="stopStream(stream)"
                        >
                            <Square class="size-3.5 fill-current" /> Encerrar
                        </button>
                    </div>
                </header>

                <div class="grid gap-5 p-5 lg:grid-cols-2">
                    <section
                        v-if="stream.input_mode === 'publisher'"
                        class="space-y-4"
                    >
                        <div>
                            <p
                                class="text-xs font-black text-slate-500 uppercase"
                            >
                                Link de transmissão para OBS
                            </p>
                            <div class="mt-2 flex gap-2">
                                <input
                                    :value="stream.ingest_url ?? ''"
                                    readonly
                                    class="min-w-0 flex-1 rounded-lg border-slate-300 bg-slate-50 text-sm"
                                />
                                <button
                                    type="button"
                                    class="grid size-10 place-items-center rounded-lg border"
                                    title="Copiar link"
                                    @click="
                                        copy(
                                            stream.ingest_url,
                                            `ingest-${stream.id}`,
                                        )
                                    "
                                >
                                    <Check
                                        v-if="copied === `ingest-${stream.id}`"
                                        class="size-4 text-emerald-600"
                                    />
                                    <Clipboard v-else class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div>
                            <p
                                class="text-xs font-black text-slate-500 uppercase"
                            >
                                Token
                            </p>
                            <div class="mt-2 flex gap-2">
                                <input
                                    :value="stream.token ?? ''"
                                    readonly
                                    class="min-w-0 flex-1 rounded-lg border-slate-300 bg-slate-50 font-mono text-xs"
                                />
                                <button
                                    type="button"
                                    class="grid size-10 place-items-center rounded-lg border"
                                    title="Copiar token"
                                    @click="
                                        copy(stream.token, `token-${stream.id}`)
                                    "
                                >
                                    <Check
                                        v-if="copied === `token-${stream.id}`"
                                        class="size-4 text-emerald-600"
                                    />
                                    <KeyRound v-else class="size-4" />
                                </button>
                                <button
                                    v-if="stream.active"
                                    type="button"
                                    :disabled="processing"
                                    class="inline-flex items-center gap-2 rounded-lg border px-3 text-xs font-bold text-amber-700 disabled:opacity-50"
                                    @click="rotateToken(stream)"
                                >
                                    <RefreshCw class="size-4" /> Renovar
                                </button>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-400">
                                Última renovação:
                                {{ formatDate(stream.token_rotated_at) }}
                            </p>
                        </div>
                    </section>

                    <section class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p
                                class="text-xs font-bold text-slate-400 uppercase"
                            >
                                Início
                            </p>
                            <p class="mt-2 font-bold">
                                {{ formatDate(stream.started_at) }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p
                                class="text-xs font-bold text-slate-400 uppercase"
                            >
                                Gravação
                            </p>
                            <p class="mt-2 font-bold">
                                {{ stream.record ? 'Ativada' : 'Desativada' }}
                            </p>
                        </div>
                        <div class="col-span-2 rounded-xl bg-slate-50 p-4">
                            <p
                                class="text-xs font-bold text-slate-400 uppercase"
                            >
                                Visibilidade ao vivo
                            </p>
                            <p class="mt-2 font-bold">
                                {{
                                    stream.is_public
                                        ? 'Pública — aparece no portal e na igreja'
                                        : 'Privada — somente membros desta igreja'
                                }}
                            </p>
                        </div>
                        <div
                            class="col-span-2 rounded-xl border border-cyan-100 bg-cyan-50 p-4 text-xs leading-5 text-cyan-950"
                        >
                            <Video class="mr-1 inline size-4" /> No OBS,
                            selecione <strong>Serviço personalizado</strong>,
                            cole o link completo no campo servidor e deixe a
                            chave de transmissão vazia.
                        </div>
                    </section>
                </div>

                <section
                    v-if="stream.recordings.length"
                    class="border-t bg-slate-50/70 p-5"
                >
                    <h3 class="text-sm font-black text-slate-950">
                        Gravações desta transmissão
                    </h3>
                    <div class="mt-3 grid gap-2 md:grid-cols-2">
                        <div
                            v-for="recording in stream.recordings"
                            :key="recording.id"
                            class="flex items-center justify-between gap-4 rounded-xl border bg-white p-3"
                        >
                            <div>
                                <p class="text-sm font-bold">
                                    {{
                                        recording.is_public
                                            ? 'Gravação pública'
                                            : 'Gravação privada'
                                    }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ recording.status }} ·
                                    {{ formatDate(recording.uploaded_at) }}
                                </p>
                            </div>
                            <button
                                v-if="recording.media_id"
                                type="button"
                                :disabled="processing"
                                class="rounded-lg border px-3 py-2 text-xs font-bold text-indigo-700 disabled:opacity-50"
                                @click="toggleRecordingVisibility(recording)"
                            >
                                {{
                                    recording.is_public
                                        ? 'Tornar privada'
                                        : 'Publicar gravação'
                                }}
                            </button>
                        </div>
                    </div>
                </section>
            </article>
        </section>

        <section
            v-else
            class="grid min-h-64 place-items-center rounded-2xl border-2 border-dashed bg-white p-8 text-center text-slate-500"
        >
            <div>
                <Radio class="mx-auto size-10 text-slate-300" />
                <p class="mt-3 font-bold">Nenhuma transmissão cadastrada.</p>
            </div>
        </section>

        <div
            v-if="createOpen"
            class="fixed inset-0 z-50 grid place-items-center bg-slate-950/70 p-4 backdrop-blur-sm"
            @click.self="createOpen = false"
        >
            <form
                class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl"
                @submit.prevent="createStream"
            >
                <header class="flex items-center justify-between border-b p-5">
                    <div>
                        <p class="text-xs font-black text-indigo-600 uppercase">
                            Nova transmissão
                        </p>
                        <h2 class="mt-1 text-xl font-black">
                            Gerar link e token
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 hover:bg-slate-100"
                        @click="createOpen = false"
                    >
                        <X class="size-5" />
                    </button>
                </header>
                <div class="space-y-4 p-5">
                    <label class="block text-sm font-bold"
                        >Nome<input
                            v-model="name"
                            required
                            maxlength="255"
                            class="mt-1 w-full rounded-lg border-slate-300"
                            placeholder="Culto de domingo"
                    /></label>
                    <label
                        class="flex items-center gap-3 rounded-xl border p-4 text-sm font-bold"
                        ><input
                            v-model="record"
                            type="checkbox"
                            class="rounded border-slate-300"
                        />
                        Gravar transmissão</label
                    >
                    <label
                        class="flex items-start gap-3 rounded-xl border p-4 text-sm"
                        ><input
                            v-model="isPublic"
                            type="checkbox"
                            class="mt-0.5 rounded border-slate-300"
                        />
                        <span
                            ><strong class="block">Transmissão pública</strong
                            ><small class="mt-1 block text-slate-500"
                                >Quando desmarcada, a live não aparece no
                                portal, na home ou na galeria. A gravação poderá
                                ser publicada depois.</small
                            ></span
                        ></label
                    >
                    <p v-if="error" class="text-sm font-bold text-rose-600">
                        {{ error }}
                    </p>
                </div>
                <footer class="flex justify-end gap-2 border-t bg-slate-50 p-4">
                    <button
                        type="button"
                        class="rounded-lg border px-4 py-2 text-sm font-bold"
                        @click="createOpen = false"
                    >
                        Cancelar
                    </button>
                    <button
                        :disabled="processing || !name.trim()"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                    >
                        {{ processing ? 'Criando…' : 'Criar transmissão' }}
                    </button>
                </footer>
            </form>
        </div>
    </main>
</template>
