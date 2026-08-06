<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import BrandingController from '@/actions/App/Http/Controllers/Settings/BrandingController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/admin/branding';

type BrandingData = {
    brand_name: string;
    tagline: string;
    banner_title: string;
    banner_subtitle: string;
    primary_color: string;
    secondary_color: string;
    accent_color: string;
    surface_color: string;
    font_family: string;
    logo_url: string;
    icon_name: string;
    contact_email: string;
    contact_phone: string;
    contact_whatsapp: string;
    contact_website: string;
};

const props = defineProps<{
    branding: BrandingData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Identidade visual',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Identidade visual" />

    <div class="space-y-6 p-4 md:p-6">
        <Heading
            variant="small"
            title="Identidade Visual do Templo"
            description="Configure cores, titulos, logotipo e canais de contato usados na experiencia privada e publica."
        />

        <Form
            v-bind="BrandingController.update.form()"
            :options="{ preserveScroll: true }"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm md:p-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="brand_name">Nome da marca</Label>
                        <Input id="brand_name" name="brand_name" :default-value="props.branding.brand_name" />
                        <InputError :message="errors.brand_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tagline">Tagline</Label>
                        <Input id="tagline" name="tagline" :default-value="props.branding.tagline" />
                        <InputError :message="errors.tagline" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="banner_title">Titulo do banner</Label>
                        <Input id="banner_title" name="banner_title" :default-value="props.branding.banner_title" />
                        <InputError :message="errors.banner_title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="banner_subtitle">Subtitulo do banner</Label>
                        <Input id="banner_subtitle" name="banner_subtitle" :default-value="props.branding.banner_subtitle" />
                        <InputError :message="errors.banner_subtitle" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="font_family">Familia tipografica</Label>
                        <Input id="font_family" name="font_family" :default-value="props.branding.font_family" />
                        <InputError :message="errors.font_family" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="icon_name">Icone principal</Label>
                        <Input id="icon_name" name="icon_name" :default-value="props.branding.icon_name" />
                        <InputError :message="errors.icon_name" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="logo_url">URL do logotipo</Label>
                        <Input id="logo_url" name="logo_url" :default-value="props.branding.logo_url" />
                        <InputError :message="errors.logo_url" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact_website">Website</Label>
                        <Input id="contact_website" name="contact_website" :default-value="props.branding.contact_website" />
                        <InputError :message="errors.contact_website" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="primary_color">Cor primaria</Label>
                        <Input id="primary_color" name="primary_color" :default-value="props.branding.primary_color" placeholder="#2f6e79" />
                        <InputError :message="errors.primary_color" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="secondary_color">Cor secundaria</Label>
                        <Input id="secondary_color" name="secondary_color" :default-value="props.branding.secondary_color" placeholder="#5f7d95" />
                        <InputError :message="errors.secondary_color" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="accent_color">Cor de destaque</Label>
                        <Input id="accent_color" name="accent_color" :default-value="props.branding.accent_color" placeholder="#c88b4a" />
                        <InputError :message="errors.accent_color" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="surface_color">Cor de superficie</Label>
                        <Input id="surface_color" name="surface_color" :default-value="props.branding.surface_color" placeholder="#f4f7fb" />
                        <InputError :message="errors.surface_color" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="contact_email">Email</Label>
                        <Input id="contact_email" name="contact_email" :default-value="props.branding.contact_email" />
                        <InputError :message="errors.contact_email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact_phone">Telefone</Label>
                        <Input id="contact_phone" name="contact_phone" :default-value="props.branding.contact_phone" />
                        <InputError :message="errors.contact_phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact_whatsapp">WhatsApp</Label>
                        <Input id="contact_whatsapp" name="contact_whatsapp" :default-value="props.branding.contact_whatsapp" />
                        <InputError :message="errors.contact_whatsapp" />
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <Button :disabled="processing">Salvar configuracoes visuais</Button>
                </div>
            </div>
        </Form>
    </div>
</template>
