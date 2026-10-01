import { infiniteQueryOptions, queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import type {
    ConversationItem,
    MessageItem,
    MessagesFilters,
} from '@/pages/messages/types';

import { INBOX_POLL_MS, type Paginator } from '../engagement/engagement';

export type MessagesIndexData = {
    conversations: Paginator<ConversationItem>;
    filters: MessagesFilters;
};

export type MessagesThreadData = {
    conversation: ConversationItem;
    messages: MessageItem[];
};

/** Infinite query so the stream merges pages on scroll, like `Inertia::scroll()`. */
export const messagesQuery = (filters: MessagesFilters) =>
    infiniteQueryOptions({
        queryKey: ['messages', filters],
        queryFn: ({ pageParam }) =>
            apiFetch<MessagesIndexData>(
                `messages?${filters.archived ? 'archived=1&' : ''}page=${pageParam}`,
            ),
        initialPageParam: 1,
        getNextPageParam: (last) =>
            last.conversations.current_page < last.conversations.last_page
                ? last.conversations.current_page + 1
                : undefined,
        refetchInterval: INBOX_POLL_MS,
    });

export const messagesThreadQuery = (conversationId: string) =>
    queryOptions({
        queryKey: ['messages', 'thread', conversationId],
        queryFn: () =>
            apiFetch<MessagesThreadData>(`messages/${conversationId}/thread`),
    });

// --- Conversation-pane actions ---

export const markConversationRead = (conversationId: string) =>
    apiFetch<void>(`messages/${conversationId}/read`, { method: 'POST' });

export const archiveConversation = (conversationId: string) =>
    apiFetch<void>(`messages/${conversationId}/archive`, { method: 'POST' });

export const respondToMessage = (
    conversationId: string,
    body: { text?: string; media?: string[] },
) =>
    apiFetch<{ message: MessageItem }>(`messages/${conversationId}/reply`, {
        method: 'POST',
        body,
    });
