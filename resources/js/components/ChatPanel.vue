<script setup lang="ts">
import { echo } from '@laravel/echo-vue';
import { Paperclip, Send, Trash2, X } from '@lucide/vue';
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import type { ChatConversation, ChatMessage } from '@/composables/useChat';
import { useChat } from '@/composables/useChat';
import { useI18n } from '@/lib/i18n';

const props = defineProps<{
    contextType: 'event' | 'group' | 'classroom';
    contextId: string;
}>();

const { t } = useI18n();
const chat = useChat();

const conversation = ref<ChatConversation | null>(null);
const messages = ref<ChatMessage[]>([]);
const nextPage = ref<number | null>(null);
const canModerate = ref(false);
const canPost = ref(false);
const chatEnabled = ref(true);
const loading = ref(true);
const sending = ref(false);
const errorMessage = ref('');
const draft = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const thread = ref<HTMLElement | null>(null);

type MessagePayload = { deleted: boolean; message: ChatMessage };

const scrollToBottom = (): void => {
    void nextTick(() => {
        if (thread.value) {
            thread.value.scrollTop = thread.value.scrollHeight;
        }
    });
};

const mergeIncoming = (incoming: ChatMessage, deleted: boolean): void => {
    const index = messages.value.findIndex((item) => item.id === incoming.id);

    if (deleted) {
        if (index !== -1) {
            messages.value.splice(index, 1);
        }

        return;
    }

    if (index === -1) {
        messages.value.push(incoming);
        scrollToBottom();
    }
};

const load = async (): Promise<void> => {
    loading.value = true;
    errorMessage.value = '';

    try {
        const payload = await chat.openContext(props.contextType, props.contextId);
        conversation.value = payload.conversation;
        canModerate.value = payload.can_moderate;
        canPost.value = payload.can_post;
        chatEnabled.value = payload.conversation.context?.chat_enabled ?? false;
        messages.value = [...payload.messages.data].reverse();
        nextPage.value = payload.messages.next_page_url ? 2 : null;
        scrollToBottom();
    } catch {
        errorMessage.value = t('chat.error');
    } finally {
        loading.value = false;
    }
};

const loadMore = async (): Promise<void> => {
    if (!conversation.value || nextPage.value === null) {
        return;
    }

    const payload = await chat.loadMessages(conversation.value.id, nextPage.value);
    messages.value = [...[...payload.messages.data].reverse(), ...messages.value];
    nextPage.value = payload.messages.next_page_url ? nextPage.value + 1 : null;
};

const sendText = async (): Promise<void> => {
    const body = draft.value.trim();

    if (!body || sending.value || !conversation.value) {
        return;
    }

    sending.value = true;
    errorMessage.value = '';

    try {
        const { message } = await chat.sendText(conversation.value.id, body);
        draft.value = '';
        mergeIncoming(message, false);
    } catch {
        errorMessage.value = t('chat.error');
    } finally {
        sending.value = false;
    }
};

const pickFile = (): void => fileInput.value?.click();

const onFile = async (event: Event): Promise<void> => {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file || !conversation.value) {
        return;
    }

    if (file.size > 50 * 1024 * 1024) {
        errorMessage.value = t('chat.attachment_too_large');

        return;
    }

    sending.value = true;
    errorMessage.value = '';

    try {
        const { message } = await chat.sendAttachment(conversation.value.id, file);
        mergeIncoming(message, false);
    } catch {
        errorMessage.value = t('chat.error');
    } finally {
        sending.value = false;

        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
};

const remove = async (message: ChatMessage): Promise<void> => {
    if (!conversation.value) {
        return;
    }

    await chat.deleteMessage(conversation.value.id, message.id);
    mergeIncoming(message, true);
};

const toggleChat = async (): Promise<void> => {
    const next = !chatEnabled.value;
    const result = await chat.toggleContext(props.contextType, props.contextId, next);
    chatEnabled.value = result.chat_enabled;
    canPost.value = result.chat_enabled && canPost.value;
    await load();
};

const senderName = (message: ChatMessage): string =>
    message.is_mine
        ? t('chat.you')
        : message.user
          ? `${message.user.first_name} ${message.user.last_name}`.trim()
          : '';

let subscribedChannel: string | null = null;

const subscribeRealtime = (): void => {
    if (!conversation.value) {
        return;
    }

    const channelName = `chat.conversation.${conversation.value.id}`;

    if (subscribedChannel === channelName) {
        return;
    }

    subscribedChannel = channelName;
    echo()
        .private(channelName)
        .listen('.chat.message', (payload: MessagePayload) =>
            mergeIncoming(payload.message, payload.deleted),
        );
};

const leaveRealtime = (): void => {
    if (subscribedChannel) {
        echo().leave(subscribedChannel);
        subscribedChannel = null;
    }
};

onMounted(async () => {
    await load();
    subscribeRealtime();
});

onUnmounted(leaveRealtime);
</script>

