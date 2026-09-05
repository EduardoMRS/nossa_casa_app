<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    Check,
    Clock3,
    Globe2,
    ImageUp,
    Languages,
    LayoutTemplate,
    MapPinned,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import BrandingController from '@/actions/App/Http/Controllers/Settings/BrandingController';
import AdminPageHeader from '@/components/AdminPageHeader.vue';
import ChurchNetworkSettings from '@/components/ChurchNetworkSettings.vue';
import InputError from '@/components/InputError.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import TemplateVariantPreview from '@/components/TemplateVariantPreview.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/lib/i18n';
import { edit } from '@/routes/admin/branding';
import type { ChurchNetworkSettingsData } from '@/types/church-network';

type AddressData = {
    country: string;
    state: string;
    city: string;
    neighborhood: string;
    street: string;
    number: string;
    complement: string;
    zipcode: string;
    latitude: number | null;
    longitude: number | null;
};

type BrandingData = {
    domain: string;
    brand_name: string;
    tagline: string;
    banner_title: string;
    banner_subtitle: string;
    primary_color: string;
    secondary_color: string;
    accent_color: string;
    surface_color: string;
    font_family: string;
    logo_path: string;
    logo_url: string;
    logo_source_church_name?: string | null;
    logo_inherited?: boolean;
    logo_fallback?: boolean;
    icon_name: string;
    contact_email: string;
    contact_phone: string;
    contact_whatsapp: string;
    address: AddressData;
    map_embed: string;
    weekly_schedule: WeeklySchedule[];
    social_links: Record<SocialNetwork, string>;
};

type SocialNetwork =
    | 'instagram'
    | 'facebook'
    | 'whatsapp'
    | 'tiktok'
    | 'youtube'
    | 'x'
    | 'telegram'
    | 'linkedin';

type WeeklySchedule = {
    title: string;
    day_of_week: number;
    start_time: string;
    end_time: string;
};

type TemplateSection =
    | 'home'
    | 'posts_index'
    | 'posts_show'
    | 'events_index'
    | 'events_show'
    | 'form'
    | 'library'
    | 'gallery';

type TemplateVariant = 'classic' | 'editorial' | 'minimal';

type TerminologySelections = {
    units: Record<'headquarters' | 'branch', string>;
    roles: Record<string, string>;
};

type TerminologyOptions = {
    units: Record<
        'headquarters' | 'branch',
        Array<{ value: string; singular: string; plural: string }>
    >;
    roles: Record<
        string,
        {
            technical_label: string;
            options: Array<{ value: string; label: string }>;
        }
    >;
};

type MailSettings = {
    enabled: boolean;
    allow_branches: boolean;
    host: string;
    port: string;
    scheme: string;
    username: string;
    from_address: string;
    from_name: string;
    has_password: boolean;
};

const props = defineProps<{
    branding: BrandingData;
    templates: Record<TemplateSection, TemplateVariant>;
    terminology: TerminologySelections;
    terminologyOptions: TerminologyOptions;
    currency: string;
    mainDomain: string;
    mailSettings: MailSettings;
    networkSettings: ChurchNetworkSettingsData;
    registrationProof: { name: string; url: string } | null;
}>();
const useOwnMailServer = ref(props.mailSettings.enabled);
const colors = ref({
    primary_color: props.branding.primary_color,
    secondary_color: props.branding.secondary_color,
    accent_color: props.branding.accent_color,
    surface_color: props.branding.surface_color,
});
const weeklySchedule = ref<WeeklySchedule[]>(
    props.branding.weekly_schedule.map((item) => ({ ...item })),
);
const templateSelections = ref<Record<TemplateSection, TemplateVariant>>({
    ...props.templates,
});
const localLogoPreview = ref('');
const removeLogo = ref(false);
const logoPreview = computed(() =>
    removeLogo.value
        ? ''
        : localLogoPreview.value || props.branding.logo_url || '',
);

const chooseLogo = (event: Event): void => {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (localLogoPreview.value) {
        URL.revokeObjectURL(localLogoPreview.value);
    }

    localLogoPreview.value = file ? URL.createObjectURL(file) : '';
    removeLogo.value = false;
};

