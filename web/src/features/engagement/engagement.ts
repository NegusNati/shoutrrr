import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import type {
    AccountFacet,
    EngagementFilters,
    PostFacet,
    ReplyItem,
} from '@/pages/engagement/types';
import type { PlatformName, WorkspaceMention } from '@/types/compose';

export type EngagementIndexData = {
    replies: { data: ReplyItem[] };
    filters: EngagementFilters;
    facets: { accounts: AccountFacet[]; posts: PostFacet[] };
    engagementEnabled: Record<PlatformName, boolean>;
    linkedinCommunityManagementEnabled: boolean;
    savedMentions: WorkspaceMention[];
};

export type EngagementThreadData = {
    post_excerpt: string | null;
    thread: ReplyItem[];
};

/** Matches the app chrome's 60s poll — the inbox rides the same cadence. */
export const INBOX_POLL_MS = 60_000;

const replyParams = (filters: EngagementFilters): string => {
    const params = new URLSearchParams();
    if (filters.account !== '') {
        params.set('account', filters.account);
    }
    if (filters.platform !== '') {
        params.set('platform', filters.platform);
    }
    if (filters.target !== '') {
        params.set('target', filters.target);
    }
    if (filters.post !== '') {
        params.set('post', filters.post);
    }
    if (filters.unread) {
        params.set('unread', '1');
    }
    if (filters.archived) {
        params.set('archived', '1');
    }
    const query = params.toString();

    return query === '' ? '' : `?${query}`;
};

export const engagementQuery = (filters: EngagementFilters) =>
    queryOptions({
        queryKey: ['engagement', filters],
        queryFn: () =>
            apiFetch<EngagementIndexData>(`engagement${replyParams(filters)}`),
        refetchInterval: INBOX_POLL_MS,
    });

export const engagementThreadQuery = (replyId: string) =>
    queryOptions({
        queryKey: ['engagement', 'thread', replyId],
        queryFn: () =>
            apiFetch<EngagementThreadData>(`engagement/${replyId}/thread`),
    });

// --- Conversation-pane actions (plain JSON, like the legacy useHttp islands) ---

export const markReplyRead = (replyId: string) =>
    apiFetch<void>(`engagement/${replyId}/read`, { method: 'POST' });

export const archiveReply = (replyId: string) =>
    apiFetch<void>(`engagement/${replyId}/archive`, { method: 'POST' });

export const respondToReply = (
    replyId: string,
    body: { text?: string; media?: string[] },
) =>
    apiFetch<{ reply: ReplyItem }>(`engagement/${replyId}/reply`, {
        method: 'POST',
        body,
    });

export const likeReply = (replyId: string) =>
    apiFetch<{ is_liked: boolean }>(`engagement/${replyId}/like`, {
        method: 'POST',
    });

export const unlikeReply = (replyId: string) =>
    apiFetch<{ is_liked: boolean }>(`engagement/${replyId}/like`, {
        method: 'DELETE',
    });

export const destroyReply = (replyId: string) =>
    apiFetch<void>(`engagement/${replyId}`, { method: 'DELETE' });
