import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import type { PublicPostView } from '@/types/share';

export const publicShareQuery = (token: string) =>
    queryOptions({
        queryKey: ['public-share', token],
        queryFn: () =>
            apiFetch<{ post: PublicPostView | null }>(
                `shares/public/${encodeURIComponent(token)}`,
            ),
        staleTime: 60_000,
        retry: false,
    });