onBeforeUnmount(() => {
    if (localLogoPreview.value) {
        URL.revokeObjectURL(localLogoPreview.value);
    }
});

const addSchedule = (): void => {
    weeklySchedule.value.push({
        title: '',
        day_of_week: 0,
        start_time: '09:00',
        end_time: '10:00',
    });
};

const removeSchedule = (index: number): void => {
    weeklySchedule.value.splice(index, 1);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'admin.branding.title',
                href: edit(),
            },
        ],
    },
});
const { t } = useI18n();
const weekdays = [
    'sunday',
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
];
const templateSections: TemplateSection[] = [
    'home',
    'posts_index',
    'posts_show',
    'events_index',
    'events_show',
    'form',
    'library',
    'gallery',
];
const templateVariants = ['classic', 'editorial', 'minimal'] as const;
const socialNetworks: SocialNetwork[] = [
    'instagram',
    'facebook',
    'whatsapp',
    'tiktok',
    'youtube',
    'x',
    'telegram',
    'linkedin',
];
const domainModes = ['internal', 'external'] as const;
const isInternalDomain =
    props.branding.domain.endsWith(`.${props.mainDomain}`) &&
    props.branding.domain !== props.mainDomain;
const domainMode = ref<'internal' | 'external'>(
    isInternalDomain ? 'internal' : 'external',
);
const domainInput = ref(
    isInternalDomain
        ? props.branding.domain.slice(0, -(props.mainDomain.length + 1))
        : props.branding.domain,
);
const normalizedDomainInput = computed(() =>
    domainInput.value
        .trim()
        .toLowerCase()
        .replace(/^https?:\/\//, '')
        .split('/')[0]
        .replace(/\.$/, ''),
);
const resolvedDomain = computed(() =>
    domainMode.value === 'internal'
        ? `${normalizedDomainInput.value}.${props.mainDomain}`
        : normalizedDomainInput.value,
);
</script>

<template>
    <Head :title="t('admin.branding.title')" />

    <div class="space-y-6 p-4 md:p-8">
        <AdminPageHeader
            :kicker="t('admin.branding.title')"
            :title="t('admin.branding.heading')"
            :description="t('admin.branding.description')"
        />

        <nav
            class="sticky top-16 z-20 flex gap-2 overflow-x-auto rounded-xl border border-border bg-background/95 p-2 shadow-sm backdrop-blur"
        >
            <a
                v-for="section in [
                    'identity',
                    'domain',
                    'network',
                    'communication',
                    'location',
                    'templates',
                    'schedule',
                ]"
                :key="section"
                :href="`#settings-${section}`"
                class="rounded-lg px-3 py-2 text-xs font-bold whitespace-nowrap text-muted-foreground hover:bg-muted hover:text-foreground"
                >{{ t(`admin.branding.groups.${section}`) }}</a
            >
        </nav>

        <Form
            v-bind="BrandingController.update.form()"
            :options="{ preserveScroll: true }"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div
                id="settings-identity"
                class="grid gap-4 rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm md:p-6"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="brand_name">{{
                            t('admin.branding.brand_name')
                        }}</Label>
                        <Input
                            id="brand_name"
                            name="brand_name"
                            :default-value="props.branding.brand_name"
                        />
                        <InputError :message="errors.brand_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tagline">{{
                            t('admin.branding.tagline')
                        }}</Label>
                        <Input
                            id="tagline"
                            name="tagline"
                            :default-value="props.branding.tagline"
                        />
                        <InputError :message="errors.tagline" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="banner_title">{{
                            t('admin.branding.banner_title')
                        }}</Label>
                        <Input
                            id="banner_title"
                            name="banner_title"
                            :default-value="props.branding.banner_title"
                        />
                        <InputError :message="errors.banner_title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="banner_subtitle">{{
                            t('admin.branding.banner_subtitle')
                        }}</Label>
                        <Input
                            id="banner_subtitle"
                            name="banner_subtitle"
                            :default-value="props.branding.banner_subtitle"
                        />
                        <InputError :message="errors.banner_subtitle" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="font_family">{{
                            t('admin.branding.font_family')
                        }}</Label>
                        <Input
                            id="font_family"
                            name="font_family"
                            :default-value="props.branding.font_family"
                        />
                        <InputError :message="errors.font_family" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="icon_name">{{
                            t('admin.branding.icon_name')
                        }}</Label>
                        <Input
                            id="icon_name"
                            name="icon_name"
                            :default-value="props.branding.icon_name"
                        />
                        <InputError :message="errors.icon_name" />
                    </div>
                </div>

                <section
                    class="grid gap-5 rounded-2xl border border-border bg-muted/30 p-4 md:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] md:p-5"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="grid size-24 shrink-0 place-items-center overflow-hidden rounded-2xl border border-border bg-background shadow-sm"
                        >
                            <img
                                v-if="logoPreview"
                                :src="logoPreview"
                                :alt="t('admin.branding.logo_preview')"
                                class="h-full w-full object-contain p-2"
                            />
                            <ImageUp
                                v-else
                                class="size-9 text-muted-foreground"
                            />
                        </div>
                        <div>
                            <h2 class="font-bold">
                                {{ t('admin.branding.logo_file') }}
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t('admin.branding.logo_hint') }}
                            </p>
                            <p
                                v-if="props.branding.logo_inherited"
                                class="mt-2 text-xs font-semibold text-sky-700"
                            >
                                {{
                                    t('admin.branding.logo_inherited', {
                                        church:
                                            props.branding
                                                .logo_source_church_name || '',
                                    })
                                }}
                            </p>
                            <p
                                v-else-if="props.branding.logo_fallback"
                                class="mt-2 text-xs font-semibold text-sky-700"
                            >
                                {{ t('admin.branding.logo_project_fallback') }}
                            </p>
                        </div>
                    </div>
                    <div class="grid gap-3">
                        <input
                            id="logo"
                            name="logo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="w-full rounded-xl border border-input bg-background p-2 text-sm text-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-2 file:text-xs file:font-bold file:text-primary-foreground"
                            @change="chooseLogo"
                        />
                        <input
                            type="hidden"
                            name="remove_logo"
                            :value="removeLogo ? 1 : 0"
                        />
                        <button
                            v-if="
                                (logoPreview || props.branding.logo_url) &&
                                !props.branding.logo_inherited &&
                                !props.branding.logo_fallback
                            "
                            type="button"
                            class="justify-self-start text-xs font-bold text-destructive hover:underline"
                            @click="removeLogo = !removeLogo"
                        >
                            {{
                                removeLogo
                                    ? t('admin.branding.keep_logo')
                                    : t('admin.branding.remove_logo')
                            }}
                        </button>
                        <InputError :message="errors.logo" />
                    </div>
                </section>

                <section
                    id="settings-domain"
                    class="grid scroll-mt-36 gap-3 border-t border-border pt-6"
                >
                    <div class="flex items-start gap-3">
                        <Globe2 class="mt-0.5 size-5 text-primary" />
                        <div>
                            <h2 class="font-bold">
                                {{ t('admin.branding.domain_title') }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{ t('admin.branding.domain_description') }}
                            </p>
                        </div>
                    </div>
                    <div class="grid max-w-2xl gap-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="mode in domainModes"
                                :key="mode"
                                class="flex cursor-pointer items-start gap-3 rounded-xl border border-input bg-background p-4"
                                :class="{
                                    'ring-2 ring-primary': domainMode === mode,
                                }"
                            >
                                <input
                                    v-model="domainMode"
                                    type="radio"
                                    :value="mode"
                                    class="mt-1"
                                />
                                <span>
                                    <strong class="block text-sm">{{
                                        t(`admin.branding.domain_${mode}`)
                                    }}</strong>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            t(
                                                `admin.branding.domain_${mode}_hint`,
                                            )
                                        }}</span
                                    >
                                </span>
                            </label>
                        </div>
                        <Label for="domain">{{
                            t('admin.branding.domain')
                        }}</Label>
                        <input
                            type="hidden"
                            name="domain"
                            :value="resolvedDomain"
                        />
                        <div
                            class="flex overflow-hidden rounded-md border border-input bg-background"
                        >
                            <Input
                                id="domain"
                                v-model="domainInput"
                                class="border-0 shadow-none focus-visible:ring-0"
                                :placeholder="
                                    domainMode === 'internal'
                                        ? t(
                                              'portal.domain.subdomain_placeholder',
                                          )
                                        : t('admin.branding.domain_placeholder')
                                "
                            />
                            <span
                                v-if="domainMode === 'internal'"
                                class="flex items-center border-l border-input bg-muted px-3 text-sm text-muted-foreground"
                                >.{{ mainDomain }}</span
                            >
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ t('admin.branding.domain_hint') }}
                        </p>
                        <InputError :message="errors.domain" />
                    </div>
                </section>

                <section class="grid gap-4 border-t border-border pt-6">
                    <div class="flex items-start gap-3">
                        <Languages class="mt-0.5 size-5 text-primary" />
                        <div>
                            <h2 class="font-bold">
                                {{ t('admin.branding.terminology_title') }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    t('admin.branding.terminology_description')
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="grid gap-2 text-sm font-bold">
                            {{ t('admin.branding.headquarters_term') }}
                            <select
                                name="terminology[units][headquarters]"
                                :value="props.terminology.units.headquarters"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm font-normal"
                            >
                                <option
                                    v-for="option in props.terminologyOptions
                                        .units.headquarters"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.singular }}
                                </option>
                            </select>
                            <InputError
                                :message="
                                    errors['terminology.units.headquarters']
                                "
                            />
                        </label>
                        <label class="grid gap-2 text-sm font-bold">
                            {{ t('admin.branding.branch_term') }}
                            <select
                                name="terminology[units][branch]"
                                :value="props.terminology.units.branch"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm font-normal"
                            >
                                <option
                                    v-for="option in props.terminologyOptions
                                        .units.branch"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.singular }}
                                </option>
                            </select>
                            <InputError
                                :message="errors['terminology.units.branch']"
                            />
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <label
                            v-for="(definition, role) in props
                                .terminologyOptions.roles"
                            :key="role"
                            class="grid gap-2 text-sm font-bold"
                        >
                            {{ definition.technical_label }}
                            <select
                                :name="`terminology[roles][${role}]`"
                                :value="props.terminology.roles[role]"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm font-normal"
                            >
                                <option
                                    v-for="option in definition.options"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError
                                :message="errors[`terminology.roles.${role}`]"
                            />
                        </label>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('admin.branding.terminology_hint') }}
                    </p>
                    <InputError :message="errors.terminology" />
                </section>

                <div
                    id="settings-communication"
                    class="grid scroll-mt-36 gap-4 md:grid-cols-3"
                >
                    <div class="grid gap-2">
                        <Label for="currency">{{
                            t('admin.branding.currency')
                        }}</Label>
                        <select
                            id="currency"
                            name="currency"
                            :value="props.currency"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="BRL">BRL</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="ARS">ARS</option>
                            <option value="PYG">PYG</option>
                            <option value="BOB">BOB</option>
                            <option value="CLP">CLP</option>
                            <option value="COP">COP</option>
                            <option value="MXN">MXN</option>
                        </select>
                        <InputError :message="errors.currency" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="primary_color">{{
                            t('admin.branding.primary_color')
                        }}</Label>
                        <div class="flex gap-2">
                            <input
                                id="primary_color"
                                v-model="colors.primary_color"
                                name="primary_color"
                                type="color"
                                class="h-10 w-14 cursor-pointer rounded-md border border-input bg-background p-1"
                            />
                            <Input
                                v-model="colors.primary_color"
                                placeholder="#2f6e79"
                            />
                        </div>
                        <InputError :message="errors.primary_color" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="secondary_color">{{
                            t('admin.branding.secondary_color')
                        }}</Label>
                        <div class="flex gap-2">
                            <input
                                id="secondary_color"
                                v-model="colors.secondary_color"
                                name="secondary_color"
                                type="color"
                                class="h-10 w-14 cursor-pointer rounded-md border border-input bg-background p-1"
                            />
                            <Input
                                v-model="colors.secondary_color"
                                placeholder="#5f7d95"
                            />
                        </div>
                        <InputError :message="errors.secondary_color" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="accent_color">{{
                            t('admin.branding.accent_color')
                        }}</Label>
                        <div class="flex gap-2">
                            <input
                                id="accent_color"
                                v-model="colors.accent_color"
                                name="accent_color"
                                type="color"
                                class="h-10 w-14 cursor-pointer rounded-md border border-input bg-background p-1"
                            />
                            <Input
                                v-model="colors.accent_color"
                                placeholder="#c88b4a"
                            />
                        </div>
                        <InputError :message="errors.accent_color" />
                    </div>
                </div>

                <section
                    class="grid min-w-0 gap-4 rounded-2xl border border-border bg-muted/30 p-4 sm:p-5"
                >
                    <div>
                        <h2 class="font-bold">
                            {{ t('admin.branding.mail_title') }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ t('admin.branding.mail_description') }}
                        </p>
                    </div>

                    <label
                        class="flex min-w-0 items-center gap-3 rounded-xl border bg-background p-4 text-sm font-bold"
                    >
                        <input
                            type="hidden"
                            name="mail[enabled]"
                            value="0"
                        />
                        <input
                            v-model="useOwnMailServer"
                            name="mail[enabled]"
                            value="1"
                            type="checkbox"
                        />
                        <span class="min-w-0">
                            {{ t('admin.branding.mail_enabled') }}
                        </span>
                    </label>

                    <div
                        v-if="useOwnMailServer"
                        class="grid min-w-0 gap-4"
                        data-test="custom-mail-server-options"
                    >
                        <p
                            class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800"
                        >
                            {{ t('admin.branding.mail_encryption_notice') }}
                        </p>

                        <label
                            class="flex min-w-0 items-center gap-3 rounded-xl border bg-background p-4 text-sm font-bold"
                        >
                            <input
                                type="hidden"
                                name="mail[allow_branches]"
                                value="0"
                            />
                            <input
                                name="mail[allow_branches]"
                                value="1"
                                type="checkbox"
                                :checked="mailSettings.allow_branches"
                            />
                            <span class="min-w-0">
                                {{ t('admin.branding.mail_allow_branches') }}
                            </span>
                        </label>

                        <div class="grid min-w-0 gap-4 md:grid-cols-3">
                            <label class="grid min-w-0 gap-2 text-sm font-bold"
                                >{{ t('admin.branding.mail_host')
                                }}<Input
                                    name="mail[host]"
                                    :default-value="mailSettings.host"
                                /><InputError
                                    :message="errors['mail.host']"
                            /></label>
                            <label class="grid min-w-0 gap-2 text-sm font-bold"
                                >{{ t('admin.branding.mail_port')
                                }}<Input
                                    name="mail[port]"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    :default-value="mailSettings.port"
                                /><InputError
                                    :message="errors['mail.port']"
                            /></label>
                            <label class="grid min-w-0 gap-2 text-sm font-bold"
                                >{{ t('admin.branding.mail_security')
                                }}<select
                                    name="mail[scheme]"
                                    :value="mailSettings.scheme"
                                    class="h-10 w-full min-w-0 max-w-full rounded-md border border-input bg-background px-3 text-sm font-normal"
                                >
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                </select></label
                            >
                            <label class="grid min-w-0 gap-2 text-sm font-bold"
                                >{{ t('admin.branding.mail_username')
                                }}<Input
                                    name="mail[username]"
                                    autocomplete="off"
                                    :default-value="mailSettings.username"
                            /></label>
                            <label class="grid min-w-0 gap-2 text-sm font-bold"
                                >{{ t('admin.branding.mail_password')
                                }}<Input
                                    name="mail[password]"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="
                                        mailSettings.has_password
                                            ? t(
                                                  'admin.branding.mail_password_saved',
                                              )
                                            : ''
                                    "
                            /></label>
                            <label class="grid min-w-0 gap-2 text-sm font-bold"
                                >{{ t('admin.branding.mail_from_address')
                                }}<Input
                                    name="mail[from_address]"
                                    type="email"
                                    :default-value="mailSettings.from_address"
                            /></label>
                            <label class="grid min-w-0 gap-2 text-sm font-bold"
                                >{{ t('admin.branding.mail_from_name')
                                }}<Input
                                    name="mail[from_name]"
                                    :default-value="mailSettings.from_name"
                            /></label>
                        </div>
                    </div>
                </section>

                <section class="grid gap-4 border-t border-border pt-6">
                    <div>
                        <h2 class="font-bold">
                            {{ t('admin.branding.social_links_title') }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ t('admin.branding.social_links_description') }}
                        </p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div
                            v-for="network in socialNetworks"
                            :key="network"
                            class="grid gap-2"
                        >
                            <Label :for="`social_${network}`">{{
                                t(`admin.branding.social_networks.${network}`)
                            }}</Label>
                            <Input
                                :id="`social_${network}`"
                                :name="`social_links[${network}]`"
                                type="url"
                                :default-value="
                                    props.branding.social_links?.[network]
                                "
                                placeholder="https://"
                            />
                            <InputError
                                :message="errors[`social_links.${network}`]"
                            />
                        </div>
                    </div>
                </section>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="surface_color">{{
                            t('admin.branding.surface_color')
                        }}</Label>
                        <div class="flex gap-2">
                            <input
                                id="surface_color"
                                v-model="colors.surface_color"
                                name="surface_color"
                                type="color"
                                class="h-10 w-14 cursor-pointer rounded-md border border-input bg-background p-1"
                            />
                            <Input
                                v-model="colors.surface_color"
                                placeholder="#f4f7fb"
                            />
                        </div>
                        <InputError :message="errors.surface_color" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="contact_email">{{
                            t('admin.branding.email')
                        }}</Label>
                        <Input
                            id="contact_email"
                            name="contact_email"
                            :default-value="props.branding.contact_email"
                        />
                        <InputError :message="errors.contact_email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact_phone">{{
                            t('admin.branding.phone')
                        }}</Label>
                        <PhoneInput
                            id="contact_phone"
                            name="contact_phone"
                            :model-value="props.branding.contact_phone"
                        />
                        <InputError :message="errors.contact_phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact_whatsapp">{{
                            t('admin.branding.whatsapp')
                        }}</Label>
                        <PhoneInput
                            id="contact_whatsapp"
                            name="contact_whatsapp"
                            :model-value="props.branding.contact_whatsapp"
                        />
                        <InputError :message="errors.contact_whatsapp" />
                    </div>
                </div>

                <section
                    id="settings-location"
                    class="grid scroll-mt-36 gap-4 border-t border-border pt-6"
                >
                    <div class="flex items-start gap-3">
                        <MapPinned class="mt-0.5 size-5 text-primary" />
                        <div>
                            <h2 class="font-bold">
                                {{ t('admin.branding.location_title') }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{ t('admin.branding.location_description') }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid gap-4 rounded-xl border border-border bg-muted/20 p-4 md:grid-cols-2"
                    >
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="address_street">{{
                                t('portal.fields.street')
                            }}</Label>
                            <Input
                                id="address_street"
                                name="address[street]"
                                :default-value="props.branding.address.street"
                            />
                            <InputError
                                :message="errors['address.street']"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="address_number">{{
                                t('portal.fields.number')
                            }}</Label>
                            <Input
                                id="address_number"
                                name="address[number]"
                                :default-value="props.branding.address.number"
                            />
                            <InputError
                                :message="errors['address.number']"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="address_complement">{{
                                t('portal.fields.complement')
                            }}</Label>
                            <Input
                                id="address_complement"
                                name="address[complement]"
                                :default-value="
                                    props.branding.address.complement
                                "
                            />
                            <InputError
                                :message="errors['address.complement']"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="address_neighborhood">{{
                                t('portal.fields.neighborhood')
                            }}</Label>
                            <Input
                                id="address_neighborhood"
                                name="address[neighborhood]"
                                :default-value="
                                    props.branding.address.neighborhood
                                "
                            />
                            <InputError
                                :message="errors['address.neighborhood']"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="address_city">{{
                                t('portal.fields.city')
                            }}</Label>
                            <Input
                                id="address_city"
                                name="address[city]"
                                :default-value="props.branding.address.city"
                            />
                            <InputError
                                :message="errors['address.city']"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="address_state">{{
                                t('portal.fields.state')
                            }}</Label>
                            <Input
                                id="address_state"
                                name="address[state]"
                                :default-value="props.branding.address.state"
                            />
                            <InputError
                                :message="errors['address.state']"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="address_zipcode">{{
                                t('portal.fields.zipcode')
                            }}</Label>
                            <Input
                                id="address_zipcode"
                                name="address[zipcode]"
                                :default-value="props.branding.address.zipcode"
                            />
                            <InputError
                                :message="errors['address.zipcode']"
                            />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="address_country">{{
                                t('portal.fields.country')
                            }}</Label>
                            <Input
                                id="address_country"
                                name="address[country]"
                                :default-value="props.branding.address.country"
                            />
                            <InputError
                                :message="errors['address.country']"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="address_latitude">{{
                                t('admin.branding.latitude')
                            }}</Label>
                            <Input
                                id="address_latitude"
                                name="address[latitude]"
                                type="number"
                                step="0.0000001"
                                min="-90"
                                max="90"
                                :default-value="
                                    props.branding.address.latitude ?? ''
                                "
                            />
                            <InputError
                                :message="errors['address.latitude']"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="address_longitude">{{
                                t('admin.branding.longitude')
                            }}</Label>
                            <Input
                                id="address_longitude"
                                name="address[longitude]"
                                type="number"
                                step="0.0000001"
                                min="-180"
                                max="180"
                                :default-value="
                                    props.branding.address.longitude ?? ''
                                "
                            />
                            <InputError
                                :message="errors['address.longitude']"
                            />
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('admin.branding.coordinates_hint') }}
                    </p>

                    <div class="grid gap-2">
                        <Label for="map_embed">{{
                            t('admin.branding.map_embed')
                        }}</Label>
                        <textarea
                            id="map_embed"
                            name="map_embed"
                            rows="4"
                            class="rounded-xl border border-input bg-background px-3 py-2 font-mono text-xs text-foreground"
                            :value="props.branding.map_embed"
                            :placeholder="
                                t('admin.branding.map_embed_placeholder')
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ t('admin.branding.map_embed_hint') }}
                        </p>
                        <InputError :message="errors.map_embed" />
                    </div>
                    <a
                        v-if="props.registrationProof"
                        :href="props.registrationProof.url"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex w-fit items-center gap-2 rounded-lg border px-3 py-2 text-sm font-bold"
                    >
                        <MapPinned class="size-4" />
                        {{ t('admin.branding.registration_proof') }}
                        <span class="font-normal text-muted-foreground">
                            {{ props.registrationProof.name }}
                        </span>
                    </a>
                </section>

                <section
                    id="settings-templates"
                    class="grid scroll-mt-36 gap-4 border-t border-border pt-6"
                >
                    <div class="flex items-start gap-3">
                        <LayoutTemplate class="mt-0.5 size-5 text-primary" />
                        <div>
                            <h2 class="font-bold">
                                {{ t('admin.branding.templates_title') }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{ t('admin.branding.templates_description') }}
                            </p>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('admin.branding.templates_preview_hint') }}
                    </p>
                    <div class="grid gap-4 xl:grid-cols-2">
                        <fieldset
                            v-for="section in templateSections"
                            :key="section"
                            class="rounded-xl border border-border bg-muted/20 p-3"
                        >
                            <legend
                                class="px-1 text-sm font-bold text-foreground"
                            >
                                {{
                                    t(
                                        `admin.branding.template_sections.${section}`,
                                    )
                                }}
                            </legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <label
                                    v-for="variant in templateVariants"
                                    :key="variant"
                                    class="group relative grid min-w-0 cursor-pointer gap-2 rounded-xl border bg-background p-2 transition focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 hover:-translate-y-0.5 hover:border-primary/60 hover:shadow-sm"
                                    :class="
                                        templateSelections[section] === variant
                                            ? 'border-primary ring-1 ring-primary'
                                            : 'border-border'
                                    "
                                >
                                    <input
                                        v-model="templateSelections[section]"
                                        type="radio"
                                        class="sr-only"
                                        :name="`templates[${section}]`"
                                        :value="variant"
                                    />
                                    <TemplateVariantPreview
                                        :section="section"
                                        :variant="variant"
                                        :primary-color="colors.primary_color"
                                        :secondary-color="
                                            colors.secondary_color
                                        "
                                        :surface-color="colors.surface_color"
                                    />
                                    <span
                                        class="flex items-center justify-center gap-1 text-center text-[11px] font-semibold text-foreground sm:text-xs"
                                    >
                                        <Check
                                            v-if="
                                                templateSelections[section] ===
                                                variant
                                            "
                                            class="size-3 text-primary"
                                        />
                                        {{
                                            t(
                                                `admin.branding.template_variants.${variant}`,
                                            )
                                        }}
                                    </span>
                                    <span
                                        class="line-clamp-2 px-0.5 text-center text-[10px] leading-4 text-muted-foreground"
                                    >
                                        {{
                                            t(
                                                `admin.branding.template_variant_descriptions.${variant}`,
                                            )
                                        }}
                                    </span>
                                </label>
                            </div>
                            <InputError
                                class="mt-2"
                                :message="errors[`templates.${section}`]"
                            />
                        </fieldset>
                    </div>
                    <InputError :message="errors.templates" />
                </section>

                <section
                    id="settings-schedule"
                    class="grid scroll-mt-36 gap-4 border-t border-border pt-6"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="flex items-start gap-3">
                            <Clock3 class="mt-0.5 size-5 text-primary" />
                            <div>
                                <h2 class="font-bold">
                                    {{ t('admin.branding.schedule_title') }}
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        t('admin.branding.schedule_description')
                                    }}
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            @click="addSchedule"
                        >
                            <Plus class="size-4" />
                            {{ t('admin.branding.schedule_add') }}
                        </Button>
                    </div>

                    <div v-if="weeklySchedule.length" class="grid gap-3">
                        <article
                            v-for="(schedule, index) in weeklySchedule"
                            :key="index"
                            class="grid gap-3 rounded-xl border border-border bg-muted/40 p-4 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_8rem_8rem_auto] md:items-end"
                        >
                            <div class="grid gap-2">
                                <Label :for="`schedule_title_${index}`">{{
                                    t('admin.branding.schedule_name')
                                }}</Label>
                                <Input
                                    :id="`schedule_title_${index}`"
                                    v-model="schedule.title"
                                    :name="`weekly_schedule[${index}][title]`"
                                    required
                                />
                                <InputError
                                    :message="
                                        errors[`weekly_schedule.${index}.title`]
                                    "
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`schedule_day_${index}`">{{
                                    t('admin.branding.schedule_day')
                                }}</Label>
                                <select
                                    :id="`schedule_day_${index}`"
                                    v-model.number="schedule.day_of_week"
                                    :name="`weekly_schedule[${index}][day_of_week]`"
                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option
                                        v-for="(weekday, dayIndex) in weekdays"
                                        :key="weekday"
                                        :value="dayIndex"
                                    >
                                        {{ t(`weekdays.${weekday}`) }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`schedule_start_${index}`">{{
                                    t('admin.branding.schedule_start')
                                }}</Label>
                                <Input
                                    :id="`schedule_start_${index}`"
                                    v-model="schedule.start_time"
                                    :name="`weekly_schedule[${index}][start_time]`"
                                    type="time"
                                    required
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`schedule_end_${index}`">{{
                                    t('admin.branding.schedule_end')
                                }}</Label>
                                <Input
                                    :id="`schedule_end_${index}`"
                                    v-model="schedule.end_time"
                                    :name="`weekly_schedule[${index}][end_time]`"
                                    type="time"
                                    required
                                />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="text-destructive"
                                :aria-label="
                                    t('admin.branding.schedule_remove')
                                "
                                @click="removeSchedule(index)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </article>
                    </div>
                    <p
                        v-else
                        class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                    >
                        {{ t('admin.branding.schedule_empty') }}
                    </p>
                    <InputError :message="errors.weekly_schedule" />
                </section>

                <div class="flex items-center gap-4 pt-2">
                    <Button :disabled="processing">{{
                        t('admin.branding.save')
                    }}</Button>
                </div>
            </div>
        </Form>
    </div>
</template>
