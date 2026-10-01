import { queryOptions } from '@tanstack/react-query';

import type { PostRowData } from '@/components/posts/post-row';
import { apiCall, apiClient, apiFetch } from '@/lib/api';
import { queryClient } from '@/lib/query-client';

export type CalendarData = {
    month: string;
    posts: PostRowData[];
};

/**
 * Posts in the 42-day Sunday-first window around the month's 1st —
 * the same window the legacy Inertia calendar fetched for a month anchor.
 */
export const calendarQuery = (month: string) =>
    queryOptions({
        queryKey: ['calendar', month],
        queryFn: () =>
            apiCall<CalendarData>(
                apiClient.GET('/calendar', {
                    params: { query: { month } },
                }),
            ),
    });

/** POST /posts/{id}/schedule — drag-to-reschedule target. */
export const reschedulePost = (postId: string, scheduledAt: string | null) =>
    apiFetch(`posts/${postId}/schedule`, {
        method: 'POST',
        body: { scheduled_at: scheduledAt },
    });

/** Refetches every cached calendar month after a mutation settles. */
export function invalidateCalendarQueries() {
    void queryClient.invalidateQueries({ queryKey: ['calendar'] });
}
