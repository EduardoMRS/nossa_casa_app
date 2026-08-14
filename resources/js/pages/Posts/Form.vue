<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed } from 'vue';
import { store, update } from '@/actions/App/Http/Controllers/PostController';
import CategorySelector from '@/components/CategorySelector.vue';
import MarkdownWysiwyg from '@/components/MarkdownWysiwyg.vue';
import { useI18n } from '@/lib/i18n';
import { index } from '@/routes/posts';

type PostResource = {
    id?: string;
    title: string;
    slug: string;
    content: string;
    published_at?: string | null;
    expires_at?: string | null;
    category_ids?: string[];
    form_id?: string | null;
};

type CategoryOption = {
    id: string;
    name: string;
    slug: string;
    type: string;
};

type FormOption = {
    id: string;
    title: string;
    description: string | null;
};

const props = defineProps<{
    post?: PostResource;
    categories: CategoryOption[];
    forms: FormOption[];
}>();

const { t } = useI18n();
const isEditing = computed(() => Boolean(props.post?.id));

const toDateTimeLocal = (value?: string | null) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const tzOffset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - tzOffset).toISOString().slice(0, 16);
};

const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    content: props.post?.content ?? '',
    published_at: toDateTimeLocal(props.post?.published_at),
    expires_at: toDateTimeLocal(props.post?.expires_at),
    category_ids: props.post?.category_ids ? [...props.post.category_ids] : [],
    form_id: props.post?.form_id ?? '',
});

const slugify = (value: string): string => {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
};

const submit = () => {
    if (!form.slug && form.title) {
        form.slug = slugify(form.title);
    }

    if (isEditing.value && props.post?.id) {
        form.put(update.url({ post: props.post.id }), {
            preserveScroll: true,
        });

        return;
    }

    form.post(store.url(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <Head
            :title="
                isEditing
                    ? t('posts.form.edit_title')
                    : t('posts.form.create_title')
            "
        />

        <main class="mx-auto max-w-5xl px-5 py-8 md:px-8 md:py-10">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-primary uppercase"
                    >
                        {{ t('posts.form.studio') }}
                    </p>
                    <h1
                        class="[font-family:Manrope,ui-sans-serif] text-3xl font-black"
                    >
                        {{
                            isEditing
                                ? t('posts.form.edit_title')
                                : t('posts.form.create_title')
                        }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('posts.form.subtitle') }}
                    </p>
                </div>

                <Link
                    :href="index()"
                    class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2.5 text-sm font-bold text-card-foreground shadow-sm"
                >
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('nav.back') }}
                </Link>
            </div>

            <form
                @submit.prevent="submit"
                class="space-y-6 rounded-2xl border border-border bg-card p-6 text-card-foreground shadow-sm md:p-8"
            >
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-foreground">{{
                            t('posts.form.title')
                        }}</label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm text-foreground"
                        />
                        <p
                            v-if="form.errors.title"
                            class="text-xs text-red-600"
                        >
                            {{ form.errors.title }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-foreground">{{
                            t('posts.form.slug')
                        }}</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm text-foreground"
                        />
                        <p v-if="form.errors.slug" class="text-xs text-red-600">
                            {{ form.errors.slug }}
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-bold text-foreground">{{
                            t('posts.form.content')
                        }}</label>
                        <span class="text-xs text-muted-foreground">{{
                            t('posts.form.content_hint')
                        }}</span>
                    </div>
                    <MarkdownWysiwyg v-model="form.content" />
                    <p v-if="form.errors.content" class="text-xs text-red-600">
                        {{ form.errors.content }}
                    </p>
                </div>

                <CategorySelector
                    v-model="form.category_ids"
                    :categories="categories"
                    label="Categorias da postagem"
                    hint="Selecione as categorias desta igreja"
                />

                <div class="space-y-2">
                    <label class="text-sm font-bold text-foreground">
                        {{ t('admin.forms.title') }}
                    </label>
                    <select
                        v-model="form.form_id"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm text-foreground"
                    >
                        <option value="">{{ t('admin.forms.none') }}</option>
                        <option
                            v-for="registrationForm in props.forms"
                            :key="registrationForm.id"
                            :value="registrationForm.id"
                        >
                            {{ registrationForm.title }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        {{ t('admin.forms.content_hint') }}
                    </p>
                    <p v-if="form.errors.form_id" class="text-xs text-red-600">
                        {{ form.errors.form_id }}
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-foreground">{{
                            t('posts.form.published_at')
                        }}</label>
                        <input
                            v-model="form.published_at"
                            type="datetime-local"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm text-foreground"
                        />
                        <p
                            v-if="form.errors.published_at"
                            class="text-xs text-red-600"
                        >
                            {{ form.errors.published_at }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-foreground">{{
                            t('posts.form.expires_at')
                        }}</label>
                        <input
                            v-model="form.expires_at"
                            type="datetime-local"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm text-foreground"
                        />
                        <p
                            v-if="form.errors.expires_at"
                            class="text-xs text-red-600"
                        >
                            {{ form.errors.expires_at }}
                        </p>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                    >
                        <Save class="h-4 w-4" />
                        {{
                            isEditing
                                ? t('posts.form.update')
                                : t('posts.form.save')
                        }}
                    </button>
                </div>
            </form>
        </main>
    </div>
</template>
