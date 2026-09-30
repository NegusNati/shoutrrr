import { queryOptions } from '@tanstack/react-query';

import type { PostRowData } from '@/components/posts/post-row';
import { apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';

export type CalendarData = {
    month: string;
    posts: PostRowData[];
};

export const calendarKeys = {
    month: (yyyymm: string) => ['calendar', yyyymm] as const,
};

/** GET /api/v1/calendar?month=YYYY-MM — scheduled/published posts for the grid window. */
export function calendarQuery(yyyymm: string) {
    return queryOptions({
        queryKey: calendarKeys.month(yyyymm),
        queryFn: (): Promise<CalendarData> =>
            apiFetch<CalendarData>(endpoints.calendar(yyyymm)),
    });
}
