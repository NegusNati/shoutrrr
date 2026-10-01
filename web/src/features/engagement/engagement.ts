import { infiniteQueryOptions, queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import type {
    AccountFacet,
    EngagementFilters,
    PostFacet,
    ReplyItem,
} from '@/pages/engagement/types';
import type { PlatformName, WorkspaceMention } from '@/types/compose';

/** Slice of Laravel's LengthAwarePaginator JSON the pages actually read. */
export type Paginator<T> = {
    current_page: number;
    last_page: number;
    total: number;
    data: T[];
};

export type EngagementIndexData = {
    replies: Paginator<ReplyItem>;
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

const replyParams = (filters: EngagementFilters, page = 1): string => {
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
    if (page > 1) {
        params.set('page', String(page));
    }
    const query = params.toString();

    return query === '' ? '' : `?${query}`;
};

/**
 * Infinite query so the stream merges pages as the reader scrolls — matching
 * the legacy `Inertia::scroll()` behavior. The poll cadence refetches every
 * loaded page.
 */
export const engagementQuery = (filters: EngagementFilters) =>
    infiniteQueryOptions({
        queryKey: ['engagement', filters],
        queryFn: ({ pageParam }) =>
            apiFetch<EngagementIndexData>(
                `engagement${replyParams(filters, pageParam)}`,
            ),
        initialPageParam: 1,
        getNextPageParam: (last) =>
            last.replies.current_page < last.replies.last_page
                ? last.replies.current_page + 1
                : undefined,
        refetchInterval: INBOX_POLL_MS,
    });

export const engagementThreadQuery = (replyId: string) =>
    queryOptions({
        queryKey: ['engagement', 'thread', replyId],
        queryFn: () =>
            apiFetch<EngagementThreadData>(`engagement/${replyId}/thread`),
    });

/** Save a new mention to the workspace library (POST /workspace-mentions). */
export const createWorkspaceMention = (body: {
    name: string;
    handles: Record<string, string>;
}) =>
    apiFetch<{ mention: WorkspaceMention }>('workspace-mentions', {
        method: 'POST',
        body,
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