<template>
    <section
        class="flex h-full min-h-0 flex-col rounded-2xl border border-border bg-card text-card-foreground"
    >
        <header
            class="flex items-center justify-between gap-2 border-b border-border p-4"
        >
            <h2 class="text-sm font-black">
                {{ conversation?.title ?? t('chat.title') }}
            </h2>
            <button
                v-if="canModerate"
                type="button"
                class="rounded-lg border border-border px-3 py-1.5 text-xs font-bold"
                :class="
                    chatEnabled
                        ? 'text-rose-600 hover:bg-rose-50'
                        : 'text-emerald-600 hover:bg-emerald-50'
                "
                @click="toggleChat"
            >
                {{ chatEnabled ? t('chat.disable') : t('chat.enable') }}
            </button>
        </header>

        <div
            ref="thread"
            class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4"
        >
            <button
                v-if="nextPage !== null"
                type="button"
                class="mx-auto block rounded-lg px-3 py-1 text-xs font-bold text-muted-foreground hover:bg-muted"
                @click="loadMore"
            >
                {{ t('chat.load_more') }}
            </button>

            <p
                v-if="!loading && !messages.length"
                class="py-10 text-center text-sm text-muted-foreground"
            >
                {{ t('chat.empty') }}
            </p>

            <div
                v-for="message in messages"
                :key="message.id"
                class="flex flex-col"
                :class="message.is_mine ? 'items-end' : 'items-start'"
            >
                <div
                    class="max-w-[85%] rounded-2xl px-3 py-2 text-sm"
                    :class="
                        message.is_mine
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-foreground'
                    "
                >
                    <p class="mb-0.5 text-[10px] font-bold opacity-70">
                        {{ senderName(message) }}
                    </p>

                    <img
                        v-if="message.type === 'image' && message.attachment_url"
                        :src="message.attachment_url"
                        class="max-h-64 rounded-lg"
                        loading="lazy"
                        alt=""
                    />
                    <video
                        v-else-if="message.type === 'video' && message.attachment_url"
                        :src="message.attachment_url"
                        class="max-h-64 rounded-lg"
                        controls
                    />
                    <audio
                        v-else-if="message.type === 'audio' && message.attachment_url"
                        :src="message.attachment_url"
                        class="w-full"
                        controls
                    />

                    <p v-if="message.body" class="break-words whitespace-pre-wrap">
                        {{ message.body }}
                    </p>

                    <a
                        v-if="message.type === 'link' && message.link_preview"
                        :href="message.link_preview.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-2 block overflow-hidden rounded-lg border border-black/10 bg-background/40"
                    >
                        <img
                            v-if="message.link_preview.image"
                            :src="message.link_preview.image"
                            class="h-28 w-full object-cover"
                            loading="lazy"
                            alt=""
                        />
                        <span class="block p-2">
                            <span class="block text-xs font-black">{{
                                message.link_preview.title ||
                                message.link_preview.url
                            }}</span>
                            <span
                                v-if="message.link_preview.description"
                                class="mt-0.5 block text-[11px] opacity-70"
                                >{{ message.link_preview.description }}</span
                            >
                            <span
                                class="mt-1 block text-[10px] font-bold opacity-60"
                                >{{ message.link_preview.host }}</span
                            >
                        </span>
                    </a>
                </div>

                <button
                    v-if="canModerate || message.is_mine"
                    type="button"
                    class="mt-0.5 inline-flex items-center gap-1 text-[10px] text-muted-foreground hover:text-rose-600"
                    @click="remove(message)"
                >
                    <Trash2 class="size-3" />{{ t('chat.remove') }}
                </button>
            </div>
        </div>

        <footer class="border-t border-border p-3">
            <p
                v-if="!chatEnabled"
                class="flex items-center justify-center gap-2 py-2 text-sm text-muted-foreground"
            >
                <X class="size-4" />{{ t('chat.disabled') }}
            </p>
            <template v-else-if="canPost">
                <p v-if="errorMessage" class="mb-2 text-xs text-rose-600">
                    {{ errorMessage }}
                </p>
                <form class="flex items-end gap-2" @submit.prevent="sendText">
                    <button
                        type="button"
                        class="rounded-lg border border-border p-2 text-muted-foreground hover:bg-muted"
                        :title="t('chat.attach')"
                        :disabled="sending"
                        @click="pickFile"
                    >
                        <Paperclip class="size-4" />
                    </button>
                    <input
                        ref="fileInput"
                        type="file"
                        class="hidden"
                        accept="image/*,video/*,audio/*"
                        @change="onFile"
                    />
                    <textarea
                        v-model="draft"
                        rows="1"
                        :placeholder="t('chat.placeholder')"
                        class="min-h-0 flex-1 resize-none rounded-lg border-input bg-background text-sm text-foreground"
                        @keydown.enter.exact.prevent="sendText"
                    ></textarea>
                    <button
                        type="submit"
                        :disabled="sending || !draft.trim()"
                        class="rounded-lg bg-primary p-2 text-primary-foreground disabled:opacity-50"
                        :title="t('chat.send')"
                    >
                        <Send class="size-4" />
                    </button>
                </form>
            </template>
        </footer>
    </section>
</template>
