import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import type { AnalyticsPageProps } from '@/types/metrics';

export const analyticsKeys = {
    index: ['analytics'] as const,
};

export const analyticsQuery = (days: number) =>
    queryOptions({
        queryKey: [...analyticsKeys.index, days],
        queryFn: () =>
            apiFetch<AnalyticsPageProps>(`${endpoints.analytics}?days=${days}`),
        placeholderData: (previousData) => previousData,
    });
