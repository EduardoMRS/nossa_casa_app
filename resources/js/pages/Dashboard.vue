<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { useTerminology } from '@/composables/useTerminology';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';

type ModuleItem = {
    title_key: string;
    description_key: string;
    href: string;
};

const props = defineProps<{
    role: string;
    context: 'church' | 'platform';
    kpis: Record<string, number>;
    modules: ModuleItem[];
}>();

const { t } = useI18n();
const { roleLabel: terminologyRoleLabel } = useTerminology();
const page = usePage<{
    branding?: {
        brand_name?: string;
        tagline?: string;
        banner_title?: string;
        banner_subtitle?: string;
        primary_color?: string;
        contact_email?: string;
    };
}>();

const branding = computed(() => page.props.branding ?? {});
const isPlatformDashboard = computed(() => props.context === 'platform');
const kpiItems = computed(() =>
    Object.entries(props.kpis).map(([key, value]) => ({
        key,
        value,
        label: t(`dashboard.kpis.${key}`),
    })),
);
const { resolvedAppearance, updateAppearance } = useAppearance();

const paletteStyle = computed(() => ({
    '--dashboard-primary': branding.value.primary_color ?? '#342f87',
}));

const roleLabel = computed(() => {
    return terminologyRoleLabel(props.role);
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'nav.dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head
        :title="
            isPlatformDashboard
                ? t('dashboard.platform.title')
                : t('dashboard.title')
        "
    />

    <div
        class="flex h-full min-w-0 max-w-full flex-1 flex-col gap-6 overflow-x-clip bg-background p-4 text-foreground md:p-8"
        :style="paletteStyle"
    >
        <section
            class="relative overflow-hidden rounded-2xl border border-slate-900/20 p-6 text-white shadow-sm md:p-8"
            :style="{ backgroundColor: 'var(--dashboard-primary)' }"
        >
            <button
                type="button"
                class="absolute top-4 right-4 grid size-10 place-items-center rounded-full border border-white/25 bg-black/15 text-white backdrop-blur-sm transition hover:bg-black/25"
                :aria-label="
                    resolvedAppearance === 'dark'
                        ? t('dashboard.activate_light')
                        : t('dashboard.activate_dark')
                "
                @click="
                    updateAppearance(
                        resolvedAppearance === 'dark' ? 'light' : 'dark',
                    )
                "
            >
                <Sun v-if="resolvedAppearance === 'dark'" class="size-4" />
                <Moon v-else class="size-4" />
            </button>
            <p
                class="mb-1 text-xs tracking-[0.2em] text-slate-100/80 uppercase"
            >
                {{
                    isPlatformDashboard
                        ? t('dashboard.platform.workspace')
                        : t('dashboard.workspace')
                }}
            </p>
            <h1 class="text-2xl leading-tight font-black break-words sm:text-3xl md:text-4xl">
                {{
                    isPlatformDashboard
                        ? t('dashboard.platform.title')
                        : branding.banner_title || t('dashboard.title')
                }}
            </h1>
            <p class="mt-2 text-sm text-slate-100/90 md:text-base">
                {{
                    isPlatformDashboard
                        ? t('dashboard.platform.description')
                        : branding.banner_subtitle ||
                          t('dashboard.profile_context', { role: roleLabel })
                }}
            </p>
            <p
                v-if="!isPlatformDashboard && branding.contact_email"
                class="mt-3 text-xs font-semibold break-all text-slate-100/80"
            >
                {{ branding.contact_email }}
            </p>
        </section>

        <section
            class="grid gap-4 sm:grid-cols-2"
            :class="
                kpiItems.length > 3
                    ? 'xl:grid-cols-4'
                    : 'xl:grid-cols-3'
            "
        >
            <article
                v-for="item in kpiItems"
                :key="item.key"
                class="min-w-0 rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm"
            >
                <p
                    class="text-xs tracking-[0.14em] text-muted-foreground uppercase"
                >
                    {{ item.label }}
                </p>
                <p class="mt-2 text-3xl font-black text-card-foreground">
                    {{ item.value }}
                </p>
            </article>
        </section>

        <section>
            <div class="mb-3">
                <p
                    class="text-xs tracking-[0.2em] text-muted-foreground uppercase"
                >
                    {{ t('dashboard.modules') }}
                </p>
                <h2 class="text-2xl font-black break-words text-foreground">
                    {{ t('dashboard.recommended_actions') }}
                </h2>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="module in modules"
                    :key="module.title_key"
                    class="min-w-0 rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm"
                >
                    <h3 class="text-lg font-black text-card-foreground">
                        {{ t(module.title_key) }}
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t(module.description_key) }}
                    </p>
                    <Link
                        :href="module.href"
                        class="mt-4 inline-flex rounded-full px-4 py-2 text-xs font-bold text-white"
                        :style="{ backgroundColor: 'var(--dashboard-primary)' }"
                    >
                        {{ t('dashboard.open_module') }}
                    </Link>
                </article>
            </div>
        </section>
    </div>
</template>
