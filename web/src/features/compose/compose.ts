import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import type {
    Account,
    AccountSet,
    PlatformLimits,
    PostView,
    WorkspaceMention,
} from '@/types/compose';
import type { PostStatsPayload } from '@/types/metrics';

/** Full composer payload — same shape the Inertia compose page received. */
export type ComposePayload = {
    post: PostView;
    accounts: Account[];
    sets: AccountSet[];
    limits: PlatformLimits[];
    savedMentions: WorkspaceMention[];
    metricsEnabled: boolean;
};

export function composeQuery(postId: string) {
    return queryOptions({
        queryKey: ['posts', 'compose', postId] as const,
        queryFn: () => apiFetch<ComposePayload>(`posts/${postId}/compose`),
        // Post status targets transition async (queued → publishing →
        // published/failed); the page re-polls while any are in flight.
        staleTime: 0,
    });
}

export function postMetricsQuery(postId: string) {
    return queryOptions({
        queryKey: ['posts', 'metrics', postId] as const,
        queryFn: () =>
            apiFetch<{ stats: PostStatsPayload | null }>(
                `posts/${postId}/metrics`,
            ),
        staleTime: 60_000,
    });
}

export type SaveResponse = { post: PostView };

export function createPost(body: unknown) {
    return apiFetch<SaveResponse>('posts', { method: 'POST', body });
}

export function updatePost(postId: string, body: unknown) {
    return apiFetch<SaveResponse>(`posts/${postId}`, {
        method: 'PATCH',
        body,
    });
}

export function fetchPost(postId: string) {
    return apiFetch<SaveResponse>(`posts/${postId}`);
}

export function saveWorkspaceMention(body: {
    name: string;
    handles: Record<string, string | null>;
}) {
    return apiFetch<{ mention: WorkspaceMention }>('workspace-mentions', {
        method: 'POST',
        body,
    });
}

export function deleteWorkspaceMention(mentionId: string) {
    return apiFetch(`workspace-mentions/${mentionId}`, { method: 'DELETE' });
}
