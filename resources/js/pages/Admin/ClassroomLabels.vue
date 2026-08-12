<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';

defineProps<{
    label: {
        presence_id: string;
        pin: string;
        child_name: string;
        child_age: number | null;
        classroom_name: string;
        dropoff_name: string | null;
        dropoff_phone: string | null;
        qr_data_url: string;
    };
}>();

const printLabels = (): void => window.print();
</script>

<template>
    <Head title="Etiquetas de check-in" />
    <main
        class="min-h-screen bg-slate-100 p-6 text-slate-950 print:bg-white print:p-0"
    >
        <div
            class="mx-auto mb-6 flex max-w-5xl items-center justify-between print:hidden"
        >
            <div>
                <h1 class="text-2xl font-black">Etiquetas do check-in</h1>
                <p class="text-sm text-slate-500">
                    Imprima as duas etiquetas antes de fechar esta página.
                </p>
            </div>
            <button
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-700 px-5 py-3 font-bold text-white"
                @click="printLabels"
            >
                <Printer class="size-4" /> Imprimir etiquetas
            </button>
        </div>

        <section
            class="mx-auto grid max-w-5xl gap-6 md:grid-cols-2 print:grid-cols-2 print:gap-3"
        >
            <article
                class="flex min-h-80 flex-col justify-between rounded-2xl border-2 border-slate-900 bg-white p-6 print:min-h-0 print:rounded-none"
            >
                <header class="border-b-2 border-slate-900 pb-3">
                    <p class="text-xs font-black tracking-[0.2em] uppercase">
                        Etiqueta da criança
                    </p>
                    <h2 class="mt-2 text-2xl font-black">
                        {{ label.child_name }}
                    </h2>
                </header>
                <dl class="grid gap-3 py-5 text-sm">
                    <div>
                        <dt class="font-black uppercase">Idade</dt>
                        <dd>{{ label.child_age ?? '—' }} anos</dd>
                    </div>
                    <div>
                        <dt class="font-black uppercase">Sala</dt>
                        <dd>{{ label.classroom_name }}</dd>
                    </div>
                    <div>
                        <dt class="font-black uppercase">Quem deixou</dt>
                        <dd>{{ label.dropoff_name || 'Não informado' }}</dd>
                    </div>
                    <div>
                        <dt class="font-black uppercase">Contato</dt>
                        <dd>{{ label.dropoff_phone || 'Não informado' }}</dd>
                    </div>
                </dl>
                <p class="border-t pt-3 text-center text-xs font-bold">
                    Nossa Casa · Check-in seguro
                </p>
            </article>

            <article
                class="grid min-h-80 grid-cols-[1fr_11rem] gap-5 rounded-2xl border-2 border-slate-900 bg-white p-6 print:min-h-0 print:rounded-none"
            >
                <div class="flex flex-col justify-between">
                    <div>
                        <p
                            class="text-xs font-black tracking-[0.2em] uppercase"
                        >
                            Etiqueta do responsável
                        </p>
                        <h2 class="mt-3 text-xl font-black">
                            {{ label.child_name }}
                        </h2>
                        <p class="mt-1 text-sm">
                            {{ label.child_age ?? '—' }} anos ·
                            {{ label.classroom_name }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-black uppercase">
                            PIN de retirada
                        </p>
                        <p
                            class="font-mono text-4xl font-black tracking-[0.22em]"
                        >
                            {{ label.pin }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-col items-center justify-center">
                    <img
                        :src="label.qr_data_url"
                        alt="QR Code para retirada"
                        class="size-44"
                    />
                    <p class="mt-1 text-center text-[10px] font-bold">
                        Apresente no checkout
                    </p>
                </div>
            </article>
        </section>
    </main>
</template>
