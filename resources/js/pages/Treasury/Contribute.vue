<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { HandCoins } from '@lucide/vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';

type TreasuryInfo = {
    pix_key_type: string | null;
    pix_key: string | null;
    bank_name: string | null;
    bank_account: string | null;
    account_holder: string | null;
    instructions: string | null;
};
type TypeOption = { value: string; label: string };

const props = defineProps<{
    church: { id: string; name: string };
    treasury: TreasuryInfo | null;
    types: TypeOption[];
}>();

const { t } = useI18n();

const form = useForm({
    type: props.types[0]?.value ?? 'tithe',
    amount: '',
    contributor_name: '',
    message: '',
});

const submit = (): void => {
    form.post(window.location.pathname, {
        preserveScroll: true,
        onSuccess: () => form.reset('amount', 'message'),
    });
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <Head :title="t('treasury.title')" />
        <PublicHeader />
        <main class="mx-auto w-full max-w-2xl flex-1 px-4 py-10">
            <header class="mb-8">
                <h1 class="flex items-center gap-2 text-2xl font-black">
                    <HandCoins class="size-6 text-indigo-600" />{{
                        t('treasury.title')
                    }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('treasury.subtitle') }}
                </p>
            </header>

            <p
                v-if="!treasury"
                class="rounded-2xl border border-border bg-card p-12 text-center text-sm text-muted-foreground"
            >
                {{ t('treasury.closed') }}
            </p>

            <template v-else>
                <section
                    class="mb-6 rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                    <h2 class="mb-2 font-black">
                        {{ t('treasury.how_to') }}
                    </h2>
                    <dl class="space-y-1 text-sm">
                        <div v-if="treasury.pix_key" class="flex gap-2">
                            <dt class="font-bold">{{ t('treasury.pix') }}:</dt>
                            <dd>
                                {{ treasury.pix_key }}
                                <span
                                    v-if="treasury.pix_key_type"
                                    class="text-muted-foreground"
                                    >({{ treasury.pix_key_type }})</span
                                >
                            </dd>
                        </div>
                        <div v-if="treasury.bank_account" class="flex gap-2">
                            <dt class="font-bold">{{ t('treasury.bank') }}:</dt>
                            <dd>
                                {{ treasury.bank_name }}
                                {{ treasury.bank_account }}
                                <span v-if="treasury.account_holder"
                                    >— {{ treasury.account_holder }}</span
                                >
                            </dd>
                        </div>
                        <p
                            v-if="treasury.instructions"
                            class="pt-2 text-muted-foreground"
                        >
                            {{ treasury.instructions }}
                        </p>
                    </dl>
                </section>

                <form
                    class="space-y-4 rounded-2xl border border-border bg-card p-5 shadow-sm"
                    @submit.prevent="submit"
                >
                    <label class="block text-xs font-bold text-foreground"
                        >{{ t('treasury.type')
                        }}<select
                            v-model="form.type"
                            class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                        >
                            <option
                                v-for="option in types"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ t(`treasury.types.${option.value}`) }}
                            </option>
                        </select></label
                    >
                    <label class="block text-xs font-bold text-foreground"
                        >{{ t('treasury.amount')
                        }}<input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                    /></label>
                    <p v-if="form.errors.amount" class="text-xs text-rose-600">
                        {{ form.errors.amount }}
                    </p>
                    <label class="block text-xs font-bold text-foreground"
                        >{{ t('treasury.name')
                        }}<input
                            v-model="form.contributor_name"
                            class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                    /></label>
                    <label class="block text-xs font-bold text-foreground"
                        >{{ t('treasury.message')
                        }}<textarea
                            v-model="form.message"
                            rows="2"
                            class="mt-1 w-full rounded-lg border-input bg-background text-foreground"
                        ></textarea>
                    </label>
                    <button
                        :disabled="form.processing"
                        class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-black text-primary-foreground disabled:opacity-50"
                    >
                        {{ t('treasury.submit') }}
                    </button>
                </form>
            </template>
        </main>
        <PublicFooter />
    </div>
</template>
