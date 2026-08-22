<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from '@/lib/i18n';
import { home, login, register } from '@/routes';
import { privacy } from '@/routes/legal';

defineProps<{ userChurchUrl?: string | null }>();

const page = usePage();
const { t } = useI18n();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const branding = computed(
    () =>
        (page.props.branding as
            { logo_url?: string; brand_name?: string } | undefined) ?? {},
);
</script>

<template>
    <header class="border-b border-indigo-900/60 bg-[#312e81] text-white">
        <div
            class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-2 px-3 sm:h-18 sm:px-5 lg:px-8"
        >
            <Link :href="home()" class="flex items-center gap-3">
                <span
                    class="grid size-9 place-items-center overflow-hidden rounded-xl border border-white/25 bg-white/10 p-1 sm:size-11"
                >
                    <img
                        :src="branding.logo_url || '/branding/logo'"
                        :alt="branding.brand_name || t('portal.brand_name')"
                        class="size-full object-contain"
                    />
                </span>
                <span class="hidden sm:block">
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
                    class="rounded-xl bg-white px-3 py-2.5 text-xs font-black text-indigo-950 sm:px-4"
                    >{{ t('portal.open_my_church') }}</a
                >
                <template v-else-if="!isAuthenticated">
                    <Link
                        :href="login()"
                        class="rounded-xl px-2 py-2.5 text-xs font-bold text-indigo-100 sm:px-4"
                        >{{ t('nav.login') }}</Link
                    >
                    <Link
                        :href="register()"
                        class="rounded-xl bg-white px-3 py-2.5 text-xs font-black text-indigo-950 sm:px-4"
                        >{{ t('portal.create_account') }}</Link
                    >
                </template>
                <a
                    :href="privacy().url"
                    class="hidden rounded-xl px-2 py-2.5 text-xs font-bold text-indigo-100 transition hover:bg-white/10 hover:text-white lg:inline-flex"
                >
                    {{ t('footer.privacy_terms') }}
                </a>
            </div>
        </div>
    </header>
</template>
