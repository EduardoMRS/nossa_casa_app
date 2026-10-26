<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Download, Wallet } from '@lucide/vue';
import { ref } from 'vue';
import AdminPageHeader from '@/components/AdminPageHeader.vue';
import { useI18n } from '@/lib/i18n';

type Settings = {
    pix_key_type: string | null;
    pix_key: string | null;
    bank_name: string | null;
    bank_account: string | null;
    account_holder: string | null;
    instructions: string | null;
    accepts_contributions: boolean;
};
type Report = {
    month: string;
    tithe_total: string;
    offering_total: string;
    total: string;
    count: number;
};

const props = defineProps<{
    settings: Settings;
    report: Report;
}>();

const { t } = useI18n();

const form = useForm({
    pix_key_type: props.settings.pix_key_type ?? '',
    pix_key: props.settings.pix_key ?? '',
    bank_name: props.settings.bank_name ?? '',
    bank_account: props.settings.bank_account ?? '',
    account_holder: props.settings.account_holder ?? '',
    instructions: props.settings.instructions ?? '',
    accepts_contributions: props.settings.accepts_contributions,
});

const exportMonth = ref(props.report.month);

const save = (): void => {
    form.put('/dashboard/treasury', { preserveScroll: true });
};

const exportCsv = (): void => {
    window.location.href = `/dashboard/treasury/export?month=${encodeURIComponent(exportMonth.value)}`;
};
</script>

<template>
    <Head :title="t('admin.treasury.title')" />
    <main class="space-y-6 p-4 md:p-8">
        <AdminPageHeader
            :kicker="t('admin.treasury.kicker')"
            :title="t('admin.treasury.title')"
            :description="t('admin.treasury.description')"
        />

        <section
            class="grid gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm sm:grid-cols-4"
        >
            <div>
                <p class="text-xs text-muted-foreground">
                    {{ t('admin.treasury.tithes') }}
                </p>
                <p class="text-xl font-black">{{ report.tithe_total }}</p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground">
                    {{ t('admin.treasury.offerings') }}
                </p>
                <p class="text-xl font-black">{{ report.offering_total }}</p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground">
                    {{ t('admin.treasury.total') }}
                </p>
                <p class="text-xl font-black">{{ report.total }}</p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground">
                    {{ t('admin.treasury.count') }}
                </p>
                <p class="text-xl font-black">{{ report.count }}</p>
            </div>
        </section>

        <section
            class="flex flex-wrap items-end gap-3 rounded-2xl border border-border bg-card p-5 shadow-sm"
        >
            <label class="text-xs font-bold text-foreground"
                >{{ t('admin.treasury.month')
                }}<input
                    v-model="exportMonth"
                    type="month"
                    class="mt-1 block rounded-lg border-input bg-background text-foreground"
            /></label>
            <button
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground"
                @click="exportCsv"
            >
                <Download class="size-4" />{{ t('admin.treasury.export') }}
            </button>
        </section>

        <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <div class="mb-4 flex items-center gap-2 font-black">
                <Wallet class="size-5 text-indigo-600" />{{
                    t('admin.treasury.title')
                }}
            </div>
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                <label class="text-xs font-bold text-foreground"
                    >{{ t('admin.treasury.pix_key_type')
                    }}<input
                        v-model="form.pix_key_type"
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                /></label>
                <label class="text-xs font-bold text-foreground"
                    >{{ t('admin.treasury.pix_key')
                    }}<input
                        v-model="form.pix_key"
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                /></label>
                <label class="text-xs font-bold text-foreground"
                    >{{ t('admin.treasury.bank_name')
                    }}<input
                        v-model="form.bank_name"
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                /></label>
                <label class="text-xs font-bold text-foreground"
                    >{{ t('admin.treasury.bank_account')
                    }}<input
                        v-model="form.bank_account"
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                /></label>
                <label class="text-xs font-bold text-foreground"
                    >{{ t('admin.treasury.account_holder')
                    }}<input
                        v-model="form.account_holder"
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                /></label>
                <label
                    class="flex items-center gap-2 text-xs font-bold text-foreground sm:col-span-2"
                >
                    <input
                        v-model="form.accepts_contributions"
                        type="checkbox"
                    />
                    {{ t('admin.treasury.accepts') }}
                </label>
                <label class="text-xs font-bold text-foreground sm:col-span-2"
                    >{{ t('admin.treasury.instructions')
                    }}<textarea
                        v-model="form.instructions"
                        rows="3"
                        class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                    ></textarea>
                </label>
                <div class="sm:col-span-2">
                    <button
                        :disabled="form.processing"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground disabled:opacity-50"
                    >
                        {{ t('admin.treasury.save') }}
                    </button>
                </div>
            </form>
        </section>
    </main>
</template>
