import { queryOptions, useMutation } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { queryClient } from '@/lib/query-client';

export type SyncAccount = {
    id: string;
    platform: string;
    handle: string;
    display_name: string | null;
    avatar_url: string | null;
    status: string;
    supports_native: boolean;
};

export type SyncPipeline = {
    id: string;
    name: string;
    enabled: boolean;
    source_connected_account_id: string;
    destination_connected_account_ids: string[];
};

export type SyncData = {
    accounts: SyncAccount[];
    pipelines: SyncPipeline[];
    maxPipelines: number;
    canCreate: boolean;
    trackableAccounts: SyncAccount[];
    trackedAccountIds: string[];
    canTrack: boolean;
    maxTracked: number;
};

export type CreatePipelinePayload = {
    name: string;
    enabled?: boolean;
    source_connected_account_id: string;
    destination_connected_account_ids: string[];
    track_source?: boolean;
};

export const syncQuery = queryOptions({
    queryKey: ['sync'] as const,
    queryFn: (): Promise<SyncData> => apiFetch<SyncData>(endpoints.sync),
});

const invalidateSync = () =>
    queryClient.invalidateQueries({ queryKey: syncQuery.queryKey });

export function useCreatePipeline() {
    return useMutation({
        mutationFn: (payload: CreatePipelinePayload) =>
            apiFetch<{ id: string; message: string }>(endpoints.sync, {
                method: 'POST',
                body: payload,
            }),
        onSuccess: invalidateSync,
    });
}

export function useUpdatePipeline() {
    return useMutation({
        mutationFn: ({
            id,
            ...payload
        }: { id: string } & Partial<
            Pick<
                CreatePipelinePayload,
                'name' | 'enabled' | 'source_connected_account_id'
            > & { destination_connected_account_ids: string[] }
        >) =>
            apiFetch<{ message: string }>(endpoints.syncPipeline(id), {
                method: 'PATCH',
                body: payload,
            }),
        onSuccess: invalidateSync,
    });
}

export function useDeletePipeline() {
    return useMutation({
        mutationFn: (id: string) =>
            apiFetch<{ message: string }>(endpoints.syncPipeline(id), {
                method: 'DELETE',
            }),
        onSuccess: invalidateSync,
    });
}

export function useTrackAccount() {
    return useMutation({
        mutationFn: (accountId: string) =>
            apiFetch<{ message: string }>(
                endpoints.syncNativeTracking(accountId),
                { method: 'POST' },
            ),
        onSuccess: invalidateSync,
    });
}

export function useUntrackAccount() {
    return useMutation({
        mutationFn: (accountId: string) =>
            apiFetch<{ message: string }>(
                endpoints.syncNativeTracking(accountId),
                { method: 'DELETE' },
            ),
        onSuccess: invalidateSync,
    });
}
