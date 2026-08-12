<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    FileText,
    Heart,
    MessageCircle,
    Play,
    Send,
    Upload,
    X,
} from '@lucide/vue';
import axios from 'axios';
import { computed, onBeforeUnmount, ref } from 'vue';
import CategorySelector from '@/components/CategorySelector.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useI18n } from '@/lib/i18n';

interface Person {
    id: string;
    first_name: string;
    last_name: string;
}

interface ReactionItem {
    id: string;
    user_id: string;
    content: string;
    type?: string;
    user_details?: Person | null;
}

interface GalleryComment {
    id: string;
    content: string;
    created_at: string;
    user_details?: Person | null;
    reactions: ReactionItem[];
}

interface MediaItem {
    id: string;
    url: string;
    title: string;
    description: string | null;
    mimetype: string;
    size: number;
    created_at: string;
    categories: string[];
    comments: GalleryComment[];
    reactions: ReactionItem[];
    uploader?: Person | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

type CategoryOption = {
    id: string;
    name: string;
    slug: string;
    type: string;
};

const props = defineProps<{
    media: {
        data: MediaItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    categories: CategoryOption[];
    canInteract: boolean;
}>();
const { locale, t } = useI18n();
const page = usePage();
const mediaItems = ref<MediaItem[]>(
    props.media.data.map((item) => ({ ...item })),
);
const selectedMedia = ref<MediaItem | null>(null);
const galleryComment = ref('');
const interactionProcessing = ref(false);
const interactionError = ref('');
const emojis = ['👍', '❤️', '🙏', '🎉'];

const formatDate = (value: string): string =>
    new Intl.DateTimeFormat(locale.value === 'pt' ? 'pt-BR' : 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
const isVideo = (item: MediaItem): boolean =>
    item.mimetype.startsWith('video/');
const isPdf = (item: MediaItem): boolean => item.mimetype === 'application/pdf';

const currentUser = computed<Person | undefined>(() => {
    const user = page.props.auth?.user;

    if (!user) {
        return undefined;
    }

    const nameParts = user.name.trim().split(/\s+/);

    return {
        id: String(user.id),
        first_name: nameParts.shift() ?? user.name,
        last_name: nameParts.join(' '),
    };
});
const canUploadMedia = computed(() => {
    const role = String(page.props.auth?.user?.role ?? '');

    return (
        props.canInteract &&
        ['leader', 'media', 'admin', 'superadmin', 'system'].includes(role)
    );
});
const groupedMediaReactions = computed(() => {
    if (!selectedMedia.value) {
        return [];
    }

    return emojis
        .map((emoji) => ({
            emoji,
            count: selectedMedia.value?.reactions.filter(
                (reaction) => reaction.content === emoji,
            ).length,
        }))
        .filter((reaction) => reaction.count);
});

const openMedia = (item: MediaItem): void => {
    selectedMedia.value = {
        ...item,
        reactions: [...item.reactions],
        comments: item.comments.map((comment) => ({
            ...comment,
            reactions: [...comment.reactions],
        })),
    };
    interactionError.value = '';
};

const closeMedia = (): void => {
    if (selectedMedia.value) {
        const index = mediaItems.value.findIndex(
            (item) => item.id === selectedMedia.value?.id,
        );

        if (index !== -1) {
            mediaItems.value[index] = selectedMedia.value;
        }
    }

    selectedMedia.value = null;
    galleryComment.value = '';
    interactionError.value = '';
};

const reactToMedia = async (content: string): Promise<void> => {
    if (!selectedMedia.value || !props.canInteract) {
        return;
    }

    interactionProcessing.value = true;
    interactionError.value = '';

    try {
        const response = await axios.post<ReactionItem>('/api/reactions', {
            reactionable_type: 'media',
            reactionable_id: selectedMedia.value.id,
            content,
            type: 'emoji',
        });
        selectedMedia.value.reactions = [
            ...selectedMedia.value.reactions.filter(
                (reaction) => reaction.user_id !== response.data.user_id,
            ),
            response.data,
        ];
    } catch {
        interactionError.value = t('gallery.interaction_error');
    } finally {
        interactionProcessing.value = false;
    }
};

const submitComment = async (): Promise<void> => {
    if (!selectedMedia.value || !galleryComment.value.trim()) {
        return;
    }

    interactionProcessing.value = true;
    interactionError.value = '';

    try {
        const response = await axios.post<GalleryComment>('/api/comments', {
            commentable_type: 'media',
            commentable_id: selectedMedia.value.id,
            content: galleryComment.value,
        });
        selectedMedia.value.comments.unshift({
            ...response.data,
            user_details: response.data.user_details ?? currentUser.value,
            reactions: [],
        });
        galleryComment.value = '';
    } catch {
        interactionError.value = t('gallery.interaction_error');
    } finally {
        interactionProcessing.value = false;
    }
};

const reactToComment = async (comment: GalleryComment): Promise<void> => {
    if (!props.canInteract) {
        return;
    }

    interactionProcessing.value = true;
    interactionError.value = '';

    try {
        const response = await axios.post<ReactionItem>('/api/reactions', {
            reactionable_type: 'comment',
            reactionable_id: comment.id,
            content: '❤️',
            type: 'emoji',
        });
        comment.reactions = [
            ...comment.reactions.filter(
                (reaction) => reaction.user_id !== response.data.user_id,
            ),
            response.data,
        ];
    } catch {
        interactionError.value = t('gallery.interaction_error');
    } finally {
        interactionProcessing.value = false;
    }
};

const uploadType = ref<'url' | 'file'>('url');
const uploadTitle = ref('');
const uploadDescription = ref('');
const uploadUrl = ref('');
const selectedUploadFile = ref<File | null>(null);
const uploadPreviewUrl = ref<string>('');
const selectedCategoryIds = ref<string[]>([]);
const uploadProcessing = ref(false);
const uploadError = ref('');
const uploadOpen = ref(false);

const clearUploadPreview = () => {
    if (uploadPreviewUrl.value) {
        URL.revokeObjectURL(uploadPreviewUrl.value);
        uploadPreviewUrl.value = '';
    }
};

const uploadPreview = computed(() =>
    uploadType.value === 'file'
        ? uploadPreviewUrl.value
        : uploadUrl.value.trim(),
);

const handleUploadTypeChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    uploadType.value = target.value === 'file' ? 'file' : 'url';

    if (uploadType.value === 'url') {
        selectedUploadFile.value = null;
        clearUploadPreview();
    }
};

const handleUploadFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    selectedUploadFile.value = file;
    clearUploadPreview();

    if (file) {
        uploadPreviewUrl.value = URL.createObjectURL(file);
    }
};

const submitUpload = () => {
    uploadProcessing.value = true;
    uploadError.value = '';

    router.post(
        '/api/media',
        {
            title: uploadTitle.value,
            description: uploadDescription.value,
            file_path:
                uploadType.value === 'file'
                    ? selectedUploadFile.value
                    : uploadUrl.value,
            file: uploadType.value === 'file' ? selectedUploadFile.value : null,
            gallery: true,
            category_ids: selectedCategoryIds.value,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadTitle.value = '';
                uploadDescription.value = '';
                uploadUrl.value = '';
                selectedUploadFile.value = null;
                uploadType.value = 'url';
                selectedCategoryIds.value = [];
                clearUploadPreview();
                uploadOpen.value = false;
            },
            onError: (errors) => {
                uploadError.value = String(
                    errors.file_path ??
                        errors.file ??
                        errors.title ??
                        t('gallery.upload_error'),
                );
            },
            onFinish: () => {
                uploadProcessing.value = false;
            },
        },
    );
};

