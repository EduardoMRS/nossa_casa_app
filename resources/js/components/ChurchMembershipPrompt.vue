<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRightLeft, LogOut } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from '@/lib/i18n';

type ChurchContext = {
    membershipPending: boolean;
    church?: { name: string } | null;
};

const page = usePage();
const { t } = useI18n();
const processing = ref(false);
const context = computed(
    () => page.props.churchContext as ChurchContext | undefined,
);

const switchMembership = (): void => {
    processing.value = true;
    router.post(
        '/church-membership/switch',
        {},
        { onFinish: () => (processing.value = false) },
    );
};

const decline = (): void => {
    processing.value = true;
    router.post(
        '/church-membership/decline',
        {},
        { onFinish: () => (processing.value = false) },
    );
};
</script>

<template>
    <aside
        v-if="context?.membershipPending"
        class="border-b border-amber-300 bg-amber-50 px-4 py-3 text-amber-950"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col justify-between gap-3 sm:flex-row sm:items-center"
        >
            <div class="flex items-start gap-3">
                <AlertTriangle class="mt-0.5 size-5 shrink-0 text-amber-600" />
                <div>
                    <p class="text-sm font-black">
                        {{ t('membership.title') }}
                    </p>
                    <p class="text-xs leading-5 text-amber-800">
                        {{
                            t('membership.description', {
                                church: context.church?.name ?? '',
                            })
                        }}
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 gap-2">
                <button
                    :disabled="processing"
                    class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-3 py-2 text-xs font-black text-white"
                    @click="switchMembership"
                >
                    <ArrowRightLeft class="size-4" />
                    {{ t('membership.switch') }}
                </button>
                <button
                    :disabled="processing"
                    class="inline-flex items-center gap-2 rounded-lg border border-amber-300 px-3 py-2 text-xs font-black"
                    @click="decline"
                >
                    <LogOut class="size-4" /> {{ t('membership.leave') }}
                </button>
            </div>
        </div>
    </aside>
</template>
