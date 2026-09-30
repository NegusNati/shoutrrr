import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import type { Slot } from '@/lib/queue/queue-schedule';

export type PostingScheduleData = {
    timezone: string | null;
    canManage: boolean;
    slots: Slot[];
};

export const postingScheduleQuery = queryOptions({
    queryKey: ['posting-schedule'],
    queryFn: () => apiFetch<PostingScheduleData>('posting-schedule'),
});

export const updatePostingSchedule = (slots: Slot[]) =>
    apiFetch<PostingScheduleData>('posting-schedule', {
        method: 'PUT',
        body: { slots },
    });
