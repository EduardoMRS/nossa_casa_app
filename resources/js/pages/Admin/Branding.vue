<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Clock3, Globe2, ImageUp, MapPinned, Plus, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import BrandingController from '@/actions/App/Http/Controllers/Settings/BrandingController';
import AdminPageHeader from '@/components/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/lib/i18n';
import { edit } from '@/routes/admin/branding';

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
    icon_name: string;
    contact_email: string;
    contact_phone: string;
    contact_whatsapp: string;
    address: string;
    map_embed: string;
    weekly_schedule: WeeklySchedule[];
};

type WeeklySchedule = {
    title: string;
    day_of_week: number;
    start_time: string;
    end_time: string;
};

const props = defineProps<{
    branding: BrandingData;
}>();
const colors = ref({
    primary_color: props.branding.primary_color,
    secondary_color: props.branding.secondary_color,
    accent_color: props.branding.accent_color,
    surface_color: props.branding.surface_color,
});
const weeklySchedule = ref<WeeklySchedule[]>(
    props.branding.weekly_schedule.map((item) => ({ ...item })),
);
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
</script>

<template>
    <Head :title="t('admin.branding.title')" />

    <div class="space-y-6 p-4 md:p-8">
        <AdminPageHeader
            :kicker="t('admin.branding.title')"
            :title="t('admin.branding.heading')"
            :description="t('admin.branding.description')"
        />

        <Form
            v-bind="BrandingController.update.form()"
            :options="{ preserveScroll: true }"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div
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
                            v-if="logoPreview || props.branding.logo_url"
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

                <section class="grid gap-3 border-t border-border pt-6">
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
                    <div class="grid max-w-2xl gap-2">
                        <Label for="domain">{{
                            t('admin.branding.domain')
                        }}</Label>
                        <Input
                            id="domain"
                            name="domain"
                            :default-value="props.branding.domain"
                            placeholder="igreja.exemplo.com"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ t('admin.branding.domain_hint') }}
                        </p>
                        <InputError :message="errors.domain" />
                    </div>
                </section>

                <div class="grid gap-4 md:grid-cols-3">
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
                        <Label for="contact_email">Email</Label>
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
                        <Input
                            id="contact_phone"
                            name="contact_phone"
                            :default-value="props.branding.contact_phone"
                        />
                        <InputError :message="errors.contact_phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact_whatsapp">WhatsApp</Label>
                        <Input
                            id="contact_whatsapp"
                            name="contact_whatsapp"
                            :default-value="props.branding.contact_whatsapp"
                        />
                        <InputError :message="errors.contact_whatsapp" />
                    </div>
                </div>

                <section class="grid gap-4 border-t border-border pt-6">
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

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="address">{{
                                t('admin.branding.address')
                            }}</Label>
                            <textarea
                                id="address"
                                name="address"
                                rows="4"
                                class="rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground"
                                :value="props.branding.address"
                            />
                            <InputError :message="errors.address" />
                        </div>
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
                    </div>
                </section>

                <section class="grid gap-4 border-t border-border pt-6">
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
