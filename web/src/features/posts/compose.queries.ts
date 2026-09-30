import { queryOptions } from '@tanstack/react-query';

import { apiCall, apiClient, apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import type {
    PlatformLimits,
    PostView,
    WorkspaceMention,
} from '@/types/compose';
import type { PostStatsPayload } from '@/types/metrics';

/** Query keys shared by the composer page and its status polling. */
export const postKeys = {
    detail: (id: string) => ['posts', 'detail', id] as const,
    metrics: (id: string) => ['posts', 'detail', id, 'metrics'] as const,
    mentions: ['workspace-mentions'] as const,
    platformLimits: ['platform-limits'] as const,
};

export const postDetailQuery = (id: string) =>
    queryOptions({
        queryKey: postKeys.detail(id),
        queryFn: () => apiFetch<PostView>(endpoints.post(id)),
    });

/** Read-only stats rollup for a published post (POST metrics/refresh re-captures). */
export const postMetricsQuery = (id: string) =>
    queryOptions({
        queryKey: postKeys.metrics(id),
        queryFn: () =>
            apiFetch<PostStatsPayload>(`posts/${id}/metrics`),
    });

export const workspaceMentionsQuery = queryOptions({
    queryKey: postKeys.mentions,
    queryFn: () =>
        apiCall<{ data: WorkspaceMention[] }>(
            apiClient.GET('/workspace-mentions'),
        ),
});

/** Per-platform constraints (text/media limits) the composer enforces. */
export const platformLimitsQuery = queryOptions({
    queryKey: postKeys.platformLimits,
    queryFn: () =>
        apiFetch<{ limits: PlatformLimits[] }>(endpoints.platformLimits),
});
