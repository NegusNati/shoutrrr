import { queryOptions } from '@tanstack/react-query';

import { apiCall, apiClient } from '@/lib/api';

export type CommandPost = {
    id: string;
    excerpt: string;
    status: string;
    scheduled_at: string | null;
};

export type CommandSearchData = {
    posts: CommandPost[];
};

/** Post-body search backing the ⌘K palette — session-only, workspace-scoped. */
export const commandSearchQuery = (q: string) =>
    queryOptions({
        queryKey: ['command-search', q],
        queryFn: () =>
            apiCall<CommandSearchData>(
                apiClient.GET('/command-search', {
                    params: { query: { q } },
                }),
            ),
    });
