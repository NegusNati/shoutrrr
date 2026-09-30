import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import type { AnalyticsPageProps } from '@/types/metrics';

export const analyticsQuery = (days: number) =>
    queryOptions({
        queryKey: ['analytics', days],
        queryFn: () => apiFetch<AnalyticsPageProps>(`analytics?days=${days}`),
    });