onBeforeUnmount(() => {
    clearUploadPreview();
});
</script>

<template>
    <Head :title="t('gallery.meta_title')" />

    <div
        class="flex min-h-screen flex-col bg-[#f8fafc] text-slate-950"
        :style="{
            backgroundColor: 'var(--church-surface, #f8fafc)',
            fontFamily: 'var(--church-font, Manrope, ui-sans-serif)',
        }"
    >
        <PublicHeader active="gallery" />

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
            <section
                class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1 class="text-2xl font-black tracking-tight md:text-3xl">
                        {{ t('gallery.title') }}
                    </h1>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                        {{ t('gallery.description') }}
                    </p>
                </div>
                <button
                    v-if="canUploadMedia"
                    class="inline-flex shrink-0 items-center justify-center gap-2 self-start rounded-lg px-4 py-2.5 text-xs font-bold text-white shadow-sm sm:self-center"
                    :style="{ backgroundColor: 'var(--church-primary)' }"
                    @click="uploadOpen = true"
                >
                    <Upload class="size-4" /> {{ t('gallery.upload') }}
                </button>
            </section>

            <section
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
            >
                <button
                    v-for="item in mediaItems"
                    :key="item.id"
                    type="button"
                    class="group overflow-hidden rounded-xl border border-slate-200 bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    @click="openMedia(item)"
                >
                    <span
                        class="relative block aspect-square overflow-hidden bg-slate-100"
                    >
                        <video
                            v-if="isVideo(item)"
                            :src="item.url"
                            class="h-full w-full object-cover"
                            preload="metadata"
                        />
                        <span
                            v-else-if="isPdf(item)"
                            class="grid h-full place-items-center text-slate-400"
                        >
                            <FileText class="size-12" />
                        </span>
                        <img
                            v-else
                            :src="item.url"
                            :alt="item.title"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                            loading="lazy"
                        />
                        <span
                            v-if="isVideo(item)"
                            class="absolute inset-0 grid place-items-center bg-slate-950/15"
                        >
                            <span
                                class="grid size-11 place-items-center rounded-full bg-white/90 text-slate-950 shadow"
                            >
                                <Play class="size-5 fill-current" />
                            </span>
                        </span>
                        <span
                            class="absolute right-2 bottom-2 flex gap-2 rounded-full bg-slate-950/70 px-2.5 py-1 text-[10px] font-bold text-white backdrop-blur"
                        >
                            <span class="inline-flex items-center gap-1"
                                ><Heart class="size-3" />{{
                                    item.reactions.length
                                }}</span
                            >
                            <span class="inline-flex items-center gap-1"
                                ><MessageCircle class="size-3" />{{
                                    item.comments.length
                                }}</span
                            >
                        </span>
                    </span>
                    <span class="block space-y-1 p-3">
                        <strong class="block truncate text-sm text-slate-900">{{
                            item.title
                        }}</strong>
                        <span class="block truncate text-xs text-slate-500">
                            {{
                                item.uploader
                                    ? `${item.uploader.first_name} ${item.uploader.last_name}`
                                    : t('gallery.anonymous')
                            }}
                        </span>
                    </span>
                </button>
            </section>

            <section
                v-if="props.media.links.length > 3"
                class="mt-8 flex flex-wrap items-center justify-between gap-3"
            >
                <p class="text-sm text-slate-500">
                    {{
                        t('gallery.pagination', {
                            from: props.media.from ?? 0,
                            to: props.media.to ?? 0,
                            total: props.media.total,
                        })
                    }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <template
                        v-for="(link, index) in props.media.links"
                        :key="index"
                    >
                        <span
                            v-if="!link.url"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-400"
                            v-html="link.label"
                        />
                        <Link
                            v-else
                            :href="link.url"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm"
                            :class="
                                link.active
                                    ? 'text-white'
                                    : 'bg-white text-slate-700'
                            "
                            :style="
                                link.active
                                    ? {
                                          backgroundColor:
                                              'var(--church-primary)',
                                      }
                                    : undefined
                            "
                        >
                            <span v-html="link.label" />
                        </Link>
                    </template>
                </div>
            </section>
        </main>

        <Teleport to="body">
            <div
                v-if="selectedMedia"
                class="fixed inset-0 z-[80] grid place-items-center overflow-y-auto bg-slate-950/80 p-3 backdrop-blur-md sm:p-6"
                @click.self="closeMedia"
            >
                <section
                    class="relative my-auto grid w-full max-w-6xl overflow-hidden rounded-2xl bg-white shadow-2xl lg:max-h-[86vh] lg:grid-cols-[minmax(0,1.45fr)_minmax(22rem,0.7fr)]"
                >
                    <button
                        type="button"
                        class="absolute top-3 right-3 z-10 grid size-9 place-items-center rounded-full bg-slate-950/70 text-white backdrop-blur hover:bg-slate-950"
                        :aria-label="t('gallery.close')"
                        @click="closeMedia"
                    >
                        <X class="size-5" />
                    </button>

                    <div
                        class="grid min-h-72 place-items-center bg-slate-950 lg:min-h-[38rem]"
                    >
                        <video
                            v-if="isVideo(selectedMedia)"
                            :src="selectedMedia.url"
                            controls
                            autoplay
                            class="max-h-[86vh] w-full object-contain"
                        />
                        <iframe
                            v-else-if="isPdf(selectedMedia)"
                            :src="selectedMedia.url"
                            :title="selectedMedia.title"
                            class="h-[70vh] w-full bg-white"
                        />
                        <img
                            v-else
                            :src="selectedMedia.url"
                            :alt="selectedMedia.title"
                            class="max-h-[86vh] w-full object-contain"
                        />
                    </div>

                    <div class="flex min-h-0 flex-col bg-white">
                        <header class="border-b border-slate-200 p-5 pr-14">
                            <h2 class="text-lg font-black text-slate-950">
                                {{ selectedMedia.title }}
                            </h2>
                            <p class="mt-1 text-xs text-slate-500">
                                {{
                                    selectedMedia.uploader
                                        ? `${selectedMedia.uploader.first_name} ${selectedMedia.uploader.last_name}`
                                        : t('gallery.anonymous')
                                }}
                                · {{ formatDate(selectedMedia.created_at) }}
                            </p>
                            <div
                                v-if="selectedMedia.categories.length"
                                class="mt-3 flex flex-wrap gap-1.5"
                            >
                                <span
                                    v-for="category in selectedMedia.categories"
                                    :key="category"
                                    class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase"
                                >
                                    {{ category }}
                                </span>
                            </div>
                            <p
                                v-if="selectedMedia.description"
                                class="mt-3 text-sm leading-6 whitespace-pre-wrap text-slate-600"
                            >
                                {{ selectedMedia.description }}
                            </p>
                        </header>

                        <div
                            class="min-h-48 flex-1 space-y-4 overflow-y-auto p-5 lg:min-h-0"
                        >
                            <article
                                v-for="comment in selectedMedia.comments"
                                :key="comment.id"
                                class="flex gap-3"
                            >
                                <span
                                    class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-[10px] font-black text-slate-600"
                                >
                                    {{
                                        comment.user_details?.first_name?.slice(
                                            0,
                                            1,
                                        )
                                    }}{{
                                        comment.user_details?.last_name?.slice(
                                            0,
                                            1,
                                        )
                                    }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm leading-5 text-slate-700">
                                        <strong class="mr-1 text-slate-950"
                                            >{{
                                                comment.user_details?.first_name
                                            }}
                                            {{
                                                comment.user_details?.last_name
                                            }}</strong
                                        >
                                        {{ comment.content }}
                                    </p>
                                    <div
                                        class="mt-1 flex items-center gap-3 text-[10px] text-slate-400"
                                    >
                                        <time>{{
                                            formatDate(comment.created_at)
                                        }}</time>
                                        <button
                                            type="button"
                                            :disabled="
                                                !canInteract ||
                                                interactionProcessing
                                            "
                                            class="inline-flex items-center gap-1 font-bold hover:text-rose-600 disabled:opacity-40"
                                            @click="reactToComment(comment)"
                                        >
                                            <Heart class="size-3" />{{
                                                comment.reactions.length
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </article>
                            <div
                                v-if="!selectedMedia.comments.length"
                                class="grid min-h-36 place-items-center rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400"
                            >
                                {{ t('gallery.no_comments') }}
                            </div>
                        </div>

                        <footer class="border-t border-slate-200 p-4">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="flex gap-1">
                                    <button
                                        v-for="emoji in emojis"
                                        :key="emoji"
                                        type="button"
                                        :disabled="
                                            !canInteract ||
                                            interactionProcessing
                                        "
                                        class="rounded-lg px-2 py-1.5 text-lg transition hover:bg-slate-100 disabled:opacity-40"
                                        @click="reactToMedia(emoji)"
                                    >
                                        {{ emoji }}
                                    </button>
                                </div>
                                <div class="flex gap-1 text-xs text-slate-500">
                                    <span
                                        v-for="reaction in groupedMediaReactions"
                                        :key="reaction.emoji"
                                        class="rounded-full bg-slate-100 px-2 py-1"
                                    >
                                        {{ reaction.emoji }}
                                        {{ reaction.count }}
                                    </span>
                                </div>
                            </div>

                            <form
                                v-if="canInteract"
                                class="mt-3 flex gap-2"
                                @submit.prevent="submitComment"
                            >
                                <input
                                    v-model="galleryComment"
                                    type="text"
                                    class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                                    :placeholder="
                                        t('gallery.comment_placeholder')
                                    "
                                />
                                <button
                                    :disabled="
                                        interactionProcessing ||
                                        !galleryComment.trim()
                                    "
                                    class="grid size-10 place-items-center rounded-xl text-white disabled:opacity-40"
                                    :style="{
                                        backgroundColor:
                                            'var(--church-primary)',
                                    }"
                                    :aria-label="t('gallery.comment_submit')"
                                >
                                    <Send class="size-4" />
                                </button>
                            </form>
                            <p
                                v-else
                                class="mt-3 text-center text-xs text-slate-500"
                            >
                                {{ t('gallery.login_to_interact') }}
                            </p>
                            <p
                                v-if="interactionError"
                                class="mt-2 text-xs font-semibold text-red-600"
                            >
                                {{ interactionError }}
                            </p>
                        </footer>
                    </div>
                </section>
            </div>

            <div
                v-if="canUploadMedia && uploadOpen"
                class="fixed inset-0 z-[80] grid place-items-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
                @click.self="uploadOpen = false"
            >
                <section
                    class="relative my-6 w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl md:p-6"
                >
                    <button
                        class="absolute top-4 right-4 rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        @click="uploadOpen = false"
                    >
                        <X class="size-4" />
                    </button>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.18em] uppercase"
                                :style="{ color: 'var(--church-primary)' }"
                            >
                                {{ t('gallery.upload_kicker') }}
                            </p>
                            <h2 class="text-xl font-black text-slate-950">
                                {{ t('gallery.upload_title') }}
                            </h2>
                        </div>
                        <select
                            :value="uploadType"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700"
                            @change="handleUploadTypeChange"
                        >
                            <option value="url">URL</option>
                            <option value="file">
                                {{ t('gallery.file') }}
                            </option>
                        </select>
                    </div>

                    <div
                        class="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_240px] md:items-start"
                    >
                        <div class="space-y-3">
                            <input
                                v-model="uploadTitle"
                                type="text"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"
                                :placeholder="t('gallery.title_placeholder')"
                            />
                            <textarea
                                v-model="uploadDescription"
                                rows="3"
                                class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2.5 text-sm"
                                :placeholder="
                                    t('gallery.description_placeholder')
                                "
                            />
                            <input
                                v-if="uploadType === 'file'"
                                type="file"
                                accept="image/*,video/*,application/pdf"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"
                                @change="handleUploadFileChange"
                            />
                            <input
                                v-else
                                v-model="uploadUrl"
                                type="text"
                                :placeholder="t('gallery.file_placeholder')"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"
                            />
                            <p class="text-xs text-slate-500">
                                {{ t('gallery.upload_hint') }}
                            </p>
                            <p
                                v-if="uploadError"
                                class="text-xs font-semibold text-red-600"
                            >
                                {{ uploadError }}
                            </p>
                            <CategorySelector
                                v-model="selectedCategoryIds"
                                :categories="categories"
                                :label="t('gallery.categories')"
                                :hint="t('gallery.categories_hint')"
                            />
                        </div>

                        <div
                            class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-3"
                        >
                            <p
                                class="mb-2 text-xs font-bold tracking-[0.16em] uppercase"
                                :style="{ color: 'var(--church-primary)' }"
                            >
                                {{ t('gallery.preview') }}
                            </p>
                            <img
                                v-if="uploadPreview"
                                :src="uploadPreview"
                                :alt="t('gallery.preview_alt')"
                                class="h-44 w-full rounded-xl object-cover"
                            />
                            <div
                                v-else
                                class="flex h-44 items-center justify-center rounded-xl bg-white text-sm text-slate-500"
                            >
                                {{ t('gallery.no_selection') }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            :disabled="uploadProcessing"
                            class="rounded-lg px-5 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                            :style="{
                                backgroundColor: 'var(--church-primary)',
                            }"
                            @click="submitUpload"
                        >
                            {{
                                uploadProcessing
                                    ? t('gallery.uploading')
                                    : t('gallery.upload')
                            }}
                        </button>
                    </div>
                </section>
            </div>
        </Teleport>

        <PublicFooter />
    </div>
</template>
