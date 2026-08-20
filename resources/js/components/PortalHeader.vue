<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from '@/lib/i18n';
import { home, login, register } from '@/routes';

defineProps<{ userChurchUrl?: string | null }>();

const page = usePage();
const { t } = useI18n();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
</script>

<template>
    <header class="border-b border-indigo-900/60 bg-[#312e81] text-white">
        <div
            class="mx-auto flex h-18 max-w-7xl items-center justify-between px-5 lg:px-8"
        >
            <Link :href="home()" class="flex items-center gap-3">
                <span
                    class="grid size-11 place-items-center rounded-xl border border-white/25 bg-white/10 text-lg font-black"
                    >{{ t('portal.brand_initials') }}</span
                >
                <span>
                    <strong class="block text-base leading-none">{{
                        t('portal.brand_name')
                    }}</strong>
                    <small
                        class="font-mono text-[9px] tracking-[0.22em] text-indigo-200 uppercase"
                        >{{ t('portal.network_label') }}</small
                    >
                </span>
            </Link>
            <div class="flex items-center gap-2">
                <a
                    v-if="userChurchUrl"
                    :href="userChurchUrl"
                    class="rounded-xl bg-white px-4 py-2.5 text-xs font-black text-indigo-950"
                    >{{ t('portal.open_my_church') }}</a
                >
                <template v-else-if="!isAuthenticated">
                    <Link
                        :href="login()"
                        class="rounded-xl px-4 py-2.5 text-xs font-bold text-indigo-100"
                        >{{ t('nav.login') }}</Link
                    >
                    <Link
                        :href="register()"
                        class="rounded-xl bg-white px-4 py-2.5 text-xs font-black text-indigo-950"
                        >{{ t('portal.create_account') }}</Link
                    >
                </template>
            </div>
        </div>
    </header>
</template>
