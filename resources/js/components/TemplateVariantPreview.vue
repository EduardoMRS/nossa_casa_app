<script setup lang="ts">
import { computed } from 'vue';

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

const props = defineProps<{
    section: TemplateSection;
    variant: TemplateVariant;
    primaryColor: string;
    secondaryColor: string;
    surfaceColor: string;
}>();

const isHome = computed(() => props.section === 'home');
const isForm = computed(() => props.section === 'form');
const isShow = computed(() =>
    ['posts_show', 'events_show'].includes(props.section),
);

const previewColors = computed(() => ({
    '--preview-primary': props.primaryColor,
    '--preview-secondary': props.secondaryColor,
    '--preview-surface': props.surfaceColor,
}));
</script>

<template>
    <div
        aria-hidden="true"
        class="template-preview"
        :class="`template-preview--${variant}`"
        :style="previewColors"
    >
        <div class="template-preview__header">
            <span class="template-preview__logo" />
            <span class="template-preview__brand" />
            <span class="template-preview__nav" />
            <span class="template-preview__nav" />
            <span class="template-preview__action" />
        </div>

        <div v-if="isHome" class="template-preview__body">
            <div class="template-preview__hero">
                <div class="template-preview__hero-content">
                    <span class="template-preview__eyebrow" />
                    <span class="template-preview__title" />
                    <span class="template-preview__copy" />
                    <span class="template-preview__button" />
                </div>
                <span class="template-preview__hero-media" />
            </div>
            <div class="template-preview__grid">
                <span
                    v-for="item in 3"
                    :key="item"
                    class="template-preview__card"
                />
            </div>
        </div>

        <div
            v-else-if="isForm"
            class="template-preview__body template-preview__form-layout"
        >
            <div class="template-preview__heading">
                <span class="template-preview__eyebrow" />
                <span class="template-preview__title" />
            </div>
            <div class="template-preview__form">
                <span
                    v-for="item in 3"
                    :key="item"
                    class="template-preview__field"
                />
                <span class="template-preview__button" />
            </div>
        </div>

        <div
            v-else-if="isShow"
            class="template-preview__body template-preview__show-layout"
        >
            <div class="template-preview__article">
                <span class="template-preview__eyebrow" />
                <span class="template-preview__title" />
                <span class="template-preview__cover" />
                <span
                    v-for="item in 3"
                    :key="item"
                    class="template-preview__copy"
                />
            </div>
            <span class="template-preview__aside" />
        </div>

        <div v-else class="template-preview__body">
            <div class="template-preview__heading">
                <span class="template-preview__eyebrow" />
                <span class="template-preview__title" />
                <span class="template-preview__copy" />
            </div>
            <div class="template-preview__grid">
                <span
                    v-for="item in 3"
                    :key="item"
                    class="template-preview__card"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.template-preview {
    --preview-primary: #2563eb;
    --preview-secondary: #0f172a;
    --preview-surface: #ffffff;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    border: 1px solid
        color-mix(in srgb, var(--preview-secondary) 14%, transparent);
    background: var(--preview-surface);
    color: var(--preview-secondary);
}

.template-preview__header {
    display: flex;
    height: 18%;
    align-items: center;
    gap: 4%;
    padding: 0 7%;
    border-bottom: 1px solid
        color-mix(in srgb, var(--preview-secondary) 10%, transparent);
}

.template-preview__logo {
    width: 8%;
    aspect-ratio: 1;
    border-radius: 9999px;
    background: var(--preview-primary);
}

.template-preview__brand,
.template-preview__nav,
.template-preview__action,
.template-preview__eyebrow,
.template-preview__title,
.template-preview__copy,
.template-preview__button,
.template-preview__field {
    display: block;
    border-radius: 9999px;
}

.template-preview__brand {
    width: 22%;
    height: 10%;
    background: currentColor;
}

.template-preview__nav {
    margin-left: auto;
    width: 10%;
    height: 7%;
    opacity: 0.38;
    background: currentColor;
}

.template-preview__nav + .template-preview__nav {
    margin-left: 0;
}

.template-preview__action,
.template-preview__button {
    background: var(--preview-primary);
}

.template-preview__action {
    width: 15%;
    height: 27%;
}

