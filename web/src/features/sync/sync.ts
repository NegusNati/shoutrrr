import {
    queryOptions,
    useMutation,
    useQueryClient,
} from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';

export type SyncAccount = {
    id: string;
    platform: string;
    handle: string;
    display_name: string | null;
    avatar_url: string | null;
    status: string;
    supports_native: boolean;
};

export type Pipeline = {
    id: string;
    name: string;
    enabled: boolean;
    source_connected_account_id: string;
    destination_connected_account_ids: string[];
};

export type SyncPipelinesPayload = {
    accounts: SyncAccount[];
    pipelines: Pipeline[];
    maxPipelines: number;
    canCreate: boolean;
    trackableAccounts: SyncAccount[];
    trackedAccountIds: string[];
    canTrack: boolean;
    maxTracked: number;
};

export const syncPipelinesQuery = queryOptions({
    queryKey: ['sync-pipelines'],
    queryFn: () => apiFetch<SyncPipelinesPayload>('sync-pipelines'),
});

export type CreatePipelineInput = {
    name: string;
    source_connected_account_id: string;
    destination_connected_account_ids: string[];
    track_source: boolean;
};

function invalidate(queryClient: ReturnType<typeof useQueryClient>) {
    return () =>
        queryClient.invalidateQueries({ queryKey: ['sync-pipelines'] });
}

export function useCreatePipeline() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (input: CreatePipelineInput) =>
            apiFetch<{ id: string; tracked_source: boolean }>(
                'sync-pipelines',
                { method: 'POST', body: input },
            ),
        onSuccess: invalidate(queryClient),
    });
}

export function useUpdatePipeline() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (input: {
            id: string;
            name?: string;
            enabled?: boolean;
            source_connected_account_id?: string;
            destination_connected_account_ids?: string[];
        }) => {
            const { id, ...body } = input;
            return apiFetch(`sync-pipelines/${id}`, {
                method: 'PATCH',
                body,
            });
        },
        onSuccess: invalidate(queryClient),
    });
}

export function useDeletePipeline() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (id: string) =>
            apiFetch(`sync-pipelines/${id}`, { method: 'DELETE' }),
        onSuccess: invalidate(queryClient),
    });
}

export function useSetNativeTracking() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (input: { accountId: string; enabled: boolean }) =>
            apiFetch(`sync-pipelines/native-tracking/${input.accountId}`, {
                method: input.enabled ? 'POST' : 'DELETE',
            }),
        onSuccess: invalidate(queryClient),
    });
}
