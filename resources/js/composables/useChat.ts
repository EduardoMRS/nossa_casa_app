import { usePage } from '@inertiajs/vue3';

export type ChatUser = {
    id: string;
    first_name: string;
    last_name: string;
};

export type ChatLinkPreview = {
    url: string;
    host: string | null;
    title: string | null;
    description: string | null;
    image: string | null;
    site_name: string | null;
};

export type ChatMessage = {
    id: string;
    conversation_id: string;
    type: 'text' | 'image' | 'video' | 'audio' | 'link';
    body: string | null;
    attachment_url: string | null;
    attachment_mime: string | null;
    link_preview: ChatLinkPreview | null;
    created_at: string | null;
    user: ChatUser | null;
    is_mine?: boolean;
    deleted_at?: string | null;
};

export type ChatConversation = {
    id: string;
    type: 'direct' | 'context';
    title: string;
    context: { type: string; id: string; chat_enabled: boolean } | null;
    last_message_at: string | null;
    can_moderate: boolean;
    can_post: boolean;
};

type Paginated<T> = { data: T[]; next_page_url: string | null };

type ConversationPayload = {
    conversation: ChatConversation;
    messages: Paginated<ChatMessage>;
    can_moderate: boolean;
    can_post: boolean;
};

const csrfToken = (): string =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const churchId = (): string | null => {
    const context = (usePage().props as { churchContext?: { church?: { id?: string } | null } })
        .churchContext;

    return context?.church?.id ?? null;
};

const headers = (json = true): Record<string, string> => {
    const base: Record<string, string> = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };

    const church = churchId();

    if (church) {
        base['X-Church-ID'] = church;
    }

    if (json) {
        base['Content-Type'] = 'application/json';
    }

    return base;
};

async function parse<T>(response: Response): Promise<T> {
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return (response.status === 204 ? undefined : await response.json()) as T;
}

export function useChat() {
    const listConversations = async (): Promise<ChatConversation[]> => {
        const data = await parse<{ conversations: ChatConversation[] }>(
            await fetch('/api/chat/conversations', {
                headers: headers(),
                credentials: 'same-origin',
            }),
        );

        return data.conversations;
    };

    const openContext = async (
        contextType: string,
        contextId: string,
    ): Promise<ConversationPayload> =>
        parse<ConversationPayload>(
            await fetch(`/api/chat/contexts/${contextType}/${contextId}`, {
                headers: headers(),
                credentials: 'same-origin',
            }),
        );

    const loadMessages = async (
        conversationId: string,
        page = 1,
    ): Promise<ConversationPayload> =>
        parse<ConversationPayload>(
            await fetch(
                `/api/chat/conversations/${conversationId}/messages?page=${page}`,
                { headers: headers(), credentials: 'same-origin' },
            ),
        );

    const sendText = async (
        conversationId: string,
        body: string,
    ): Promise<{ message: ChatMessage }> =>
        parse<{ message: ChatMessage }>(
            await fetch(`/api/chat/conversations/${conversationId}/messages`, {
                method: 'POST',
                headers: headers(),
                credentials: 'same-origin',
                body: JSON.stringify({ body }),
            }),
        );

    const sendAttachment = async (
        conversationId: string,
        file: File,
        body?: string,
    ): Promise<{ message: ChatMessage }> => {
        const form = new FormData();
        form.append('attachment', file);

        if (body) {
            form.append('body', body);
        }

        return parse<{ message: ChatMessage }>(
            await fetch(`/api/chat/conversations/${conversationId}/messages`, {
                method: 'POST',
                headers: headers(false),
                credentials: 'same-origin',
                body: form,
            }),
        );
    };

    const deleteMessage = async (
        conversationId: string,
        messageId: string,
    ): Promise<void> => {
        await parse<void>(
            await fetch(
                `/api/chat/conversations/${conversationId}/messages/${messageId}`,
                {
                    method: 'DELETE',
                    headers: headers(),
                    credentials: 'same-origin',
                },
            ),
        );
    };

    const toggleContext = async (
        contextType: string,
        contextId: string,
        enabled: boolean,
    ): Promise<{ chat_enabled: boolean }> =>
        parse<{ chat_enabled: boolean }>(
            await fetch(
                `/api/chat/contexts/${contextType}/${contextId}/toggle`,
                {
                    method: 'PUT',
                    headers: headers(),
                    credentials: 'same-origin',
                    body: JSON.stringify({ enabled }),
                },
            ),
        );

    return {
        listConversations,
        openContext,
        loadMessages,
        sendText,
        sendAttachment,
        deleteMessage,
        toggleContext,
    };
}
