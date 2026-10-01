import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import type { PublicPostView } from '@/types/share';

export type PublicShareData = {
    post: PublicPostView | null;
};

/** Public read — the share token itself is the capability, no session needed. */
export const shareQuery = (token: string) =>
    queryOptions({
        queryKey: ['share', token] as const,
        queryFn: (): Promise<PublicShareData> =>
            apiFetch<PublicShareData>(endpoints.publicShare(token)),
        staleTime: 60_000,
    });
