import type { HttpClient } from '../platform/contracts.ts';

export interface PersonSummary {
    id: string;
    first_name: string;
    last_name: string;
    email?: string;
}

export interface ReactionItem {
    id: string;
    user_id: string;
    content: string;
    type?: string;
    user_details?: PersonSummary | null;
}

export interface CommentItem {
    id: string;
    content: string;
    created_at: string;
    is_pinned?: boolean;
    user?: PersonSummary | null;
    user_details?: PersonSummary | null;
    reactions?: ReactionItem[];
}

export type InteractionTarget = 'post' | 'media' | 'live_stream' | 'comment';

export class InteractionRepository {
    private readonly http: HttpClient;

    public constructor(http: HttpClient) {
        this.http = http;
    }

    public createComment<T extends CommentItem>(input: {
        commentableType: Exclude<InteractionTarget, 'comment'>;
        commentableId: string;
        content: string;
    }): Promise<T> {
        return this.http.request('comments', {
            method: 'POST',
            body: {
                commentable_type: input.commentableType,
                commentable_id: input.commentableId,
                content: input.content,
            },
        });
    }

    public updateCommentPin(
        commentId: string,
        isPinned: boolean,
    ): Promise<void> {
        return this.http.request(`comments/${encodeURIComponent(commentId)}/pin`, {
            method: 'PUT',
            body: { is_pinned: isPinned },
        });
    }

    public deleteComment(commentId: string): Promise<void> {
        return this.http.request(`comments/${encodeURIComponent(commentId)}`, {
            method: 'DELETE',
        });
    }

    public createReaction<T extends ReactionItem>(input: {
        reactionableType: InteractionTarget;
        reactionableId: string;
        content: string;
        type?: string;
    }): Promise<T> {
        return this.http.request('reactions', {
            method: 'POST',
            body: {
                reactionable_type: input.reactionableType,
                reactionable_id: input.reactionableId,
                content: input.content,
                type: input.type ?? 'emoji',
            },
        });
    }

    public deleteReaction(reactionId: string): Promise<void> {
        return this.http.request(`reactions/${encodeURIComponent(reactionId)}`, {
            method: 'DELETE',
        });
    }
}
