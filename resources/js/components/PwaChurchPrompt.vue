<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AppModal from '@/components/AppModal.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/lib/i18n';
import {
    currentPwaChurchId,
    isStandalonePwa,
    persistPwaChurchId,
    pwaChurchDaysRemaining,
} from '@/lib/pwa';
import { login, register } from '@/routes';
import { switchMethod } from '@/routes/church/membership';

type PromptMode = 'initial' | 'switch' | 'membership' | null;

const page = usePage<{
    auth?: { user?: { id?: string } | null };
    churchContext?: {
        isMainDomain?: boolean;
        church?: { id: string; name: string } | null;
        userChurch?: { id: string } | null;
    };
}>();
const { t } = useI18n();
const prompt = ref<PromptMode>(null);
const standalone = ref(false);

const church = computed(() => page.props.churchContext?.church ?? null);
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const isMainDomain = computed(
    () => page.props.churchContext?.isMainDomain === true,
);
const eligiblePage = computed(() =>
    ['Home', 'Portal/Index', 'Portal/CommunityShow'].includes(page.component),
);
const belongsToChurch = computed(
    () => page.props.churchContext?.userChurch?.id === church.value?.id,
);
const redirect = computed(() => {
    if (!church.value || typeof window === 'undefined') {
        return page.url;
    }

    const target = new URL(page.url, window.location.origin);
    target.searchParams.set('pwa', '1');
    target.searchParams.set('church_id', church.value.id);

    if (prompt.value === 'membership') {
        target.searchParams.set('pwa_membership', '1');
    }

    return target.pathname + target.search + target.hash;
});
const loginUrl = computed(() => login({ query: { redirect: redirect.value } }));
const registerUrl = computed(() =>
    register({ query: { redirect: redirect.value } }),
);

const dismissKey = computed(
    () =>
        `ncapp:pwa-church-prompt:${church.value?.id ?? 'none'}:${prompt.value}`,
);

const wasDismissed = (mode: Exclude<PromptMode, null>): boolean =>
    typeof window !== 'undefined' &&
    Boolean(church.value) &&
    window.sessionStorage.getItem(
        `ncapp:pwa-church-prompt:${church.value?.id}:${mode}`,
    ) === '1';

const evaluate = (): void => {
    if (
        !standalone.value ||
        !isMainDomain.value ||
        !eligiblePage.value ||
        !church.value
    ) {
        prompt.value = null;

        return;
    }

    const storedChurchId = currentPwaChurchId();

    if (!storedChurchId) {
        if (isAuthenticated.value) {
            persistPwaChurchId(church.value.id, 15);

            return;
        }

        if (!wasDismissed('initial')) {
            prompt.value = 'initial';
        }

        return;
    }

    if (storedChurchId !== church.value.id) {
        if (!wasDismissed('switch')) {
            prompt.value = 'switch';
        }

        return;
    }

    if (!belongsToChurch.value && (pwaChurchDaysRemaining() ?? 99) <= 3) {
        if (!wasDismissed('membership')) {
            prompt.value = 'membership';
        }
    }
};

const keepChurch = (days: number): void => {
    if (!church.value) {
        return;
    }

    persistPwaChurchId(church.value.id, days);
    prompt.value = null;
};

const dismiss = (): void => {
    window.sessionStorage.setItem(dismissKey.value, '1');
    prompt.value = null;
};

const transferMembership = (): void => {
    router.post(
        switchMethod(),
        { confirmed: true },
        { onSuccess: () => keepChurch(30) },
    );
};

watch([() => page.component, () => church.value?.id, standalone], evaluate);

onMounted(() => {
    standalone.value = isStandalonePwa();
    evaluate();
});
</script>

<template>
    <AppModal
        v-if="prompt"
        :open="true"
        :title="t(`pwa_church.${prompt}.title`)"
        size="md"
        @update:open="(value) => !value && dismiss()"
    >
        <p class="text-sm leading-6 text-muted-foreground">
            {{
                t(`pwa_church.${prompt}.description`, {
                    church: church?.name ?? '',
                })
            }}
        </p>

        <template #footer>
            <template v-if="prompt === 'membership' && !isAuthenticated">
                <Link
                    :href="loginUrl"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-bold"
                >
                    {{ t('pwa_church.membership.login') }}
                </Link>
                <Link
                    :href="registerUrl"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground"
                >
                    {{ t('pwa_church.membership.register') }}
                </Link>
            </template>
            <Button
                v-else-if="prompt === 'membership'"
                type="button"
                variant="outline"
                @click="transferMembership"
            >
                {{ t('pwa_church.membership.join') }}
            </Button>
            <Button
                type="button"
                variant="outline"
                @click="
                    prompt === 'initial' || prompt === 'switch'
                        ? keepChurch(15)
                        : keepChurch(30)
                "
            >
                {{
                    t(
                        prompt === 'membership'
                            ? 'pwa_church.membership.continue'
                            : 'pwa_church.keep',
                    )
                }}
            </Button>
        </template>
    </AppModal>
</template>
