<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import ChatPanel from '@/components/ChatPanel.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import type { ChatConversation } from '@/composables/useChat';
import { useChat } from '@/composables/useChat';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();
const chat = useChat();

const conversations = ref<ChatConversation[]>([]);
const selected = ref<ChatConversation | null>(null);
const loading = ref(true);

onMounted(async () => {
    try {
        conversations.value = await chat.listConversations();
        selected.value = conversations.value[0] ?? null;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <Head :title="t('chat.my_conversations')" />
        <PublicHeader />
        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <h1 class="mb-6 text-2xl font-black">
                {{ t('chat.my_conversations') }}
            </h1>

            <p
                v-if="!loading && !conversations.length"
                class="rounded-2xl border border-border bg-card p-12 text-center text-sm text-muted-foreground"
            >
                {{ t('chat.no_conversations') }}
            </p>

            <div v-else class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <aside
                    class="max-h-[72vh] space-y-1 overflow-y-auto rounded-2xl border border-border bg-card p-2"
                >
                    <button
                        v-for="conversation in conversations"
                        :key="conversation.id"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition"
                        :class="
                            selected?.id === conversation.id
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
                        "
                        @click="selected = conversation"
                    >
                        <MessageCircle class="size-4 shrink-0" />
                        <span class="truncate">{{ conversation.title }}</span>
                    </button>
                </aside>

                <div class="h-[72vh]">
                    <ChatPanel
                        v-if="selected"
                        :key="selected.id"
                        :conversation-id="selected.id"
                    />
                    <p
                        v-else
                        class="grid h-full place-items-center rounded-2xl border border-border bg-card text-sm text-muted-foreground"
                    >
                        {{ t('chat.select_conversation') }}
                    </p>
                </div>
            </div>
        </main>
        <PublicFooter />
    </div>
</template>
