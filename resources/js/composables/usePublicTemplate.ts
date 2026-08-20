import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

export type PublicTemplateSection =
    | 'home'
    | 'posts_index'
    | 'posts_show'
    | 'events_index'
    | 'events_show'
    | 'form'
    | 'library'
    | 'gallery';
export type PublicTemplateVariant = 'classic' | 'editorial' | 'minimal';

export const usePublicTemplate = (
    section: PublicTemplateSection,
): ComputedRef<PublicTemplateVariant> => {
    const page = usePage();

    return computed(() => {
        const templates = page.props.publicTemplates as
            | Partial<Record<PublicTemplateSection, PublicTemplateVariant>>
            | undefined;

        return templates?.[section] ?? 'classic';
    });
};
