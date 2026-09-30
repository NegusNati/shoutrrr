import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';

import WorkspaceSettingsController from '@/actions/App/Http/Controllers/Settings/WorkspaceSettingsController';
import { ScheduleEditor } from '@/components/queue/schedule-editor';
import { QueueSkeleton } from '@/components/skeletons/queue-skeleton';
import {
    postingScheduleQuery,
    updatePostingSchedule,
} from '@/features/queue/queue';
import { queryClient } from '@/lib/query-client';
import { normalizeSlots, type Slot } from '@/lib/queue/queue-schedule';

export default function QueueIndexPage() {
    const { data, isPending } = useQuery(postingScheduleQuery);

    const mutation = useMutation({
        mutationFn: (slots: Slot[]) => updatePostingSchedule(slots),
        onSuccess: (data) => {
            queryClient.setQueryData(postingScheduleQuery.queryKey, data);
            toast.success('Queue saved.');
        },
        onError: () => toast.error('Could not save the queue.'),
    });

    const timezone = data?.timezone ?? 'UTC';

    return (
        <div className="mx-auto w-full max-w-6xl space-y-5 px-4 pt-6 pb-16 sm:px-6">
            <div className="flex flex-col gap-1">
                <h1 className="text-[22px] leading-tight font-semibold tracking-tight">
                    Posting queue
                </h1>
                <p className="text-[13px] text-muted-foreground">
                    Queued posts go out at these times each week, in{' '}
                    <span className="font-medium text-foreground">
                        {timezone}
                    </span>{' '}
                    ·{' '}
                    <a
                        href={WorkspaceSettingsController.showOverview().url}
                        className="font-medium text-foreground underline underline-offset-2 hover:no-underline"
                    >
                        change
                    </a>
                </p>
            </div>

            {isPending || !data ? (
                <QueueSkeleton />
            ) : (
                <ScheduleEditor
                    key={normalizeSlots(data.slots)
                        .map((s) => `${s.weekday}:${s.hour}:${s.minute}`)
                        .join(',')}
                    initialSlots={normalizeSlots(data.slots)}
                    timezone={timezone}
                    canManage={data.canManage}
                    saving={mutation.isPending}
                    onSave={(slots) => mutation.mutate(slots)}
                />
            )}
        </div>
    );
}