.template-preview__body {
    display: grid;
    height: 82%;
    gap: 9%;
    padding: 7%;
}

.template-preview__hero {
    display: grid;
    grid-template-columns: 3fr 2fr;
    gap: 8%;
    padding: 7%;
    border-radius: 0.35rem;
    background: color-mix(
        in srgb,
        var(--preview-primary) 11%,
        var(--preview-surface)
    );
}

.template-preview__hero-content,
.template-preview__heading,
.template-preview__article,
.template-preview__form {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8%;
}

.template-preview__hero-media,
.template-preview__cover,
.template-preview__aside {
    display: block;
    border-radius: 0.25rem;
    background: color-mix(
        in srgb,
        var(--preview-primary) 24%,
        var(--preview-surface)
    );
}

.template-preview__eyebrow {
    width: 27%;
    height: 6%;
    background: var(--preview-primary);
}

.template-preview__title {
    width: 76%;
    height: 10%;
    background: currentColor;
}

.template-preview__copy {
    width: 90%;
    height: 5%;
    opacity: 0.26;
    background: currentColor;
}

.template-preview__button {
    width: 34%;
    height: 12%;
}

.template-preview__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 5%;
}

.template-preview__card {
    min-height: 1.5rem;
    border: 1px solid
        color-mix(in srgb, var(--preview-secondary) 9%, transparent);
    border-radius: 0.25rem;
    background: var(--preview-surface);
    box-shadow: 0 0.2rem 0.45rem
        color-mix(in srgb, var(--preview-secondary) 10%, transparent);
}

.template-preview__heading {
    gap: 13%;
}

.template-preview__form-layout {
    grid-template-columns: 2fr 3fr;
}

.template-preview__form {
    justify-content: center;
    gap: 8%;
    padding: 8%;
    border: 1px solid
        color-mix(in srgb, var(--preview-secondary) 10%, transparent);
    border-radius: 0.35rem;
}

.template-preview__field {
    width: 100%;
    height: 12%;
    border: 1px solid
        color-mix(in srgb, var(--preview-secondary) 18%, transparent);
}

.template-preview__form .template-preview__button {
    height: 13%;
}

.template-preview__show-layout {
    grid-template-columns: 3fr 1fr;
}

.template-preview__article {
    gap: 7%;
}

.template-preview__cover {
    width: 100%;
    height: 42%;
}

.template-preview__aside {
    height: 55%;
}

.template-preview--classic {
    border-radius: 0.5rem;
    background: linear-gradient(
        150deg,
        var(--preview-surface),
        color-mix(in srgb, var(--preview-primary) 7%, var(--preview-surface))
    );
}

.template-preview--editorial {
    border-radius: 0;
    background: linear-gradient(
        125deg,
        color-mix(in srgb, var(--preview-primary) 5%, var(--preview-surface)) 0
            62%,
        color-mix(in srgb, var(--preview-secondary) 7%, var(--preview-surface))
            62%
    );
}

.template-preview--editorial .template-preview__header {
    height: 15%;
    border-bottom-color: color-mix(
        in srgb,
        var(--preview-secondary) 30%,
        transparent
    );
}

.template-preview--editorial .template-preview__body {
    height: 85%;
    padding-inline: 5%;
}

.template-preview--editorial .template-preview__hero {
    grid-template-columns: 5fr 3fr;
    border-radius: 0;
    background: transparent;
}

.template-preview--editorial .template-preview__card,
.template-preview--editorial .template-preview__form,
.template-preview--editorial .template-preview__hero-media,
.template-preview--editorial .template-preview__cover,
.template-preview--editorial .template-preview__aside {
    border-radius: 0;
}

.template-preview--minimal {
    border-radius: 0.3rem;
    background: var(--preview-surface);
}

.template-preview--minimal .template-preview__header {
    height: 14%;
    border-bottom-color: transparent;
}

.template-preview--minimal .template-preview__body {
    height: 86%;
    gap: 14%;
    padding: 10%;
}

.template-preview--minimal .template-preview__hero {
    padding: 0;
    background: transparent;
}

.template-preview--minimal .template-preview__card,
.template-preview--minimal .template-preview__form {
    border-color: color-mix(in srgb, var(--preview-secondary) 12%, transparent);
    border-radius: 0.15rem;
    box-shadow: none;
}
</style>
